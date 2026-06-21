<?php
include '../config.php';
cek_login([1]);

require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ========== DOWNLOAD TEMPLATE ==========
if (isset($_GET['download_template'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template Siswa');
    $sheet->setCellValue('A1', 'NIS');
    $sheet->setCellValue('B1', 'Nama Lengkap');
    $sheet->setCellValue('C1', 'Nama Kelas');
    $sheet->setCellValue('D1', 'Jenis Kelamin (L/P)');
    $sheet->setCellValue('A2', '12345');
    $sheet->setCellValue('B2', 'Ahmad Fauzi');
    $sheet->setCellValue('C2', 'IX-A');
    $sheet->setCellValue('D2', 'L');
    $sheet->setCellValue('A3', '12346');
    $sheet->setCellValue('B3', 'Siti Aminah');
    $sheet->setCellValue('C3', 'IX-A');
    $sheet->setCellValue('D3', 'P');
    $sheet->getStyle('A1:D1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    foreach (range('A','D') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="template_import_siswa.xlsx"');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

// ========== RESET PASSWORD SISWA ==========
if (isset($_GET['reset_pass_siswa'])) {
    $siswa_id = (int)$_GET['reset_pass_siswa'];
    $stmt = $conn->prepare("SELECT user_id FROM siswa WHERE id=?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $siswa = $stmt->get_result()->fetch_assoc();
    if ($siswa) {
        $new_pass = password_hash('123456', PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $new_pass, $siswa['user_id']);
        $upd->execute();
        set_flash('success', 'Password siswa direset menjadi 123456.');
    } else {
        set_flash('warning', 'Siswa tidak ditemukan.');
    }
    header('Location: kelas_siswa');
    exit;
}

// ========== LULUSKAN KELAS (Khusus IX) ==========
if (isset($_GET['lulus_kelas'])) {
    $kelas_id_lulus = (int)$_GET['lulus_kelas'];
    // Pastikan kelas adalah tingkat IX untuk safety
    $chk = $conn->prepare("SELECT tingkat FROM kelas WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $kelas_id_lulus);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if ($row && $row['tingkat'] === 'IX') {
        $stmt = $conn->prepare("UPDATE siswa SET status = 'lulus' WHERE kelas_id = ? AND status = 'aktif'");
        if ($stmt) {
            $stmt->bind_param("i", $kelas_id_lulus);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            set_flash('success', "Berhasil menandai $affected siswa sebagai lulus pada kelas ini.");
        } else {
            set_flash('warning', 'Terjadi kesalahan saat memproses luluskan kelas.');
        }
    } else {
        set_flash('warning', 'Kelas tidak valid atau bukan tingkat IX.');
    }
    header('Location: kelas_siswa');
    exit;
}

// ========== EDIT SISWA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_siswa'])) {
    csrf_verify();
    $id       = (int)$_POST['id'];
    $nama     = trim($_POST['nama']);
    $kelas_id = (int)$_POST['kelas_id'];
    $jk       = $_POST['jenis_kelamin']; // L atau P
    $tinggal_kelas = isset($_POST['tinggal_kelas']) ? 1 : 0;

    if (empty($nama)) {
        set_flash('danger', 'Nama siswa tidak boleh kosong.');
        header('Location: kelas_siswa');
        exit;
    }
    // Update nama & jenis_kelamin di users
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, jenis_kelamin = ? WHERE id = (SELECT user_id FROM siswa WHERE id = ?)");
    $stmt->bind_param("ssi", $nama, $jk, $id);
    $stmt->execute();

    // Update kelas_id & tinggal_kelas di siswa
    $check_col = $conn->query("SHOW COLUMNS FROM `siswa` LIKE 'tinggal_kelas'");
    $has_tk = ($check_col->num_rows > 0);

    if ($kelas_id === 0) {
        if ($has_tk) {
            $stmt2 = $conn->prepare("UPDATE siswa SET kelas_id = NULL, tinggal_kelas = ? WHERE id = ?");
            $stmt2->bind_param("ii", $tinggal_kelas, $id);
        } else {
            $stmt2 = $conn->prepare("UPDATE siswa SET kelas_id = NULL WHERE id = ?");
            $stmt2->bind_param("i", $id);
        }
    } else {
        if ($has_tk) {
            $stmt2 = $conn->prepare("UPDATE siswa SET kelas_id = ?, tinggal_kelas = ? WHERE id = ?");
            $stmt2->bind_param("iii", $kelas_id, $tinggal_kelas, $id);
        } else {
            $stmt2 = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
            $stmt2->bind_param("ii", $kelas_id, $id);
        }
    }
    $stmt2->execute();
    set_flash('success', 'Data siswa berhasil diperbarui.');
    header('Location: kelas_siswa');
    exit;
}

// ========== TAMBAH KELAS ==========
if (isset($_POST['tambah_kelas'])) {
    csrf_verify();
    $tingkat    = in_array($_POST['tingkat'], ['VII','VIII','IX']) ? $_POST['tingkat'] : 'VII';
    $nama_kelas = trim($_POST['nama_kelas']);
    $stmt = $conn->prepare("INSERT INTO kelas (tingkat, nama_kelas) VALUES (?, ?)");
    $stmt->bind_param("ss", $tingkat, $nama_kelas);
    $stmt->execute();
    set_flash('success', 'Kelas berhasil ditambahkan.');
    header('Location: kelas_siswa');
    exit;
}

// ========== HAPUS KELAS ==========
if (isset($_GET['hapus_kelas'])) {
    $id = (int)$_GET['hapus_kelas'];
    $cek = $conn->prepare("SELECT id FROM siswa WHERE kelas_id=? LIMIT 1");
    $cek->bind_param("i", $id);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        set_flash('warning', 'Hapus siswa terlebih dahulu di kelas ini.');
    } else {
        $del = $conn->prepare("DELETE FROM kelas WHERE id=?");
        $del->bind_param("i", $id);
        $del->execute();
        set_flash('success', 'Kelas dihapus.');
    }
    header('Location: kelas_siswa');
    exit;
}

// ========== TAMBAH SISWA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_siswa'])) {
    csrf_verify();
    $nis      = trim($_POST['nis']);
    $nama     = trim($_POST['nama']);
    $kelas_id = (int)$_POST['kelas_id'];
    $jk       = $_POST['jenis_kelamin'];

    $cek = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $cek->bind_param("s", $nis);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        set_flash('warning', 'NIS sudah terdaftar!');
    } else {
        $password = password_hash('123456', PASSWORD_BCRYPT);
        $ins_user = $conn->prepare("INSERT INTO users (username, password, role_id, nama_lengkap, nip_nis, jenis_kelamin, is_active) VALUES (?, ?, 3, ?, ?, ?, 1)");
        $ins_user->bind_param("sssss", $nis, $password, $nama, $nis, $jk);
        $ins_user->execute();
        $user_id = $conn->insert_id;
        $ins_siswa = $conn->prepare("INSERT INTO siswa (user_id, nis, kelas_id) VALUES (?, ?, ?)");
        $ins_siswa->bind_param("isi", $user_id, $nis, $kelas_id);
        $ins_siswa->execute();
        set_flash('success', 'Siswa berhasil ditambahkan. Password default: 123456');
    }
    header('Location: kelas_siswa');
    exit;
}

// ========== HAPUS SISWA ==========
if (isset($_GET['hapus_siswa'])) {
    $id = (int)$_GET['hapus_siswa'];
    $stmt = $conn->prepare("SELECT user_id FROM siswa WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $del_s = $conn->prepare("DELETE FROM siswa WHERE id=?");
    $del_s->bind_param("i", $id);
    $del_s->execute();
    if ($user) {
        $del_u = $conn->prepare("DELETE FROM users WHERE id=?");
        $del_u->bind_param("i", $user['user_id']);
        $del_u->execute();
    }
    set_flash('success', 'Siswa dihapus.');
    header('Location: kelas_siswa');
    exit;
}

// ========== IMPORT EXCEL ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    csrf_verify();
    if ($_FILES['file_excel']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx','xls'])) {
            set_flash('warning', 'File harus berformat .xlsx atau .xls');
            header('Location: kelas_siswa');
            exit;
        }
        try {
            $spreadsheet = IOFactory::load($_FILES['file_excel']['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // hapus header
            $sukses = $gagal = 0;
            foreach ($rows as $row) {
                if (empty($row[0]) || empty($row[1]) || empty($row[2])) continue;
                $nis = trim($row[0]);
                $nama = trim($row[1]);
                $nama_kelas = trim($row[2]);
                $jk = isset($row[3]) ? strtoupper(trim($row[3])) : '';
                if (!in_array($jk, ['L','P'])) $jk = 'L'; // default L jika kosong/tidak valid

                $stmt_k = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas=? LIMIT 1");
                $stmt_k->bind_param("s", $nama_kelas);
                $stmt_k->execute();
                $kelas_row = $stmt_k->get_result()->fetch_assoc();
                if (!$kelas_row) { $gagal++; continue; }
                $kelas_id = $kelas_row['id'];

                $stmt_c = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
                $stmt_c->bind_param("s", $nis);
                $stmt_c->execute();
                if ($stmt_c->get_result()->num_rows > 0) { $gagal++; continue; }

                $password = password_hash('123456', PASSWORD_BCRYPT);
                $ins_u = $conn->prepare("INSERT INTO users (username, password, role_id, nama_lengkap, nip_nis, jenis_kelamin, is_active) VALUES (?, ?, 3, ?, ?, ?, 1)");
                $ins_u->bind_param("sssss", $nis, $password, $nama, $nis, $jk);
                $ins_u->execute();
                $user_id = $conn->insert_id;
                $ins_s = $conn->prepare("INSERT INTO siswa (user_id, nis, kelas_id) VALUES (?, ?, ?)");
                $ins_s->bind_param("isi", $user_id, $nis, $kelas_id);
                $ins_s->execute();
                $sukses++;
            }
            set_flash('success', "Import selesai: $sukses sukses, $gagal gagal (NIS duplikat / kelas tidak valid).");
        } catch (Exception $e) {
            set_flash('warning', 'Error membaca file: ' . $e->getMessage());
        }
    } else {
        set_flash('warning', 'Upload file gagal.');
    }
    header('Location: kelas_siswa');
    exit;
}

$title = 'Kelola Kelas & Siswa';
include '../includes/header.php';

$kelas = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas");

// Cek kolom tinggal_kelas untuk UI
$check_col = $conn->query("SHOW COLUMNS FROM `siswa` LIKE 'tinggal_kelas'");
$has_tk_col = ($check_col->num_rows > 0);

$query_siswa = "SELECT s.id, s.nis, u.username, u.nama_lengkap, u.password, u.jenis_kelamin, s.status, k.nama_kelas, k.id as kelas_id";
if ($has_tk_col) $query_siswa .= ", s.tinggal_kelas";
$query_siswa .= " FROM siswa s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE s.status = 'aktif'
    ORDER BY COALESCE(k.nama_kelas, ''), u.nama_lengkap";

$siswa = $conn->query($query_siswa);
?>

<style>
.badge-danger { background:#fee2e2; color:#dc2626; padding:4px 10px; border-radius:20px; font-size:0.7rem; display:inline-block; border:1px solid #fecaca; }
.badge-warning { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:20px; font-size:0.7rem; display:inline-block; }
.badge-success { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:20px; font-size:0.7rem; display:inline-block; }
.btn-sm        { padding:5px 12px; font-size:0.75rem; border-radius:20px; text-decoration:none; margin:0 2px; display:inline-flex; align-items:center; gap:4px; transition:all 0.2s; border:none; cursor:pointer; }
.btn-sm-warning{ background:#f59e0b; color:white; }
.btn-sm-warning:hover{ background:#d97706; }
.btn-sm-danger { background:#dc2626; color:white; }
.btn-sm-danger:hover{ background:#b91c1c; }
.btn-sm-success { background:#10b981; color:white; }
.btn-sm-success:hover { background:#059669; }
.modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
.modal.active { display: flex; }
.modal-content { background:white; border-radius:1rem; width:90%; max-width:500px; padding:1.5rem; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-close { background:none; border:none; font-size:1.5rem; cursor:pointer; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-school"></i> Kelas & Siswa</h2>
    <p class="page-subtitle">Kelola data kelas dan siswa, import dari Excel, reset password siswa, edit data siswa.</p>
</div>
<?php show_flash(); ?>

<div class="form-row">
    <div class="form-container">
        <div class="form-title"><i class="fas fa-school"></i> Tambah Kelas Baru</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group"><label>Tingkat</label><select name="tingkat" class="form-select"><option value="VII">VII</option><option value="VIII">VIII</option><option value="IX">IX</option></select></div>
            <div class="form-group"><label>Nama Kelas</label><input type="text" name="nama_kelas" class="form-input" placeholder="Contoh: VII-A" required></div>
            <button type="submit" name="tambah_kelas" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Kelas</button>
        </form>
    </div>
    <div class="form-container">
        <div class="form-title"><i class="fas fa-user-plus"></i> Tambah Siswa Baru</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group"><label>NIS (Username)</label><input type="text" name="nis" class="form-input" required></div>
            <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" class="form-input" required></div>
            <div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin" class="form-select" required><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
            <div class="form-group"><label>Kelas</label><select name="kelas_id" class="form-select"><?php $kelas->data_seek(0); while($k=$kelas->fetch_assoc()): ?><option value="<?= $k['id'] ?>"><?= e($k['nama_kelas']) ?></option><?php endwhile; ?></select></div>
            <button type="submit" name="tambah_siswa" class="btn btn-primary"><i class="fas fa-user-plus"></i> Tambah Siswa</button>
        </form>
    </div>
    <div class="form-container">
        <div class="form-title"><i class="fas fa-file-excel"></i> Import Siswa dari Excel</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group"><label>File Excel (.xlsx/.xls)</label><input type="file" name="file_excel" class="form-input" accept=".xlsx,.xls" required></div>
            <small style="color:var(--gray-500); display:block; margin-bottom:1rem;">Format: Kolom A = NIS, B = Nama Lengkap, C = Nama Kelas (contoh: IX-A), D = Jenis Kelamin (L/P). Baris pertama = header.</small>
            <div style="display:flex; gap:8px;"><button type="submit" name="import_excel" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button><a href="?download_template=1" class="btn btn-outline"><i class="fas fa-download"></i> Template</a></div>
        </form>
    </div>
</div>

<!-- Daftar Kelas -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-list"></i> Daftar Kelas</div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead><tr><th>No</th><th>Nama Kelas</th><th>Tingkat</th><th>Aksi</th></tr></thead>
            <tbody><?php $no=1; $kelas->data_seek(0); while($k=$kelas->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center"><?= $no++ ?></td>
                    <td><?= e($k['nama_kelas']) ?></td>
                    <td><?= e($k['tingkat']) ?></td>
                    <td>
                        <?php if ($k['tingkat'] === 'IX'): ?>
                            <a href="?lulus_kelas=<?= $k['id'] ?>" class="btn-sm btn-sm-success" onclick="var _href=this.href;event.preventDefault();confirmModal('Luluskan semua siswa aktif di kelas <?= e($k['nama_kelas']) ?>?').then(r=>r&&(window.location=_href))"><i class="fas fa-graduation-cap"></i> Luluskan</a>
                        <?php endif; ?>
                        <a href="?hapus_kelas=<?= $k['id'] ?>" class="btn-sm btn-sm-danger" data-confirm="Hapus kelas? Pastikan tidak ada siswa."><i class="fas fa-trash"></i> Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?></tbody>
        </table>
    </div>
</div>

<!-- Daftar Siswa -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-users"></i> Daftar Siswa</div>
    <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
        <div>
            <select id="bulk_action" class="form-select">
                <option value="">— Aksi Massal —</option>
                <option value="move">Pindah Kelas</option>
                <option value="delete">Hapus Siswa</option>
            </select>
        </div>
        <div id="bulk_target_wrap" style="display:none;">
            <select id="bulk_target_kelas" class="form-select">
                <option value="0">Pilih Kelas tujuan</option>
                <?php $kelas->data_seek(0); while($k=$kelas->fetch_assoc()): ?><option value="<?= $k['id'] ?>"><?= e($k['nama_kelas']) ?></option><?php endwhile; ?>
            </select>
        </div>
        <div>
            <button id="btn_preview_bulk" class="btn btn-outline" onclick="performBulkPreview()">Preview</button>
            <button id="btn_execute_bulk" class="btn btn-primary" onclick="performBulkExecute()">Eksekusi</button>
        </div>
        <div style="margin-left:auto;">
            <button class="btn btn-outline" onclick="window.location.reload()">Reset</button>
        </div>
    </div>

    <div id="bulk_preview_area" style="margin-bottom:12px; display:none; background:#fff; padding:10px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.06);"></div>

    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="select_all" onclick="toggleSelectAll(this)"></th>
                    <th>NIS</th><th>Username</th><th>Nama</th><th>JK</th><th>Kelas</th>
                    <th>Status Password</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($s = $siswa->fetch_assoc()):
                    $is_md5_default = (strlen($s['password']) == 32 && ctype_xdigit($s['password']) && $s['password'] === md5('123456'));
                    $is_bcrypt_default = (substr($s['password'],0,4) === '$2y$' && password_verify('123456',$s['password']));
                    $is_default = $is_md5_default || $is_bcrypt_default;
                    $status_badge = $is_default ? '<span class="badge-warning">Default (123456)</span>' : '<span class="badge-success">Sudah Diubah</span>';
                    
                    $tinggal_kelas = isset($s['tinggal_kelas']) ? (int)$s['tinggal_kelas'] : 0;
                    $nama_display = e($s['nama_lengkap']);
                    if ($tinggal_kelas) {
                        $nama_display .= ' <span class="badge-danger"><i class="fas fa-exclamation-circle"></i> Tinggal Kelas</span>';
                    }
                ?>
                <tr>
                    <td style="text-align:center"><input type="checkbox" class="bulk-checkbox" value="<?= (int)$s['id'] ?>"></td>
                    <td><?= e($s['nis']) ?></td>
                    <td><?= e($s['username']) ?></td>
                    <td><?= $nama_display ?></td>
                    <td><?= $s['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                    <td><?= e($s['nama_kelas'] ?: 'Belum ada kelas') ?></td>
                    <td style="text-align:center"><?= $status_badge ?></td>
                    <td class="action-buttons">
                        <button class="btn-sm btn-sm-success" onclick="openEditSiswaModal(<?= $s['id'] ?>, '<?= e($s['nama_lengkap']) ?>', <?= (int)$s['kelas_id'] ?>, '<?= $s['jenis_kelamin'] ?>', <?= $tinggal_kelas ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <a href="?reset_pass_siswa=<?= $s['id'] ?>" class="btn-sm btn-sm-warning" data-confirm="Reset password siswa menjadi 123456?"><i class="fas fa-key"></i> Reset</a>
                        <a href="?hapus_siswa=<?= $s['id'] ?>" class="btn-sm btn-sm-danger" data-confirm="Hapus siswa?"><i class="fas fa-trash"></i> Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit Siswa -->
<div id="editSiswaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Edit Siswa</h3><button class="modal-close" onclick="closeEditSiswaModal()">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="edit_siswa_id">
            <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" id="edit_siswa_nama" class="form-input" required></div>
            <div class="form-group"><label>Jenis Kelamin</label><select name="jenis_kelamin" id="edit_siswa_jk" class="form-select" required><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
            <div class="form-group"><label>Kelas</label><select name="kelas_id" id="edit_siswa_kelas" class="form-select"><?php $kelas->data_seek(0); while($k=$kelas->fetch_assoc()): ?><option value="<?= $k['id'] ?>"><?= e($k['nama_kelas']) ?></option><?php endwhile; ?></select></div>
            
            <?php if ($has_tk_col): ?>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="tinggal_kelas" id="edit_siswa_tk" value="1">
                    <span>Tandai "Tinggal Kelas" (Tidak ikut kenaikan kelas otomatis)</span>
                </label>
            </div>
            <?php endif; ?>

            <div class="btn-group" style="display:flex; gap:8px; justify-content:flex-end;"><button type="submit" name="edit_siswa" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-outline" onclick="closeEditSiswaModal()">Batal</button></div>
        </form>
    </div>
</div>

<script>
function openEditSiswaModal(id, nama, kelas_id, jk, tinggal_kelas = 0) {
    document.getElementById('edit_siswa_id').value = id;
    document.getElementById('edit_siswa_nama').value = nama;
    document.getElementById('edit_siswa_kelas').value = kelas_id;
    document.getElementById('edit_siswa_jk').value = jk;
    var tk_el = document.getElementById('edit_siswa_tk');
    if (tk_el) tk_el.checked = (tinggal_kelas == 1);
    document.getElementById('editSiswaModal').classList.add('active');
}
function closeEditSiswaModal() {
    document.getElementById('editSiswaModal').classList.remove('active');
}
window.onclick = function(e) { if(e.target === document.getElementById('editSiswaModal')) closeEditSiswaModal(); }
</script>

<script>
// Bulk actions UI behavior
var bulkActionEl = document.getElementById('bulk_action');
if (bulkActionEl) {
    bulkActionEl.addEventListener('change', function(){
        var v = this.value;
        var wrap = document.getElementById('bulk_target_wrap');
        if (wrap) wrap.style.display = (v === 'move') ? 'block' : 'none';
        var preview = document.getElementById('bulk_preview_area');
        if (preview) preview.style.display = 'none';
    });
}
function toggleSelectAll(el){
    document.querySelectorAll('.bulk-checkbox').forEach(function(cb){ cb.checked = el.checked; });
}
function getSelectedIds(){
    return Array.from(document.querySelectorAll('.bulk-checkbox:checked')).map(function(cb){ return cb.value; });
}
async function performBulkPreview(){
    var action = document.getElementById('bulk_action').value;
    if (!action) { showToast('Pilih aksi massal','warning'); return; }
    var ids = getSelectedIds();
    if (ids.length === 0) { showToast('Pilih setidaknya satu siswa','warning'); return; }
    var fd = new FormData();
    fd.append('action', action);
    ids.forEach(function(id){ fd.append('student_ids[]', id); });
    fd.append('preview', '1');
    if (action === 'move'){
        var target = document.getElementById('bulk_target_kelas').value;
        if (!target || target === '0') { showToast('Pilih kelas tujuan','warning'); return; }
        fd.append('target_kelas', target);
    }
    var resp = await fetch('ajax/bulk_class_actions.php', { method: 'POST', body: fd });
    var json = await resp.json();
    if (json.success){
        var area = document.getElementById('bulk_preview_area');
        area.innerHTML = json.preview_html || json.message || '';
        area.style.display = 'block';
    } else {
        showToast(json.message || 'Preview gagal','error');
    }
}
async function performBulkExecute(){
    if (!await confirmModal('Yakin ingin mengeksekusi aksi massal?')) return;
    var action = document.getElementById('bulk_action').value;
    if (!action) { showToast('Pilih aksi massal','warning'); return; }
    var ids = getSelectedIds();
    if (ids.length === 0) { showToast('Pilih setidaknya satu siswa','warning'); return; }
    var fd = new FormData();
    fd.append('action', action);
    ids.forEach(function(id){ fd.append('student_ids[]', id); });
    fd.append('preview', '0');
    if (action === 'move'){
        var target = document.getElementById('bulk_target_kelas').value;
        if (!target || target === '0') { showToast('Pilih kelas tujuan','warning'); return; }
        fd.append('target_kelas', target);
    }
    var resp = await fetch('ajax/bulk_class_actions.php', { method: 'POST', body: fd });
    var json = await resp.json();
    if (json.success){
        showToast(json.message || 'Sukses','success');
        window.location.reload();
    } else {
        showToast(json.message || 'Eksekusi gagal','error');
    }
}
</script>

<?php include '../includes/footer.php'; ?>