<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error', 'message'=>'Not logged in']);
    exit;
}

$kelas_mapel_id = (int)$_POST['kelas_mapel_id'];
$message = trim($_POST['message']);
if (empty($message)) {
    echo json_encode(['status'=>'error', 'message'=>'Pesan kosong']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

// Validasi akses
if ($role_id == 2) {
    $check = mysqli_query($conn, "SELECT id FROM kelas_mapel WHERE id=$kelas_mapel_id AND guru_id=$user_id");
    $penerima_role = 3; // siswa
} elseif ($role_id == 3) {
    $siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT kelas_id FROM siswa WHERE user_id=$user_id"));
    $kelas_id = $siswa['kelas_id'];
    $check = mysqli_query($conn, "SELECT id, guru_id FROM kelas_mapel WHERE id=$kelas_mapel_id AND kelas_id=$kelas_id");
    if ($check && mysqli_num_rows($check) > 0) {
        $guru_id = mysqli_fetch_assoc($check)['guru_id'];
        $penerima_id = $guru_id;
        $penerima_role = 2;
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Unauthorized']);
        exit;
    }
} else {
    echo json_encode(['status'=>'error', 'message'=>'Unauthorized']);
    exit;
}

if (!$check || mysqli_num_rows($check) == 0) {
    echo json_encode(['status'=>'error', 'message'=>'Unauthorized']);
    exit;
}

// Simpan pesan
$message = mysqli_real_escape_string($conn, $message);
$query = "INSERT INTO chat_messages (kelas_mapel_id, user_id, message, created_at) VALUES ($kelas_mapel_id, $user_id, '$message', NOW())";
if (mysqli_query($conn, $query)) {
    // Kirim notifikasi ke penerima (guru atau siswa)
    if ($role_id == 2) {
        // Guru mengirim ke semua siswa di kelas ini? Sebaiknya notifikasi ke semua siswa
        $stmt_siswa = $conn->prepare("SELECT user_id FROM siswa WHERE kelas_id = (SELECT kelas_id FROM kelas_mapel WHERE id = ?)");
        $stmt_siswa->bind_param("i", $kelas_mapel_id);
        $stmt_siswa->execute();
        $res_siswa = $stmt_siswa->get_result();
        while ($siswa = $res_siswa->fetch_assoc()) {
            tambah_notifikasi($conn, $siswa['user_id'], 'chat_baru', 'Pesan baru di chat', "Pesan dari guru: " . substr($message, 0, 50), "../" . ($role_id==2?"guru":"siswa") . "/chat.php?kelas_mapel_id=$kelas_mapel_id");
        }
    } else {
        // Siswa mengirim ke guru
        $stmt_guru = $conn->prepare("SELECT guru_id FROM kelas_mapel WHERE id = ?");
        $stmt_guru->bind_param("i", $kelas_mapel_id);
        $stmt_guru->execute();
        $guru = $stmt_guru->get_result()->fetch_assoc();
        if ($guru) {
            tambah_notifikasi($conn, $guru['guru_id'], 'chat_baru', 'Pesan baru dari siswa', "Dari " . $_SESSION['nama'] . ": " . substr($message, 0, 50), "../guru/chat.php?kelas_mapel_id=$kelas_mapel_id");
        }
    }
    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error', 'message'=>mysqli_error($conn)]);
}
?>