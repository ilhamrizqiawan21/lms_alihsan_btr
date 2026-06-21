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

// Auto-create calendar_events table if not exists (in case migration not run)
$conn->query("CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    is_holiday TINYINT(1) NOT NULL DEFAULT 0,
    scope VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Helper: check if user can manage this event
function can_manage_event($conn, $event_id, $user_id, $role_id) {
    // Siswa (3) tidak boleh manage event
    if ($role_id == 3) return false;

    // Admin (1) and Kepsek (4) can manage any event
    if (in_array($role_id, [1, 4])) return true;
    
    // Other users can only manage their own events
    $stmt = $conn->prepare("SELECT user_id, scope FROM calendar_events WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    if (!$event) return false;
    return (int)$event['user_id'] === $user_id;
}

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

        // Siswa tidak boleh menambah/mengubah event
        if ($role_id == 3) {
            echo json_encode(['success' => false, 'message' => 'Siswa tidak memiliki izin untuk menambah atau mengubah event.']);
            exit;
        }

        $is_holiday = isset($_POST['is_holiday']) ? (int)$_POST['is_holiday'] : 0;
        $scope = isset($_POST['scope']) && $_POST['scope'] === 'school' ? 'school' : 'user';

        // Only Admin/Kepsek can set scope=school
        if (!in_array($role_id, [1,4]) && $scope === 'school') {
            $scope = 'user';
        }

        // If editing, check ownership
        if ($event_id && !can_manage_event($conn, $event_id, $user_id, $role_id)) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengubah event ini.']);
            exit;
        }

        // Only Admin/Kepsek can mark holiday
        if (!in_array($role_id, [1,4])) {
            $is_holiday = 0;
        }

        $GLOBALS['__cal_error'] = '';
        $success = save_calendar_event($conn, $user_id, $event_date, $title, $description, $event_id, $is_holiday, $scope);
        $debug = $success ? '' : ' [error=' . ($GLOBALS['__cal_error'] ?: $conn->error ?: 'unknown') . ']';
        echo json_encode(['success' => $success, 'message' => $success ? 'Event berhasil disimpan.' : ('Gagal menyimpan event.' . $debug)]);
        break;

    case 'delete':
        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        if (!$event_id) {
            echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
            exit;
        }

        // Siswa tidak boleh menghapus event
        if ($role_id == 3) {
            echo json_encode(['success' => false, 'message' => 'Siswa tidak memiliki izin untuk menghapus event.']);
            exit;
        }

        // Check ownership for non-admin/kepsek
        if (!can_manage_event($conn, $event_id, $user_id, $role_id)) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus event ini.']);
            exit;
        }

        $success = delete_calendar_event($conn, $user_id, $event_id);
        echo json_encode(['success' => $success, 'message' => $success ? 'Event dihapus.' : 'Gagal menghapus event.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
}
