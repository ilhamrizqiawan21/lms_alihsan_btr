<?php
include '../config.php';
cek_login([3]);
$title   = 'Profil Saya';
$user_id = $_SESSION['user_id'];

// Ambil data user dan siswa
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$stmt_s = $conn->prepare("
    SELECT s.nis, k.nama_kelas
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    WHERE s.user_id = ?
");
$stmt_s->bind_param("i", $user_id);
$stmt_s->execute();
$siswa = $stmt_s->get_result()->fetch_assoc();

// ── Proses ganti password ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi    = $_POST['konfirmasi']    ?? '';

    // Validasi: semua field wajib diisi
    if ($password_lama === '' || $password_baru === '' || $konfirmasi === '') {
        set_flash('error', 'Semua kolom password wajib diisi.');

    // Validasi: password lama harus cocok
    } elseif (md5($password_lama) !== $user['password']) {
        set_flash('error', 'Password lama tidak sesuai.');

    // Validasi: panjang minimal password baru
    } elseif (strlen($password_baru) < 6) {
        set_flash('error', 'Password baru minimal 6 karakter.');

    // Validasi: konfirmasi harus cocok
    } elseif ($password_baru !== $konfirmasi) {
        set_flash('error', 'Konfirmasi password tidak cocok.');

    // Validasi: password baru tidak boleh sama dengan yang lama
    } elseif (md5($password_baru) === $user['password']) {
        set_flash('error', 'Password baru tidak boleh sama dengan password lama.');

    } else {
        $new_pass = md5($password_baru);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $new_pass, $user_id);

        if ($upd->execute()) {
            set_flash('success', 'Password berhasil diubah. Silakan login ulang.');
            session_destroy();
            header('Location: ../index.php');
            exit;
        } else {
            set_flash('error', 'Gagal memperbarui password. Silakan coba lagi.');
        }
    }

    header('Location: profil.php');
    exit;
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-user-circle"></i> Profil Saya</h2>
    <p class="page-subtitle">Informasi akun dan pengaturan keamanan</p>
</div>

<?= show_flash(); ?>

<!-- ── Info Profil ──────────────────────────────────────────────────────────── -->
<div class="form-container">
    <div class="form-title">Informasi Akun</div>
    <div class="form-row">
        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" disabled>
        </div>
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" disabled>
        </div>
        <div class="form-group">
            <label>NIS</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars($siswa['nis'] ?? '-') ?>" disabled>
        </div>
        <div class="form-group">
            <label>Kelas</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars($siswa['nama_kelas'] ?? '-') ?>" disabled>
        </div>
    </div>
</div>

<!-- ── Ganti Password ───────────────────────────────────────────────────────── -->
<div class="form-container">
    <div class="form-title">Ganti Password</div>
    <form method="POST">
        <div class="form-group">
            <label>Password Lama</label>
            <input type="password" name="password_lama" class="form-input"
                   placeholder="Masukkan password saat ini" required>
        </div>
        <div class="form-group">
            <label>Password Baru <small style="color:var(--gray-400)">(minimal 6 karakter)</small></label>
            <input type="password" name="password_baru" class="form-input"
                   placeholder="Password baru" minlength="6" required>
        </div>
        <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <input type="password" name="konfirmasi" class="form-input"
                   placeholder="Ulangi password baru" minlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-key"></i> Ubah Password
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>