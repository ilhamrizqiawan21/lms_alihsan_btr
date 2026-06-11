<?php

// ============================================
// CONFIG - LMS MTs Al-Ihsan Batujajar
// !! GANTI nilai di bawah saat hosting !!
// ============================================

// ---- MODE APLIKASI ----
define('DEV_MODE', true); // Ganti false saat live hosting

if (DEV_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// ---- KEAMANAN SESSION ----
// WAJIB dipanggil SEBELUM session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (!DEV_MODE) {
    ini_set('session.cookie_secure', 1); // Aktif saat HTTPS
}

// ---- START SESSION ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- DATABASE ----
$host = 'localhost';
$user = 'root';             // Hosting: ganti sesuai cPanel
$pass = 'Hash2856@';                 // Hosting: isi password database
$db   = 'lms_alihsan_btr'; // Hosting: biasanya prefix_namadb

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    if (DEV_MODE) {
        die("Koneksi database gagal: " . $conn->connect_error);
    } else {
        die("Sistem sedang dalam gangguan. Silakan coba lagi nanti.");
    }
}
$conn->set_charset('utf8mb4');

// ---- BASE URL ----
// XAMPP        : '/lms_alihsan_btr/'
// Hosting root : '/'
// Subfolder    : '/lms/'
$base_url = DEV_MODE ? '/lms_alihsan_btr/' : '/';

date_default_timezone_set('Asia/Jakarta');

// ---- AUTO ERROR LOGGING TO DATABASE ----
function global_error_handler($errno, $errstr, $errfile, $errline) {
    global $conn;
    if (!(error_reporting() & $errno)) return false;

    $levels = [
        E_ERROR => 'ERROR', E_WARNING => 'WARNING', E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE', E_USER_ERROR => 'USER_ERROR', 
        E_USER_WARNING => 'USER_WARNING', E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT', E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED', E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    $level = $levels[$errno] ?? 'UNKNOWN';
    $url = $_SERVER['REQUEST_URI'] ?? 'CLI';
    
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO system_errors (error_level, message, file, line, url) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssis", $level, $errstr, $errfile, $errline, $url);
            $stmt->execute();
        }
    }
    return false; // Biarkan PHP menangani error secara normal juga
}

function global_exception_handler($exception) {
    global $conn;
    $level = 'EXCEPTION';
    $msg = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $url = $_SERVER['REQUEST_URI'] ?? 'CLI';

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO system_errors (error_level, message, file, line, url) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssis", $level, $msg, $file, $line, $url);
            $stmt->execute();
        }
    }
}

set_error_handler("global_error_handler");
set_exception_handler("global_exception_handler");

require_once __DIR__ . '/includes/fungsi.php';