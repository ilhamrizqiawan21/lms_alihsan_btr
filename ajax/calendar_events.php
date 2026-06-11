<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 0;
$action = $_REQUEST['action'] ?? 'get';

switch ($action) {
    case 'get':
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $events = get_user_calendar_events($conn, $user_id, $year, $month);
        echo json_encode(['success' => true, 'events' => $events]);
        break;

    case 'save':
        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $event_date = $_POST['event_date'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$event_date || !$title) {
            echo json_encode(['success' => false, 'message' => 'Tanggal dan judul wajib diisi.']);
            exit;
        }

        // Hanya Admin (1) dan Kepala Sekolah (4) yang boleh menambah / mengubah event
        if (!in_array($role_id, [1,4])) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menambah atau mengubah event.']);
            exit;
        }

        $is_holiday = isset($_POST['is_holiday']) ? (int)$_POST['is_holiday'] : 0;
        $scope = isset($_POST['scope']) && $_POST['scope'] === 'school' ? 'school' : 'user';

        // Only allow setting scope=school for Admin/Kepsek (we're already inside that check)
        $success = save_calendar_event($conn, $user_id, $event_date, $title, $description, $event_id, $is_holiday, $scope);
        echo json_encode(['success' => $success, 'message' => $success ? 'Event berhasil disimpan.' : 'Gagal menyimpan event.']);
        break;

    case 'delete':
        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        if (!$event_id) {
            echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
            exit;
        }
        // Hanya Admin (1) dan Kepala Sekolah (4) yang boleh menghapus event
        if (!in_array($role_id, [1,4])) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus event.']);
            exit;
        }

        $success = delete_calendar_event($conn, $user_id, $event_id);
        echo json_encode(['success' => $success, 'message' => $success ? 'Event dihapus.' : 'Gagal menghapus event.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
}
