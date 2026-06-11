<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'message'=>'Login required']);
    exit;
}
$role_id = $_SESSION['role_id'] ?? 0;
if ($role_id != 1) {
    echo json_encode(['success'=>false, 'message'=>'Akses ditolak. Hanya admin.']);
    exit;
}

$action = $_POST['action'] ?? '';
$student_ids = $_POST['student_ids'] ?? [];
$preview = isset($_POST['preview']) ? (int)$_POST['preview'] : 1;

if (!in_array($action, ['move','delete'])) {
    echo json_encode(['success'=>false, 'message'=>'Aksi tidak dikenal']);
    exit;
}

if (!is_array($student_ids) || count($student_ids) === 0) {
    echo json_encode(['success'=>false, 'message'=>'Tidak ada siswa terpilih']);
    exit;
}

$ids = array_map('intval', $student_ids);
$ids_list = implode(',', $ids);

if ($action === 'move') {
    $target = isset($_POST['target_kelas']) ? (int)$_POST['target_kelas'] : 0;
    if (!$target) {
        echo json_encode(['success'=>false, 'message'=>'Target kelas tidak valid']);
        exit;
    }
    if ($preview) {
        // Build preview HTML
        $stmt = $conn->prepare("SELECT id, nis, nama_lengkap FROM siswa WHERE id IN ($ids_list)");
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $html = '<p>Jumlah siswa: '.count($rows).'</p><ul>';
        foreach ($rows as $r) $html .= '<li>'.e($r['nis']).' &mdash; '.e($r['nama_lengkap']).'</li>';
        $html .= '</ul><p>Akan dipindahkan ke kelas ID: '.e($target).'</p>';
        echo json_encode(['success'=>true, 'preview_html'=>$html]);
        exit;
    } else {
        // Execute move
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id IN ($ids_list)");
            $stmt->bind_param("i", $target);
            $stmt->execute();
            $conn->commit();
            echo json_encode(['success'=>true, 'message'=>'Siswa berhasil dipindahkan.']);
            exit;
        } catch (Exception $ex) {
            $conn->rollback();
            echo json_encode(['success'=>false, 'message'=>'Gagal memindahkan siswa: '.$ex->getMessage()]);
            exit;
        }
    }
}

if ($action === 'delete') {
    if ($preview) {
        $stmt = $conn->prepare("SELECT id, nis, nama_lengkap, user_id FROM siswa WHERE id IN ($ids_list)");
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $html = '<p>Jumlah siswa yang akan dihapus: '.count($rows).'</p><ul>';
        foreach ($rows as $r) $html .= '<li>'.e($r['nis']).' &mdash; '.e($r['nama_lengkap']).' (user_id: '.e($r['user_id']).')</li>';
        $html .= '</ul><p>Ini akan menghapus entri siswa dan akun pengguna terkait jika ada.</p>';
        echo json_encode(['success'=>true, 'preview_html'=>$html]);
        exit;
    } else {
        $conn->begin_transaction();
        try {
            // Get user_ids to remove
            $stmt = $conn->prepare("SELECT user_id FROM siswa WHERE id IN ($ids_list)");
            $stmt->execute();
            $res = $stmt->get_result();
            $user_ids = [];
            while ($r = $res->fetch_assoc()) {
                if (!empty($r['user_id'])) $user_ids[] = (int)$r['user_id'];
            }
            // Delete siswa rows
            $stmt = $conn->prepare("DELETE FROM siswa WHERE id IN ($ids_list)");
            $stmt->execute();
            // Delete users rows if present
            if (!empty($user_ids)) {
                $uids_list = implode(',', array_map('intval', $user_ids));
                $conn->query("DELETE FROM users WHERE id IN ($uids_list)");
            }
            $conn->commit();
            echo json_encode(['success'=>true, 'message'=>'Siswa berhasil dihapus.']);
            exit;
        } catch (Exception $ex) {
            $conn->rollback();
            echo json_encode(['success'=>false, 'message'=>'Gagal menghapus siswa: '.$ex->getMessage()]);
            exit;
        }
    }
}

