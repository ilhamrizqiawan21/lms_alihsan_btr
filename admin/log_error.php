<?php
include '../config.php';
cek_login([1]); // hanya admin

$title = 'Log Error Sistem';
include '../includes/header.php';

// ========== HAPUS LOG ==========
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $del = $conn->prepare("DELETE FROM system_errors WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    set_flash('success', 'Log error dihapus.');
    header('Location: log_error');
    exit;
}
if (isset($_GET['hapus_semua'])) {
    $conn->query("TRUNCATE TABLE system_errors");
    set_flash('success', 'Semua log error dihapus.');
    header('Location: log_error');
    exit;
}
if (isset($_GET['resolved']) && is_numeric($_GET['resolved'])) {
    $id = (int)$_GET['resolved'];
    $upd = $conn->prepare("UPDATE system_errors SET is_resolved = 1 WHERE id = ?");
    $upd->bind_param("i", $id);
    $upd->execute();
    set_flash('success', 'Log ditandai sebagai resolved.');
    header('Location: log_error');
    exit;
}

// ========== FILTER ==========
$level_filter = $_GET['level'] ?? '';
$search = trim($_GET['search'] ?? '');
$show_resolved = isset($_GET['show_resolved']) ? (int)$_GET['show_resolved'] : 0;

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($level_filter !== '') {
    $where .= " AND error_level = ?";
    $params[] = $level_filter;
    $types .= 's';
}
if ($show_resolved == 0) {
    $where .= " AND is_resolved = 0";
}
if ($search !== '') {
    $where .= " AND (message LIKE ? OR file LIKE ? OR url LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

// Pagination
$limit = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM system_errors $where";
$stmt_count = $conn->prepare($count_sql);
if ($params) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

$sql = "SELECT * FROM system_errors $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$all_params = array_merge($params, [$limit, $offset]);
if ($params) {
    $all_types = $types . 'ii';
    $stmt->bind_param($all_types, ...$all_params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$errors = $stmt->get_result();

// Ambil daftar level unik untuk filter
$levels_res = $conn->query("SELECT DISTINCT error_level FROM system_errors ORDER BY error_level");
$levels = [];
while ($row = $levels_res->fetch_assoc()) $levels[] = $row['error_level'];
?>

<style>
.error-detail pre { background: #f1f5f9; padding: 10px; border-radius: 8px; overflow-x: auto; font-size: 0.75rem; margin-top: 8px; }
.error-row { border-left: 4px solid #ef4444; margin-bottom: 12px; background: white; border-radius: 8px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.error-row.resolved { border-left-color: #10b981; opacity: 0.7; }
.error-level { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; }
.level-ERROR { background: #fee2e2; color: #991b1b; }
.level-WARNING { background: #fef9c3; color: #854d0e; }
.level-NOTICE { background: #e0e7ff; color: #1e40af; }
.level-EXCEPTION { background: #f3e8ff; color: #6b21a5; }
.level-FATAL { background: #7f1d1d; color: white; }
.filter-bar { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; align-items: flex-end; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-bug"></i> Log Error Sistem</h2>
    <p class="page-subtitle">Catatan semua error, warning, exception yang terjadi pada aplikasi</p>
</div>

<?= show_flash(); ?>

<div class="form-container">
    <div class="form-title"><i class="fas fa-filter"></i> Filter & Aksi</div>
    <form method="GET" class="filter-bar">
        <div class="form-group" style="margin:0;">
            <label>Level Error</label>
            <select name="level" class="form-select">
                <option value="">Semua</option>
                <?php foreach ($levels as $lev): ?>
                    <option value="<?= $lev ?>" <?= $level_filter == $lev ? 'selected' : '' ?>><?= $lev ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Cari (pesan, file, url)</label>
            <input type="text" name="search" class="form-input" value="<?= htmlspecialchars($search) ?>" placeholder="...">
        </div>
        <div class="form-group" style="margin:0;">
            <label><input type="checkbox" name="show_resolved" value="1" <?= $show_resolved ? 'checked' : ?>> Tampilkan resolved</label>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="log_error" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
            <a href="?hapus_semua=1" class="btn btn-danger" onclick="return confirm('Hapus SEMUA log error?')"><i class="fas fa-trash-alt"></i> Hapus Semua</a>
        </div>
    </form>

    <div class="table-wrapper">
        <?php if ($errors->num_rows == 0): ?>
            <div class="alert alert-info">Tidak ada log error.</div>
        <?php else: ?>
            <?php while ($err = $errors->fetch_assoc()): ?>
                <div class="error-row <?= $err['is_resolved'] ? 'resolved' : '' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span class="error-level level-<?= $err['error_level'] ?>"><?= $err['error_level'] ?></span>
                            <strong><?= htmlspecialchars(substr($err['message'], 0, 150)) ?></strong>
                            <span style="font-size:0.7rem; color:gray; margin-left:8px;"><?= date('d/m/Y H:i:s', strtotime($err['created_at'])) ?></span>
                        </div>
                        <div class="action-buttons" style="display:flex; gap:6px;">
                            <?php if (!$err['is_resolved']): ?>
                                <a href="?resolved=<?= $err['id'] ?>&<?= http_build_query($_GET) ?>" class="btn-sm btn-sm-success">✓ Tandai resolved</a>
                            <?php endif; ?>
                            <a href="?hapus=<?= $err['id'] ?>&<?= http_build_query($_GET) ?>" class="btn-sm btn-sm-danger" onclick="return confirm('Hapus log ini?')">🗑 Hapus</a>
                        </div>
                    </div>
                    <div style="font-size:0.8rem; margin-top: 6px;">
                        <i class="fas fa-file"></i> <?= htmlspecialchars($err['file'] ?? '-') ?> : <?= $err['line'] ?? '-' ?>
                        <?php if ($err['url']): ?> <br><i class="fas fa-link"></i> <?= htmlspecialchars($err['url']) ?><?php endif; ?>
                        <?php if ($err['ip_address']): ?> <br><i class="fas fa-network-wired"></i> IP: <?= htmlspecialchars($err['ip_address']) ?><?php endif; ?>
                    </div>
                    <?php if ($err['trace']): ?>
                        <div class="error-detail">
                            <details>
                                <summary style="cursor:pointer; font-size:0.75rem;">Lihat stack trace</summary>
                                <pre><?= htmlspecialchars($err['trace']) ?></pre>
                            </details>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i=1; $i<=$total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&level=<?= urlencode($level_filter) ?>&search=<?= urlencode($search) ?>&show_resolved=<?= $show_resolved ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>