<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['messages'=>[]]);
    exit;
}

$kelas_mapel_id = (int)$_GET['kelas_mapel_id'];
$last_id = (int)$_GET['last_id'];

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

if ($role_id == 2) {
    $check = mysqli_query($conn, "SELECT id FROM kelas_mapel WHERE id=$kelas_mapel_id AND guru_id=$user_id");
} elseif ($role_id == 3) {
    $siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT kelas_id FROM siswa WHERE user_id=$user_id"));
    $kelas_id = $siswa['kelas_id'];
    $check = mysqli_query($conn, "SELECT id FROM kelas_mapel WHERE id=$kelas_mapel_id AND kelas_id=$kelas_id");
} else {
    echo json_encode(['messages'=>[]]);
    exit;
}

if (!$check || mysqli_num_rows($check) == 0) {
    echo json_encode(['messages'=>[]]);
    exit;
}

$query = "SELECT cm.id, cm.user_id, cm.message, cm.created_at, u.nama_lengkap as nama 
          FROM chat_messages cm
          JOIN users u ON cm.user_id = u.id
          WHERE cm.kelas_mapel_id = $kelas_mapel_id AND cm.id > $last_id
          ORDER BY cm.created_at ASC";
$res = mysqli_query($conn, $query);
$messages = [];
while($row = mysqli_fetch_assoc($res)) {
    $messages[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'nama' => $row['nama'],
        'message' => $row['message'],
        'created_at' => $row['created_at'], // full datetime
        'time' => date('H:i', strtotime($row['created_at']))
    ];
}
echo json_encode(['messages'=>$messages]);
?>