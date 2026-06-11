<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['total' => 0, 'notifikasi' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];
$total = notifikasi_belum_dibaca($conn, $user_id);
$notifikasi = [];
$res = ambil_notifikasi($conn, $user_id, 10);
while ($row = $res->fetch_assoc()) {
    $row['created_at_fmt'] = tgl_indonesia($row['created_at']) . ' ' . date('H:i', strtotime($row['created_at']));
    $notifikasi[] = $row;
}
echo json_encode(['total' => $total, 'notifikasi' => $notifikasi]);
?>