<?php
include 'config.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    if ($role_id == 1)      header('Location: ' . $base_url . 'admin/dashboard');
    elseif ($role_id == 2)  header('Location: ' . $base_url . 'guru/dashboard');
    elseif ($role_id == 3)  header('Location: ' . $base_url . 'siswa/dashboard');
    elseif ($role_id == 4)  header('Location: ' . $base_url . 'kepsek/dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    // Check rate limiting first
    $rate_limit = check_rate_limit($conn, $ip_address, $username);
    if (!$rate_limit['allowed']) {
        // Log failed attempt for visibility
        log_login_attempt($conn, $ip_address, $username, 0);
        if (!empty($rate_limit['reason']) && $rate_limit['reason'] === 'ip_blocked') {
            $error = 'Akun Anda terkunci sementara. Coba lagi nanti.';
        } else {
            $error = 'Terlalu banyak percobaan login. Silakan coba lagi nanti.';
        }
        // Stop processing
    }

    // Continue only if no rate-limit error
    if (empty($error)) {

    // ✅ AMAN: Prepared statement, tidak ada SQL Injection
    $stmt = $conn->prepare(
        "SELECT u.*, r.nama_role 
         FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE u.username = ? AND u.is_active = 1 
         LIMIT 1"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stored_hash = $user['password'];
        $password_valid = false;

        // Cek bcrypt (password_hash)
        if (substr($stored_hash, 0, 4) === '$2y$') {
            $password_valid = password_verify($password_input, $stored_hash);
            // ✅ Upgrade MD5 lama ke bcrypt saat login berhasil (tidak ada di sini, sudah bcrypt)
        }
        // Cek MD5 lama — lalu upgrade ke bcrypt otomatis
        elseif (strlen($stored_hash) == 32 && ctype_xdigit($stored_hash)) {
            if (md5($password_input) === $stored_hash) {
                $password_valid = true;
                // ✅ Upgrade otomatis ke bcrypt
                $new_hash = password_hash($password_input, PASSWORD_BCRYPT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $new_hash, $user['id']);
                $upd->execute();
            }
        }

        if ($password_valid) {
            // Regenerasi session ID (cegah session fixation)
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role']    = $user['nama_role'];
            $_SESSION['nama']    = $user['nama_lengkap'];

            // ✅ Log login dengan prepared statement
            $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt_log = $conn->prepare(
                "INSERT INTO log_login (user_id, username, nama_lengkap, role, ip_address, user_agent, login_time) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt_log->bind_param(
                "isssss",
                $user['id'], $user['username'], $user['nama_lengkap'],
                $user['nama_role'], $ip, $user_agent
            );
            $stmt_log->execute();

            // Record successful login attempt and clear blocks for IP
            log_login_attempt($conn, $ip_address, $username, 1);
            $conn->query("DELETE FROM blocked_ips WHERE ip_address = '" . $conn->real_escape_string($ip_address) . "'");

            // Redirect berdasarkan role
            $redirect = [1 => 'admin/dashboard', 2 => 'guru/dashboard', 3 => 'siswa/dashboard', 4 => 'kepsek/dashboard'];
            header('Location: ' . $base_url . ($redirect[$user['role_id']] ?? ''));
            exit;
        } else {
            $error = 'Password salah!';
        }
    } else {
        // Pesan generik — jangan bocorkan username valid/tidak
        $error = 'Username atau password tidak valid!';
    }
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login LMS MTs Al-Ihsan</title>
    <link rel="icon" href="<?= $base_url ?>assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= $base_url ?>style.css">
</head>
<body class="login-body">
<div class="login-container">
    <div class="login-logo">
        <img src="<?= $base_url ?>assets/images/logo-sekolah.png" alt="Logo MTs Al-Ihsan">
        <h2>MTs. Al-Ihsan Batujajar</h2>
        <p>Learning Management System</p>
    </div>
    <?php if ($error): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <i class="fas fa-user"></i>
            <input type="text" name="username" placeholder="Username" required autocomplete="username">
        </div>
        <div class="form-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        </div>
        <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
    </form>
    <div class="login-footer" style="margin-top:1rem; font-size:0.8rem; color:#666;">
        <p>Hubungi administrator jika lupa akun</p>
        <p>Ilham Rizqiawan, S.Pd. &mdash; 0895802329062</p>
    </div>
</div>
</body>
</html>