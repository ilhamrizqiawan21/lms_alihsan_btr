<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$all = isset($_POST['all']) ? (bool)$_POST['all'] : false;

if ($all) {
    $success = tandai_semua_baca($conn, $_SESSION['user_id']);
} else {
    $success = tandai_baca($conn, $id, $_SESSION['user_id']);
}
echo json_encode(['success' => $success]);
?>