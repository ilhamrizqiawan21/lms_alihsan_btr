<?php
include '../config.php';
cek_login([1]);

// ========== EXPORT EXCEL (pakai PhpSpreadsheet, bukan HTML fake xls) ==========
if (isset($_GET['export_excel'])) {
    require_once '../vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;

    $result = $conn->query("SELECT * FROM log_login ORDER BY login_time DESC");

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Log Login');

    // Header kolom
    $headers = ['No', 'Waktu Login', 'Username', 'Nama Lengkap', 'Role', 'IP Address', 'User Agent'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $sheet->getStyle('A1:G1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $row = 2;
    $no  = 1;
    while ($log = $result->fetch_assoc()) {
        $sheet->setCellValueByColumnAndRow(1, $row, $no++);
        $sheet->setCellValueByColumnAndRow(2, $row, date('d/m/Y H:i:s', strtotime($log['login_time'])));
        $sheet->setCellValueByColumnAndRow(3, $row, $log['username']);
        $sheet->setCellValueByColumnAndRow(4, $row, $log['nama_lengkap']);
        $sheet->setCellValueByColumnAndRow(5, $row, $log['role']);
        $sheet->setCellValueByColumnAndRow(6, $row, $log['ip_address']);
        $sheet->setCellValueByColumnAndRow(7, $row, $log['user_agent']);
        $row++;
    }

    foreach (range('A','G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="log_login_' . date('Ymd_His') . '.xlsx"');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

$title = 'Log Login';
include '../includes/header.php';

// ========== PAGINATION ==========
$limit  = 20;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ✅ Filter opsional berdasarkan username/role
$filter_username = trim($_GET['username'] ?? '');
$filter_role     = $_GET['role'] ?? '';
$allowed_roles   = ['admin', 'guru', 'siswa', 'kepala_sekolah'];

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($filter_username !== '') {
    $where   .= " AND (username LIKE ? OR nama_lengkap LIKE ?)";
    $like     = "%$filter_username%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($filter_role !== '' && in_array($filter_role, $allowed_roles)) {
    $where   .= " AND role = ?";
    $params[] = $filter_role;
    $types   .= 's';
}

// Total untuk pagination
$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM log_login $where");
if ($params) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows  = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = (int)ceil($total_rows / $limit);

// Data halaman ini
$stmt_data = $conn->prepare("SELECT * FROM log_login $where ORDER BY login_time DESC LIMIT ? OFFSET ?");
$all_params   = array_merge($params, [$limit, $offset]);
$all_types    = $types . 'ii';
$stmt_data->bind_param($all_types, ...$all_params);
$stmt_data->execute();
$logs = $stmt_data->get_result();
?>

<style>
.pagination { display:flex; justify-content:center; gap:6px; margin-top:1rem; flex-wrap:wrap; }
.pagination a {
    padding:6px 12px; border:1px solid #ddd; border-radius:6px;
    text-decoration:none; color:var(--primary-600); transition:all 0.2s;
}
.pagination a:hover  { background:var(--primary-50); }
.pagination a.active { background:var(--primary-600); color:white; border-color:var(--primary-600); }
.ua-cell { max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
.filter-bar { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; align-items:flex-end; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-history"></i> Log Login</h2>
    <p class="page-subtitle">Riwayat semua aktivitas login pengguna</p>
</div>

<div class="form-container">
    <div class="form-title"><i class="fas fa-filter"></i> Filter &amp; Export</div>

    <!-- Filter -->
    <form method="GET" class="filter-bar">
        <div class="form-group" style="margin:0;">
            <label>Cari Username / Nama</label>
            <input type="text" name="username" class="form-input"
                   value="<?= e($filter_username) ?>" placeholder="Ketik username...">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Role</label>
            <select name="role" class="form-select">
                <option value="">Semua</option>
                <?php foreach ($allowed_roles as $r): ?>
                    <option value="<?= $r ?>" <?= $filter_role === $r ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; gap:8px; align-items:flex-end; padding-bottom:2px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
            <a href="log_login" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
            <a href="?export_excel=1&username=<?= urlencode($filter_username) ?>&role=<?= urlencode($filter_role) ?>"
               class="btn btn-outline">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
    </form>

    <small style="color:var(--gray-500);">
        Menampilkan <?= $logs->num_rows ?> dari <?= $total_rows ?> data
    </small>

    <!-- Tabel -->
    <div class="table-wrapper" style="margin-top:0.75rem;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>No</th><th>Waktu</th><th>Username</th>
                    <th>Nama</th><th>Role</th><th>IP Address</th><th>User Agent</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs->num_rows > 0):
                $no = $offset + 1;
                while ($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center"><?= $no++ ?></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($log['login_time'])) ?></td>
                    <td><?= e($log['username']) ?></td>
                    <td><?= e($log['nama_lengkap']) ?></td>
                    <td><?= ucfirst(str_replace('_', ' ', e($log['role']))) ?></td>
                    <td><?= e($log['ip_address']) ?></td>
                    <td class="ua-cell" title="<?= e($log['user_agent']) ?>">
                        <?= e(substr($log['user_agent'], 0, 60)) ?>...
                    </td>
                </tr>
            <?php endwhile;
            else: ?>
                <tr><td colspan="7" style="text-align:center; padding:30px;">Tidak ada data log login.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&username=<?= urlencode($filter_username) ?>&role=<?= urlencode($filter_role) ?>">«</a>
            <a href="?page=<?= $page-1 ?>&username=<?= urlencode($filter_username) ?>&role=<?= urlencode($filter_role) ?>">‹ Prev</a>
        <?php endif; ?>

        <?php
        // Tampilkan maksimal 5 nomor halaman di sekitar halaman aktif
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?= $i ?>&username=<?= urlencode($filter_username) ?>&role=<?= urlencode($filter_role) ?>"
               class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?>&username=<?= urlencode($filter_username) ?>&role=<?= urlencode($filter_role) ?>">Next ›</a>
            <a href="?page=<?= $total_pages ?>&username=<?= urlencode($filter_username) ?>&role=<?= urlencode($filter_role) ?>">»</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; 