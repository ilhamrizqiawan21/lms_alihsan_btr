<?php
include '../config.php';
cek_login([2]);
$title = 'Profil Guru';
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nip_nis = trim($_POST['nip_nis'] ?? '');
    $password_baru = $_POST['password_baru'] ?? '';

    if (empty($username) || empty($nama)) {
        set_flash('danger', 'Username dan Nama Lengkap harus diisi.');
        header('Location: profil.php');
        exit;
    }

    // Cek duplikasi username (kecuali untuk user sendiri)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        set_flash('danger', "Username '$username' sudah digunakan oleh user lain.");
        header('Location: profil.php');
        exit;
    }

    // Mulai update
    $update = "UPDATE users SET username = ?, nama_lengkap = ?, nip_nis = ?";
    $params = [$username, $nama, $nip_nis];
    $types = "sss";

    if (!empty($password_baru)) {
        $new_hash = password_hash($password_baru, PASSWORD_BCRYPT);
        $update .= ", password = ?";
        $params[] = $new_hash;
        $types .= "s";
    }
    $update .= " WHERE id = ?";
    $params[] = $user_id;
    $types .= "i";

    $stmt = $conn->prepare($update);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $_SESSION['nama'] = $nama; // update session nama
        set_flash('success', 'Profil berhasil diperbarui.');
    } else {
        set_flash('danger', 'Gagal memperbarui profil: ' . $conn->error);
    }
    header('Location: profil.php');
    exit;
}

// Ambil data user
$stmt = $conn->prepare("SELECT username, nama_lengkap, nip_nis FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    die("User tidak ditemukan.");
}
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-user-circle"></i> Profil Guru</h2>
    <p class="page-subtitle">Ubah data akun Anda</p>
</div>

<?= show_flash(); ?>

<div class="form-container">
    <div class="form-title">Edit Profil</div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" class="form-input" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
        </div>
        <div class="form-group">
            <label>NIP / NIK</label>
            <input type="text" name="nip_nis" class="form-input" value="<?= htmlspecialchars($user['nip_nis'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password Baru <span class="text-muted">(kosongkan jika tidak ingin mengubah)</span></label>
            <input type="password" name="password_baru" class="form-input">
        </div>
        <div class="btn-group">
            <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            <a href="dashboard.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>