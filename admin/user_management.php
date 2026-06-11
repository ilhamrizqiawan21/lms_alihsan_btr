<?php
include '../config.php';
cek_login([1]);

// ========== TAMBAH USER ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);
    $nip_nis  = trim($_POST['nip_nis'] ?? '');

    if (empty($username) || empty($nama)) {
        set_flash('danger', 'Username dan Nama Lengkap harus diisi.');
        header('Location: user_management.php');
        exit;
    }
    if (!in_array($role_id, [2, 4])) {
        set_flash('danger', 'Role tidak valid.');
        header('Location: user_management.php');
        exit;
    }

    // Cek duplikasi username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        set_flash('danger', "Username '$username' sudah terdaftar.");
        header('Location: user_management.php');
        exit;
    }

    $password = password_hash('123456', PASSWORD_BCRYPT);

    if ($nip_nis === '') {
        $stmt2 = $conn->prepare("INSERT INTO users (username, password, role_id, nama_lengkap, nip_nis, is_active) VALUES (?, ?, ?, ?, NULL, 1)");
        $stmt2->bind_param("ssis", $username, $password, $role_id, $nama);
        $stmt2->execute();
    } else {
        $stmt2 = $conn->prepare("INSERT INTO users (username, password, role_id, nama_lengkap, nip_nis, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt2->bind_param("ssiss", $username, $password, $role_id, $nama, $nip_nis);
        $stmt2->execute();
    }

    set_flash('success', "User '$username' berhasil ditambahkan. Password default: 123456");
    header('Location: user_management.php');
    exit;
}

// ========== EDIT USER ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    csrf_verify();

    $id       = (int)$_POST['id'];
    $nama     = trim($_POST['nama']);
    $role_id  = (int)$_POST['role_id'];
    $nip_nis  = trim($_POST['nip_nis']);

    if (empty($nama)) {
        set_flash('danger', 'Nama Lengkap harus diisi.');
        header('Location: user_management.php');
        exit;
    }
    if (!in_array($role_id, [2,4])) {
        set_flash('danger', 'Role tidak valid.');
        header('Location: user_management.php');
        exit;
    }

    // Update nama, role, nip_nis (username tidak diubah)
    if ($nip_nis === '') {
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, role_id = ?, nip_nis = NULL WHERE id = ?");
        $stmt->bind_param("sii", $nama, $role_id, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, role_id = ?, nip_nis = ? WHERE id = ?");
        $stmt->bind_param("sisi", $nama, $role_id, $nip_nis, $id);
    }
    $stmt->execute();

    set_flash('success', 'Data user berhasil diperbarui.');
    header('Location: user_management.php');
    exit;
}

// ========== HAPUS USER ==========
if (isset($_GET['hapus_user'])) {
    $id = (int)$_GET['hapus_user'];

    // Cegah hapus diri sendiri
    if ($id === (int)$_SESSION['user_id']) {
        set_flash('danger', 'Tidak dapat menghapus akun sendiri.');
        header('Location: user_management.php');
        exit;
    }

    $conn->begin_transaction();
    try {
        // Hapus data terkait di tabel lain jika tidak ada ON DELETE CASCADE
        // (Siswa, Guru Mapel, Log Login, Notifikasi, dll)
        $conn->query("DELETE FROM siswa WHERE user_id = $id");
        $conn->query("DELETE FROM guru_mapel WHERE guru_id = $id");
        $conn->query("DELETE FROM log_login WHERE user_id = $id");
        $conn->query("DELETE FROM notifikasi WHERE user_id = $id");
        $conn->query("DELETE FROM dashboard_widgets WHERE user_id = $id");
        $conn->query("DELETE FROM calendar_events WHERE user_id = $id");
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        set_flash('success', 'User dan semua data terkait berhasil dihapus.');
    } catch (Exception $e) {
        $conn->rollback();
        set_flash('danger', 'Gagal menghapus user: ' . $e->getMessage());
    }

    header('Location: user_management.php');
    exit;
}

// ========== RESET PASSWORD ==========
if (isset($_GET['reset_pass'])) {
    $id = (int)$_GET['reset_pass'];
    $new_pass = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_pass, $id);
    $stmt->execute();
    set_flash('success', 'Password direset menjadi 123456.');
    header('Location: user_management.php');
    exit;
}

// ========== TOGGLE STATUS ==========
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $new_status = $row['is_active'] ? 0 : 1;
        $stmt2 = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt2->bind_param("ii", $new_status, $id);
        $stmt2->execute();
        set_flash('success', 'Status user berhasil diubah.');
    } else {
        set_flash('danger', 'User tidak ditemukan.');
    }
    header('Location: user_management.php');
    exit;
}

$title = 'Manajemen Pengguna';
include '../includes/header.php';

// ========== TAMPILKAN DATA ==========
$stmt = $conn->prepare("
    SELECT u.*, r.nama_role 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.role_id IN (2,4) 
    ORDER BY u.role_id, u.nama_lengkap
");
$stmt->execute();
$users = $stmt->get_result();
?>

<style>
.badge-aktif {
    background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px;
}
.badge-tidak-aktif {
    background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px;
}
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.btn-sm {
    padding: 5px 12px;
    font-size: 0.75rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}
.btn-sm i { margin-right: 4px; }
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.btn-warning { background: #f59e0b; color: white; }
.btn-warning:hover { background: #d97706; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
.btn-primary { background: #059669; color: white; }
.btn-primary:hover { background: #047857; }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
/* Modal */
.modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
.modal.active { display: flex; }
.modal-content { background:white; border-radius:1rem; width:90%; max-width:500px; padding:1.5rem; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-close { background:none; border:none; font-size:1.5rem; cursor:pointer; }
@media (max-width:768px) {
    .action-buttons { flex-direction: column; align-items: stretch; }
    .btn-sm { text-align: center; }
    .table-wrapper { overflow-x: auto; }
}
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-users-gear"></i> Manajemen Pengguna</h2>
    <p class="page-subtitle">Kelola akun Guru dan Kepala Sekolah</p>
</div>

<!-- Form Tambah User -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-user-plus"></i> Tambah User Baru</div>
    <form method="POST" class="form-row">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group"><label>Username <span class="text-danger">*</span></label><input type="text" name="username" class="form-input" required placeholder="ilham"></div>
        <div class="form-group"><label>Nama Lengkap <span class="text-danger">*</span></label><input type="text" name="nama" class="form-input" required placeholder="Ilham Rizqiawan"></div>
        <div class="form-group"><label>NIP / NIK <span class="text-muted">(kosongkan jika tidak ada)</span></label><input type="text" name="nip_nis" class="form-input" placeholder="Kosongkan jika tidak ada"></div>
        <div class="form-group"><label>Role</label><select name="role_id" class="form-select"><option value="2">Guru</option><option value="4">Kepala Sekolah</option></select></div>
        <div class="form-group" style="display: flex; align-items: flex-end;"><button type="submit" name="tambah_user" class="btn btn-primary"><i class="fas fa-save"></i> Tambah User</button></div>
    </form>
    <div class="mt-2"><small><i class="fas fa-info-circle"></i> Password default: <strong>123456</strong>. User dapat mengubah password setelah login.</small></div>
</div>

<!-- Daftar User -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-list"></i> Daftar User (Guru & Kepala Sekolah)</div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead><tr><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>NIP/NIK</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php if($users->num_rows > 0): ?>
                    <?php while($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($u['nama_role']) ?></td>
                        <td><?= htmlspecialchars($u['nip_nis'] ?: '-') ?></td>
                        <td><?= $u['is_active'] ? '<span class="badge-aktif"><i class="fas fa-check-circle"></i> Aktif</span>' : '<span class="badge-tidak-aktif"><i class="fas fa-ban"></i> Nonaktif</span>' ?></td>
                        <td class="action-buttons">
                            <button class="btn btn-sm btn-success" onclick="openEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nama_lengkap']) ?>', <?= $u['role_id'] ?>, '<?= htmlspecialchars($u['nip_nis']) ?>')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?reset_pass=<?= $u['id'] ?>" class="btn btn-sm btn-primary" onclick="return confirm('Reset password user <?= htmlspecialchars($u['username']) ?> menjadi 123456?')"><i class="fas fa-key"></i> Reset</a>
                            <a href="?toggle_status=<?= $u['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Ubah status user <?= htmlspecialchars($u['username']) ?>?')"><i class="fas fa-power-off"></i> <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></a>
                            <a href="?hapus_user=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus user <?= htmlspecialchars($u['username']) ?>?')"><i class="fas fa-trash-alt"></i> Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px;">Belum ada data user. Silakan tambah user baru.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Edit User</h3><button class="modal-close" onclick="closeEditModal()">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" id="edit_nama" class="form-input" required></div>
            <div class="form-group"><label>Role</label><select name="role_id" id="edit_role" class="form-select"><option value="2">Guru</option><option value="4">Kepala Sekolah</option></select></div>
            <div class="form-group"><label>NIP / NIK <span class="text-muted">(kosongkan jika tidak ada)</span></label><input type="text" name="nip_nis" id="edit_nip" class="form-input"></div>
            <div class="btn-group" style="display:flex; gap:8px; justify-content:flex-end;"><button type="submit" name="edit_user" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-outline" onclick="closeEditModal()">Batal</button></div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, nama, role_id, nip_nis) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_role').value = role_id;
    document.getElementById('edit_nip').value = (nip_nis === '-' ? '' : nip_nis);
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
window.onclick = function(e) { if(e.target === document.getElementById('editModal')) closeEditModal(); }
</script>

<?php include '../includes/footer.php'; ?>