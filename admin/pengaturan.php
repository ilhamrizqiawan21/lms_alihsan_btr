<?php
include '../config.php';

$old_tahun    = get_pengaturan($conn, 'tahun_ajaran_aktif') ?: '2025/2026';
$old_semester = get_pengaturan($conn, 'semester_aktif') ?: '1';
// Mode kenaikan: 'auto' atau 'manual'
$old_mode = get_pengaturan($conn, 'mode_kenaikan') ?: 'auto';

// ========== SIMPAN PENGATURAN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    csrf_verify();

    // Whitelist nilai yang diizinkan
    $warna_allowed    = ['hijau', 'biru-azure', 'biru-aqua'];
    $semester_allowed = ['1', '2'];
    $tahun_allowed    = ['2024/2025', '2025/2026', '2026/2027', '2027/2028'];

    $warna    = in_array($_POST['warna_tema'], $warna_allowed)         ? $_POST['warna_tema']    : 'hijau';
    $tahun    = in_array($_POST['tahun_ajaran'], $tahun_allowed)       ? $_POST['tahun_ajaran']  : '2025/2026';
    $semester = in_array($_POST['semester'], $semester_allowed)        ? $_POST['semester']      : '1';
    $mode_allowed = ['auto','manual'];
    $mode = isset($_POST['mode_kenaikan']) && in_array($_POST['mode_kenaikan'], $mode_allowed) ? $_POST['mode_kenaikan'] : 'auto';

    set_pengaturan($conn, 'warna_tema',         $warna);
    set_pengaturan($conn, 'tahun_ajaran_aktif', $tahun);
    set_pengaturan($conn, 'semester_aktif',     $semester);
    set_pengaturan($conn, 'mode_kenaikan',      $mode);
    activate_tahun_ajaran($conn, $tahun);

    if ($old_tahun !== $tahun) {
        // Tahun ajaran berubah: tergantung mode
        if ($mode === 'auto') {
            // Otomatis: kenaikan kelas dan kelulusan
            promote_students_on_year_change($conn);
            clear_academic_cycle_data($conn, $tahun, $semester);
            set_flash('success', 'Pengaturan berhasil disimpan! Siswa kelas IX otomatis lulus (archived), siswa kelas VII & VIII otomatis naik kelas. Data akademik tahun sebelumnya telah diarsipkan dengan aman.');
        } else {
            // Manual: reset kelas siswa dan kosongkan data akademik
            reset_student_classes($conn);
            clear_academic_cycle_data($conn, $tahun, $semester);
            set_flash('success', 'Pengaturan berhasil disimpan! Semua penempatan kelas siswa telah direset untuk tahun ajaran baru. Data akademik tahun sebelumnya telah diarsipkan.');
        }
    } elseif ($old_semester !== $semester) {
        // Hanya semester yang berubah
        clear_academic_cycle_data($conn, $tahun, $semester);
        set_flash('success', 'Pengaturan berhasil disimpan! Sistem sekarang beralih ke semester baru. Data semester sebelumnya tetap tersimpan sebagai histori.');
    } else {
        set_flash('success', 'Pengaturan berhasil disimpan!');
    }

    header('Location: pengaturan');
    exit;
}

// ========== PROSES UBAH USERNAME & PASSWORD ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    csrf_verify();

    $new_username   = trim($_POST['new_username'] ?? '');
    $old_password   = $_POST['old_password'] ?? '';
    $new_password   = $_POST['new_password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    $user_id = $_SESSION['user_id'];
    $errors  = [];

    // Ambil data user saat ini
    $stmt = $conn->prepare("SELECT username, password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $errors[] = "User tidak ditemukan.";
    } else {
        // Validasi password lama (support bcrypt dan legacy md5)
        $password_valid = false;
        if (substr($user['password'], 0, 4) === '$2y$') {
            $password_valid = password_verify($old_password, $user['password']);
        } elseif (strlen($user['password']) == 32 && ctype_xdigit($user['password'])) {
            if (md5($old_password) === $user['password']) {
                $password_valid = true;
                // Upgrade ke bcrypt jika masih MD5
                $new_hash = password_hash($old_password, PASSWORD_BCRYPT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $new_hash, $user_id);
                $upd->execute();
            }
        }

        if (!$password_valid) {
            $errors[] = "Password lama salah.";
        }

        // Validasi username baru
        if (!empty($new_username) && $new_username !== $user['username']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $new_username, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Username sudah digunakan oleh user lain.";
            } else {
                $upd = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $upd->bind_param("si", $new_username, $user_id);
                $upd->execute();
            }
        }

        // Validasi password baru
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $errors[] = "Password baru minimal 6 karakter.";
            } elseif ($new_password !== $confirm) {
                $errors[] = "Konfirmasi password baru tidak cocok.";
            } else {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $new_hash, $user_id);
                $upd->execute();
            }
        }
    }

    if (empty($errors)) {
        set_flash('success', 'Akun berhasil diperbarui.');
    } else {
        set_flash('error', implode('<br>', $errors));
    }
    header('Location: pengaturan');
    exit;
}

cek_login([1]);
$title = 'Pengaturan Sistem';
include '../includes/header.php';

$warna_tema     = get_pengaturan($conn, 'warna_tema')        ?: 'hijau';
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$mode_kenaikan  = get_pengaturan($conn, 'mode_kenaikan') ?: 'auto';
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-cog"></i> Pengaturan Sistem</h2>
    <p class="page-subtitle">Atur konfigurasi umum aplikasi LMS</p>
</div>

<!-- Form Pengaturan Umum -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-sliders-h"></i> Pengaturan Umum</div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
            <label>Warna Tema</label>
            <select name="warna_tema" class="form-select">
                <option value="hijau"      <?= $warna_tema === 'hijau'      ? 'selected' : '' ?>>Hijau (Default)</option>
                <option value="biru-azure" <?= $warna_tema === 'biru-azure' ? 'selected' : '' ?>>Biru Azure</option>
                <option value="biru-aqua"  <?= $warna_tema === 'biru-aqua'  ? 'selected' : '' ?>>Biru Aqua</option>
            </select>
            <small style="color:var(--gray-500);">Pilih warna tema untuk tampilan sidebar dan topbar.</small>
        </div>

        <div class="form-group">
            <label>Tahun Ajaran Aktif</label>
            <select name="tahun_ajaran" class="form-select">
                <?= tahun_ajaran_options($tahun_aktif) ?>
            </select>
            <small style="color:var(--gray-500);">Tahun ajaran yang sedang berjalan.</small>
        </div>

        <div class="form-group">
            <label>Semester Aktif</label>
            <select name="semester" class="form-select">
                <?= semester_options($semester_aktif) ?>
            </select>
            <small style="color:var(--gray-500);">Semester yang sedang berlangsung.</small>
            <small style="display:block; color:var(--primary-700); font-weight:500; margin-top:0.5rem;"><i class="fas fa-info-circle"></i> Sistem menggunakan metode pengarsipan. Perubahan semester atau tahun ajaran akan menyimpan data lama sebagai histori dan memulai lembaran baru tanpa menghapus data sebelumnya.</small>
        </div>

        <div class="form-group">
            <label>Mode Perubahan Tahun Ajaran</label>
            <select name="mode_kenaikan" class="form-select">
                <option value="auto"  <?= $mode_kenaikan === 'auto'  ? 'selected' : '' ?>>Otomatis (naik kelas & lulus)</option>
                <option value="manual"<?= $mode_kenaikan === 'manual'? 'selected' : '' ?>>Manual (reset kelas, tetapkan manual)</option>
            </select>
            <small style="color:var(--gray-500); display:block;">Pilih 'Manual' untuk mereset penempatan kelas saat ganti tahun; admin akan menugaskan kembali siswa ke kelas secara manual.</small>
        </div>

        <div class="btn-group">
            <button type="submit" name="simpan" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<!-- Form Ubah Username & Password -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-user-shield"></i> Ubah Username & Password</div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Username Baru <small style="color:var(--gray-400)">(kosongkan jika tidak ingin mengubah)</small></label>
            <input type="text" name="new_username" class="form-input" placeholder="Username baru">
        </div>
        <div class="form-group">
            <label>Password Lama <span style="color:red">*</span></label>
            <input type="password" name="old_password" class="form-input" required>
        </div>
        <div class="form-group">
            <label>Password Baru <small style="color:var(--gray-400)">(minimal 6 karakter, kosongkan jika tidak ingin mengubah)</small></label>
            <input type="password" name="new_password" class="form-input">
        </div>
        <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <input type="password" name="confirm_password" class="form-input">
        </div>
        <button type="submit" name="update_account" class="btn btn-primary">
            <i class="fas fa-save"></i> Perbarui Akun
        </button>
    </form>
</div>

<!-- Informasi Sistem -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-info-circle"></i> Informasi Sistem</div>
    <table class="modern-table" style="width:auto;">
        <tr><td style="width:200px; font-weight:600;">Nama Aplikasi</td><td>LMS MTs Al-Ihsan Batujajar</td></tr>
        <tr><td style="font-weight:600;">Versi</td><td>1.0 (2025)</td></tr>
        <tr><td style="font-weight:600;">Pengembang</td><td>Ilham Rizqiawan, S.Pd.</td></tr>
        <tr><td style="font-weight:600;">Waktu Server</td><td><?= date('d F Y H:i:s') ?></td></tr>
        <tr><td style="font-weight:600;">PHP</td><td><?= phpversion() ?></td></tr>
        <tr><td style="font-weight:600;">Mode</td><td>
            <?php if (DEV_MODE): ?>
                <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:0.8rem;">
                    <i class="fas fa-tools"></i> Development
                </span>
            <?php else: ?>
                <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:0.8rem;">
                    <i class="fas fa-globe"></i> Production
                </span>
            <?php endif; ?>
        </td></tr>
    </table>
</div>

<?php include '../includes/footer.php'; ?>