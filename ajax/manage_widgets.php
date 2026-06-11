<?php
include '../config.php';

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'toggle_visibility') {
    csrf_verify();
    $widget_key = $_POST['widget_key'] ?? '';
    $is_visible = $_POST['is_visible'] ?? 0;
    
    if (!$widget_key) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Widget key required']));
    }
    
    $success = toggle_widget_visibility($conn, $user_id, $widget_key, $is_visible);
    die(json_encode(['success' => $success]));
}

elseif ($action === 'toggle_pin') {
    csrf_verify();
    $widget_key = $_POST['widget_key'] ?? '';
    $is_pinned = $_POST['is_pinned'] ?? 0;
    
    if (!$widget_key) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Widget key required']));
    }
    
    $success = toggle_widget_pin($conn, $user_id, $widget_key, $is_pinned);
    die(json_encode(['success' => $success]));
}

elseif ($action === 'reorder') {
    csrf_verify();
    $widget_key = $_POST['widget_key'] ?? '';
    $order = $_POST['order'] ?? 0;
    
    if (!$widget_key) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Widget key required']));
    }
    
    $success = reorder_widget($conn, $user_id, $widget_key, $order);
    die(json_encode(['success' => $success]));
}

elseif ($action === 'reset') {
    csrf_verify();
    $success = reset_dashboard_widgets($conn, $user_id);
    die(json_encode(['success' => $success]));
}

http_response_code(400);
die(json_encode(['success' => false, 'message' => 'Invalid action']));
