<?php
// ============================================
// FUNGSI GLOBAL UNTUK LMS MTs AL-IHSAN
// Versi Aman: Prepared Statements, XSS Protection
// ============================================

// ---- HELPER: Bersihkan output dari XSS ----
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Format tanggal Indonesia (contoh: 1 Januari 2025)
function tgl_indonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('j', strtotime($tanggal));
    $bln = $bulan[(int)date('n', strtotime($tanggal))];
    $thn = date('Y', strtotime($tanggal));
    return "$tgl $bln $thn";
}

// Fungsi untuk cek login multi-role (menerima array role_id)
function cek_login($allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $GLOBALS['base_url'] . 'index.php');
        exit;
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['role_id'], $allowed_roles)) {
        header('Location: ' . $GLOBALS['base_url'] . 'index.php');
        exit;
    }
}

// ---- AMAN: Ambil nilai pengaturan (prepared statement) ----
function get_pengaturan($conn, $key) {
    $stmt = $conn->prepare("SELECT value FROM pengaturan WHERE `key` = ?");
    if (!$stmt) return null;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['value'];
    }
    return null;
}

// ---- AMAN: Simpan pengaturan (prepared statement) ----
function set_pengaturan($conn, $key, $value) {
    $stmt = $conn->prepare(
        "INSERT INTO pengaturan (`key`, value) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE value = ?"
    );
    if (!$stmt) return false;
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

// Tahun ajaran aktif
function get_tahun_ajaran_aktif($conn) {
    return get_pengaturan($conn, 'tahun_ajaran_aktif') ?: '2025/2026';
}

// Semester aktif
function get_semester_aktif($conn) {
    return get_pengaturan($conn, 'semester_aktif') ?: '1';
}

// Badge status absensi
function status_badge($status) {
    $allowed = ['hadir', 'sakit', 'izin', 'alpha'];
    if (!in_array($status, $allowed)) {
        return '<span class="badge-secondary">-</span>';
    }
    switch ($status) {
        case 'hadir':  return '<span class="badge-hadir"><i class="fas fa-check-circle"></i> Hadir</span>';
        case 'sakit':  return '<span class="badge-sakit"><i class="fas fa-thermometer-half"></i> Sakit</span>';
        case 'izin':   return '<span class="badge-izin"><i class="fas fa-envelope-open-text"></i> Izin</span>';
        case 'alpha':  return '<span class="badge-alpha"><i class="fas fa-times-circle"></i> Alpha</span>';
    }
}

// Konversi nilai angka (1-5) ke predikat sikap
function predikat_sikap($nilai) {
    if (is_null($nilai) || $nilai === '') return '-';
    $nilai = (int)$nilai;
    $map = [5 => 'SB (Sangat Baik)', 4 => 'B (Baik)', 3 => 'C (Cukup)', 2 => 'KB (Kurang Baik)', 1 => 'TB (Tidak Baik)'];
    return $map[$nilai] ?? '-';
}

// Generate pilihan semester untuk dropdown
function semester_options($selected = null) {
    $options = [1 => 'Semester 1', 2 => 'Semester 2'];
    $html = '';
    foreach ($options as $val => $label) {
        $sel = ((string)$selected === (string)$val) ? 'selected' : '';
        $html .= "<option value=\"" . e($val) . "\" $sel>" . e($label) . "</option>";
    }
    return $html;
}

// Generate pilihan tahun ajaran
function tahun_ajaran_options($selected = null) {
    $tahun_list = ['2024/2025', '2025/2026', '2026/2027', '2027/2028'];
    $html = '';
    foreach ($tahun_list as $ta) {
        $sel = ($selected == $ta) ? 'selected' : '';
        $html .= "<option value=\"" . e($ta) . "\" $sel>" . e($ta) . "</option>";
    }
    return $html;
}

// ---- AMAN: Aktifkan tahun ajaran pada tabel tahun_ajaran ----
function activate_tahun_ajaran($conn, $tahun) {
    if (!in_array($tahun, ['2024/2025', '2025/2026', '2026/2027', '2027/2028'])) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO tahun_ajaran (tahun, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $tahun);
    $stmt->execute();
    if ($stmt->errno !== 0) {
        return false;
    }

    $stmt2 = $conn->prepare("UPDATE tahun_ajaran SET is_active = 0 WHERE tahun != ?");
    if ($stmt2) {
        $stmt2->bind_param("s", $tahun);
        $stmt2->execute();
        if ($stmt2->errno !== 0) {
            return false;
        }
    }

    return true;
}

// ---- AMAN: Hapus data akademik untuk tahun ajaran dan semester aktif baru ----
// ---- AMAN: Hapus data akademik untuk tahun ajaran dan semester aktif baru ----
// REFACTORED: Sekarang tidak lagi menghapus data (Archive & Reference)
function clear_academic_cycle_data($conn, $tahun_ajaran, $semester) {
    // Kita biarkan fungsi ini tetap ada agar tidak mematahkan kode yang memanggilnya,
    // namun kita nonaktifkan perintah DELETE agar histori data terjaga.
    return true; 
}

// ---- BARU: Otomatis kenaikan kelas dan kelulusan saat tahun ajaran berganti ----
function promote_students_on_year_change($conn) {
    // Step 1: Siswa kelas IX → status lulus (di-archive)
    // Hanya siswa yang 'aktif' dan tidak ditandai 'tinggal_kelas' (jika kolom ada)
    $stmt1 = $conn->prepare("
        UPDATE siswa SET status = 'lulus'
        WHERE kelas_id IN (SELECT id FROM kelas WHERE tingkat = 'IX')
        AND status = 'aktif'
    ");
    if (!$stmt1) return false;
    $stmt1->execute();
    if ($stmt1->errno !== 0) return false;

    // Step 2: Siswa kelas VIII → pindah ke kelas IX (VIII-A → IX-A, dst)
    $stmt_viii = $conn->prepare("
        SELECT s.id, k.nama_kelas
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE k.tingkat = 'VIII' AND s.status = 'aktif'
        AND (s.tinggal_kelas IS NULL OR s.tinggal_kelas = 0)
    ");
    // Note: Jika kolom tinggal_kelas belum ada, query ini mungkin error. 
    // Saya akan menggunakan query yang lebih aman atau memastikan kolom ada.
    
    // Versi Aman tanpa asumsi kolom tinggal_kelas ada (fallback)
    $check_col = $conn->query("SHOW COLUMNS FROM `siswa` LIKE 'tinggal_kelas'");
    $has_tinggal_kelas = ($check_col->num_rows > 0);

    $sql_viii = "SELECT s.id, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE k.tingkat = 'VIII' AND s.status = 'aktif'";
    if ($has_tinggal_kelas) $sql_viii .= " AND s.tinggal_kelas = 0";
    
    $result_viii = $conn->query($sql_viii);

    while ($row = $result_viii->fetch_assoc()) {
        $nama_bagian = substr($row['nama_kelas'], -1);
        $new_class = 'IX-' . $nama_bagian;

        $stmt_find = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
        if (!$stmt_find) continue;
        $stmt_find->bind_param("s", $new_class);
        $stmt_find->execute();
        $res_find = $stmt_find->get_result()->fetch_assoc();
        $new_kelas_id = $res_find['id'] ?? null;

        if ($new_kelas_id) {
            $stmt_upd = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
            if ($stmt_upd) {
                $stmt_upd->bind_param("ii", $new_kelas_id, $row['id']);
                $stmt_upd->execute();
            }
        }
    }

    // Step 3: Siswa kelas VII → pindah ke kelas VIII (VII-A → VIII-A, dst)
    $sql_vii = "SELECT s.id, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE k.tingkat = 'VII' AND s.status = 'aktif'";
    if ($has_tinggal_kelas) $sql_vii .= " AND s.tinggal_kelas = 0";
    
    $result_vii = $conn->query($sql_vii);

    while ($row = $result_vii->fetch_assoc()) {
        $nama_bagian = substr($row['nama_kelas'], -1);
        $new_class = 'VIII-' . $nama_bagian;

        $stmt_find = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
        if (!$stmt_find) continue;
        $stmt_find->bind_param("s", $new_class);
        $stmt_find->execute();
        $res_find = $stmt_find->get_result()->fetch_assoc();
        $new_kelas_id = $res_find['id'] ?? null;

        if ($new_kelas_id) {
            $stmt_upd = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
            if ($stmt_upd) {
                $stmt_upd->bind_param("ii", $new_kelas_id, $row['id']);
                $stmt_upd->execute();
            }
        }
    }

    // Step 4: Reset semua bendera 'tinggal_kelas' untuk tahun depan
    if ($has_tinggal_kelas) {
        $conn->query("UPDATE siswa SET tinggal_kelas = 0");
    }

    return true;
}

// ---- BARU: Reset kelas semua siswa (set NULL) saat mode manual dipilih ----
function reset_student_classes($conn) {
    $stmt = $conn->prepare("UPDATE siswa SET kelas_id = NULL WHERE status = 'aktif'");
    if (!$stmt) return false;
    $stmt->execute();
    return $stmt->errno === 0;
}

// ---- BARU: Generate CSRF Token ----
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ---- BARU: Validasi CSRF Token ----
function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Permintaan tidak valid. Silakan refresh halaman.');
    }
}

// ---- Flash message (pesan sementara antar halaman) ----
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function show_flash(): void {
    if (!isset($_SESSION['flash'])) return;

    $type    = $_SESSION['flash']['type'];
    $message = htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['flash']);

    $icons = [
        'success' => 'fa-check-circle',
        'error'   => 'fa-times-circle',
        'warning' => 'fa-exclamation-triangle',
        'info'    => 'fa-info-circle',
        'danger'  => 'fa-times-circle',
    ];

    $css_type = $type === 'danger' ? 'error' : $type;
    $icon = $icons[$type] ?? $icons['info'];

    echo '
    <div class="flash-message flash-' . e($css_type) . '">
        <i class="fas ' . $icon . '"></i>
        <span>' . $message . '</span>
    </div>';
}

// ---- NOTIFIKASI ----
function tambah_notifikasi($conn, $user_id, $tipe, $judul, $pesan, $link = null) {
    $stmt = $conn->prepare("INSERT INTO notifikasi (user_id, tipe, judul, pesan, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $tipe, $judul, $pesan, $link);
    return $stmt->execute();
}

function notifikasi_belum_dibaca($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifikasi WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

function ambil_notifikasi($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("SELECT * FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function tandai_baca($conn, $id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifikasi SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    return $stmt->execute();
}

function tandai_semua_baca($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifikasi SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// ---- RATE LIMITING & LOGIN ATTEMPT TRACKING ----
function is_ip_whitelisted($conn, $ip_address) {
    $list = get_pengaturan($conn, 'whitelist_ips');
    if (empty($list)) return false;
    $items = array_map('trim', explode(',', $list));
    return in_array($ip_address, $items);
}

function check_rate_limit($conn, $ip_address, $username = '') {
    // If IP is whitelisted via pengaturan, allow
    if (is_ip_whitelisted($conn, $ip_address)) {
        return ['allowed' => true];
    }

    // Check if IP is currently blocked
    $stmt = $conn->prepare(
        "SELECT id, blocked_until FROM blocked_ips WHERE ip_address = ? AND blocked_until > NOW() LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $ip_address);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return ['allowed' => false, 'reason' => 'ip_blocked', 'blocked_until' => $row['blocked_until']];
        }
    }

    // Count failed attempts in last 15 minutes
    $stmt2 = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    if ($stmt2) {
        $stmt2->bind_param('s', $ip_address);
        $stmt2->execute();
        $cnt = $stmt2->get_result()->fetch_assoc()['cnt'] ?? 0;
        $failed_attempts = (int)$cnt;

        if ($failed_attempts >= 5) {
            // Block IP for 30 minutes (or update existing record)
            $blocked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $ins = $conn->prepare(
                "INSERT INTO blocked_ips (ip_address, blocked_until, reason) VALUES (?, ?, ?)"
            );
            if ($ins) {
                $reason = 'Too many failed login attempts';
                $ins->bind_param('sss', $ip_address, $blocked_until, $reason);
                $ins->execute();
            }
            return ['allowed' => false, 'reason' => 'too_many_attempts', 'blocked_until' => $blocked_until];
        }
    }

    return ['allowed' => true];
}

function log_login_attempt($conn, $ip_address, $username, $success = 0) {
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ssi', $ip_address, $username, $success);
    return $stmt->execute();
}

function get_blocked_ips($conn) {
    $stmt = $conn->prepare("SELECT * FROM blocked_ips ORDER BY created_at DESC");
    if (!$stmt) return false;
    $stmt->execute();
    return $stmt->get_result();
}

function unblock_ip($conn, $ip_address) {
    $stmt = $conn->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $ip_address);
    return $stmt->execute();
}

// ---- DASHBOARD WIDGET MANAGEMENT ----
/**
 * Get all widgets for a user, sorted by order
 * @return array
 */
function get_user_widgets($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT widget_key, is_visible, widget_order, is_pinned 
         FROM dashboard_widgets 
         WHERE user_id = ? 
         ORDER BY is_pinned DESC, widget_order ASC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $widgets = [];
    while ($row = $result->fetch_assoc()) {
        $widgets[$row['widget_key']] = $row;
    }
    return $widgets;
}

/**
 * Toggle widget visibility for a user
 */
function toggle_widget_visibility($conn, $user_id, $widget_key, $is_visible) {
    $stmt = $conn->prepare(
        "UPDATE dashboard_widgets 
         SET is_visible = ? 
         WHERE user_id = ? AND widget_key = ?"
    );
    $is_visible = $is_visible ? 1 : 0;
    $stmt->bind_param("iis", $is_visible, $user_id, $widget_key);
    return $stmt->execute();
}

/**
 * Toggle widget pin status
 */
function toggle_widget_pin($conn, $user_id, $widget_key, $is_pinned) {
    $stmt = $conn->prepare(
        "UPDATE dashboard_widgets 
         SET is_pinned = ? 
         WHERE user_id = ? AND widget_key = ?"
    );
    $is_pinned = $is_pinned ? 1 : 0;
    $stmt->bind_param("iis", $is_pinned, $user_id, $widget_key);
    return $stmt->execute();
}

/**
 * Reorder widgets
 */
function reorder_widget($conn, $user_id, $widget_key, $order) {
    $stmt = $conn->prepare(
        "UPDATE dashboard_widgets 
         SET widget_order = ? 
         WHERE user_id = ? AND widget_key = ?"
    );
    $order = (int)$order;
    $stmt->bind_param("iis", $order, $user_id, $widget_key);
    return $stmt->execute();
}

/**
 * Reset user widget preferences to defaults
 */
function reset_dashboard_widgets($conn, $user_id) {
    // Delete existing preferences
    $stmt = $conn->prepare("DELETE FROM dashboard_widgets WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Get user role to set default widgets
    $user_stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $role_id = $user['role_id'] ?? 1;
    
    // Default widget configs per role
    $defaults = [
        1 => ['stat_cards', 'login_history', 'pengumuman', 'attendance_chart'], // admin
        2 => ['class_overview', 'recent_tasks', 'pengumuman', 'performance_chart'], // guru
        3 => ['my_assignments', 'schedule', 'pengumuman', 'my_grades'], // siswa
        4 => ['quick_stats', 'recent_reports', 'pengumuman', 'performance_summary'], // kepsek
    ];
    
    $widgets = $defaults[$role_id] ?? $defaults[1];
    
    // Insert default widgets
    $insert = $conn->prepare(
        "INSERT INTO dashboard_widgets (user_id, widget_key, is_visible, widget_order, is_pinned) 
         VALUES (?, ?, 1, ?, 0)"
    );
    
    foreach ($widgets as $order => $widget_key) {
        $order_int = $order + 1;
        $insert->bind_param("isi", $user_id, $widget_key, $order_int);
        $insert->execute();
    }
    
    return true;
}

/**
 * Get the active academic year ID.
 */
function get_tahun_ajaran_id_aktif($conn) {
    $stmt = $conn->prepare("SELECT id FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
    if (!$stmt) return null;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['id'] ?? null;
}

/**
 * Get student's overall GPA for the active academic year.
 */
function get_student_gpa($conn, $student_id, $tahun_ajaran_id = null, $semester = null) {
    if (!$tahun_ajaran_id) {
        $tahun_ajaran_id = get_tahun_ajaran_id_aktif($conn);
    }
    if (!$semester) {
        $semester = get_semester_aktif($conn);
    }
    if (!$tahun_ajaran_id) return 0;

    $stmt = $conn->prepare(
        "SELECT AVG(na.rata_akhir) as gpa 
         FROM nilai_akhir na
         WHERE na.siswa_id = ? AND na.tahun_ajaran_id = ? AND na.semester = ?
         AND na.rata_akhir IS NOT NULL"
    );
    if (!$stmt) return 0;
    $stmt->bind_param("iis", $student_id, $tahun_ajaran_id, $semester);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return round($result['gpa'] ?? 0, 2);
}

/**
 * Get student's attendance summary for a given month.
 */
function get_student_attendance_summary($conn, $student_id, $periode = null) {
    if (!$periode) {
        $periode = date('Y-m');
    }

    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) as total 
         FROM absensi 
         WHERE siswa_id = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? 
         GROUP BY status"
    );
    $stmt->bind_param("is", $student_id, $periode);
    $stmt->execute();
    $result = $stmt->get_result();

    $summary = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0, 'total' => 0, 'percent' => 0];
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        if (isset($summary[$status])) {
            $summary[$status] = (int)$row['total'];
            $summary['total'] += (int)$row['total'];
        }
    }
    if ($summary['total'] > 0) {
        $summary['percent'] = round(($summary['hadir'] / $summary['total']) * 100, 1);
    }
    return $summary;
}

/**
 * Get student's task completion rate for the active academic year and semester.
 */
function get_student_assignment_rate($conn, $student_id, $tahun_ajaran_id = null, $semester = null) {
    if (!$tahun_ajaran_id) {
        $tahun_ajaran_id = get_tahun_ajaran_id_aktif($conn);
    }
    if (!$semester) {
        $semester = get_semester_aktif($conn);
    }
    if (!$tahun_ajaran_id) return 0;

    $stmt = $conn->prepare("SELECT kelas_id FROM siswa WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $kelas = $stmt->get_result()->fetch_assoc();
    if (!$kelas || !$kelas['kelas_id']) return 0;

    $kelas_id = (int)$kelas['kelas_id'];
    $stmt_total = $conn->prepare(
        "SELECT COUNT(*) as total 
         FROM tugas t 
         JOIN kelas_mapel km ON t.kelas_mapel_id = km.id 
         WHERE km.kelas_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?"
    );
    $stmt_total->bind_param("iis", $kelas_id, $tahun_ajaran_id, $semester);
    $stmt_total->execute();
    $total = (int)$stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
    if ($total === 0) return 100;

    $stmt_done = $conn->prepare(
        "SELECT COUNT(*) as total 
         FROM pengumpulan_tugas pt 
         JOIN tugas t ON pt.tugas_id = t.id 
         JOIN kelas_mapel km ON t.kelas_mapel_id = km.id 
         WHERE pt.siswa_id = ? AND km.kelas_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ? AND pt.status = 'sudah'"
    );
    $stmt_done->bind_param("iiis", $student_id, $kelas_id, $tahun_ajaran_id, $semester);
    $stmt_done->execute();
    $done = (int)$stmt_done->get_result()->fetch_assoc()['total'] ?? 0;

    return round(($done / $total) * 100, 1);
}

/**
 * Get student's subject-wise average scores for the active academic year.
 */
function get_student_subject_scores($conn, $student_id, $tahun_ajaran_id = null, $semester = null) {
    if (!$tahun_ajaran_id) {
        $tahun_ajaran_id = get_tahun_ajaran_id_aktif($conn);
    }
    if (!$semester) {
        $semester = get_semester_aktif($conn);
    }
    if (!$tahun_ajaran_id) return [];

    $stmt = $conn->prepare(
        "SELECT mp.nama_mapel, na.rata_akhir as avg_score,
                (CASE WHEN na.sum1 IS NOT NULL THEN 1 ELSE 0 END +
                 CASE WHEN na.sum2 IS NOT NULL THEN 1 ELSE 0 END +
                 CASE WHEN na.sum3 IS NOT NULL THEN 1 ELSE 0 END +
                 CASE WHEN na.sum4 IS NOT NULL THEN 1 ELSE 0 END +
                 CASE WHEN na.sts IS NOT NULL THEN 1 ELSE 0 END +
                 CASE WHEN na.sas IS NOT NULL THEN 1 ELSE 0 END +
                 CASE WHEN na.sat IS NOT NULL THEN 1 ELSE 0 END) as num_grades
         FROM nilai_akhir na
         JOIN kelas_mapel km ON na.kelas_mapel_id = km.id
         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
         WHERE na.siswa_id = ? AND na.tahun_ajaran_id = ? AND na.semester = ?
         AND na.rata_akhir IS NOT NULL
         ORDER BY mp.nama_mapel ASC"
    );
    if (!$stmt) return [];
    $stmt->bind_param("iis", $student_id, $tahun_ajaran_id, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = [
            'nama_mapel' => $row['nama_mapel'],
            'avg_score' => round($row['avg_score'], 2),
            'num_grades' => (int)$row['num_grades']
        ];
    }
    return $subjects;
}

/**
 * Get student's last grade trend for the active academic year.
 */
function get_student_grade_trend($conn, $student_id, $tahun_ajaran_id = null, $limit = 6) {
    if (!$tahun_ajaran_id) {
        $tahun_ajaran_id = get_tahun_ajaran_id_aktif($conn);
    }
    if (!$tahun_ajaran_id) return [];

    $stmt = $conn->prepare(
        "SELECT mp.nama_mapel, pt.nilai, COALESCE(pt.tanggal_kumpul, t.created_at) as grade_date
         FROM pengumpulan_tugas pt
         JOIN tugas t ON pt.tugas_id = t.id
         JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
         WHERE pt.siswa_id = ? AND km.tahun_ajaran_id = ? AND pt.nilai IS NOT NULL
         ORDER BY grade_date DESC
         LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param("iii", $student_id, $tahun_ajaran_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $grades = [];
    while ($row = $result->fetch_assoc()) {
        $grades[] = [
            'subject' => $row['nama_mapel'],
            'score' => (float)$row['nilai'],
            'date' => $row['grade_date']
        ];
    }
    return array_reverse($grades);
}

/**
 * Get calendar events for a user by month and year.
 */
function get_user_calendar_events($conn, $user_id, $year, $month) {
    $stmt = $conn->prepare(
        "SELECT id, title, description, event_date, is_done, is_holiday, scope, user_id 
         FROM calendar_events 
         WHERE (user_id = ? OR scope = 'school') AND YEAR(event_date) = ? AND MONTH(event_date) = ? 
         ORDER BY event_date, created_at"
    );
    if (!$stmt) return [];
    $stmt->bind_param("iii", $user_id, $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => (int)$row['id'], 
            'title' => $row['title'],
            'description' => $row['description'],
            'event_date' => $row['event_date'],
            'is_done' => (int)$row['is_done'],
            'is_holiday' => (int)($row['is_holiday'] ?? 0),
            'scope' => $row['scope'] ?? 'user',
            'owner_id' => isset($row['user_id']) ? (int)$row['user_id'] : null
        ];
    }
    return $events;
}

/**
 * Save or update a calendar event for a user.
 */
function save_calendar_event($conn, $user_id, $event_date, $title, $description, $event_id = null, $is_holiday = 0, $scope = 'user') {
    // Store last error globally for debug
    $GLOBALS['__cal_error'] = '';

    if (!$event_date || !$title) {
        $GLOBALS['__cal_error'] = 'Empty date or title';
        return false;
    }
    if ($event_id) {
        $sql = "UPDATE calendar_events SET title=?, description=?, event_date=?, is_holiday=?, scope=?, updated_at=CURRENT_TIMESTAMP WHERE id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { $GLOBALS['__cal_error'] = 'Prepare UPDATE: '.$conn->error; return false; }
        $stmt->bind_param("sssisi", $title, $description, $event_date, $is_holiday, $scope, $event_id);
    } else {
        if ($scope === 'school') {
            $sql = "INSERT INTO calendar_events (user_id,title,description,event_date,is_holiday,scope) VALUES (NULL,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { $GLOBALS['__cal_error'] = 'Prepare school INSERT: '.$conn->error; return false; }
            $stmt->bind_param("sssis", $title, $description, $event_date, $is_holiday, $scope);
        } else {
            $sql = "INSERT INTO calendar_events (user_id,title,description,event_date,is_holiday,scope) VALUES (?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { $GLOBALS['__cal_error'] = 'Prepare user INSERT: '.$conn->error; return false; }
            $stmt->bind_param("isssis", $user_id, $title, $description, $event_date, $is_holiday, $scope);
        }
    }
    $ok = $stmt->execute();
    if (!$ok) {
        $GLOBALS['__cal_error'] = 'Execute: '.$stmt->error;
    }
    return $ok;
}

/**
 * Delete a calendar event.
 */
function delete_calendar_event($conn, $user_id, $event_id) {
    // Allow deletion by owner or school-wide events (AJAX must check role)
    $stmt = $conn->prepare("DELETE FROM calendar_events WHERE id = ? AND (user_id = ? OR scope = 'school')");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $event_id, $user_id);
    return $stmt->execute();
}

/**
 * Mengambil daftar kelas dan mata pelajaran yang diampu oleh guru tertentu
 * pada tahun ajaran dan semester yang aktif.
 */
function get_kelas_mapel_guru($conn, $guru_id, $tahun_ajaran, $semester) {
    $stmt = $conn->prepare(
        "SELECT km.*, k.nama_kelas, mp.nama_mapel 
         FROM kelas_mapel km
         JOIN kelas k ON km.kelas_id = k.id
         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
         JOIN tahun_ajaran ta ON km.tahun_ajaran_id = ta.id
         WHERE km.guru_id = ? AND ta.tahun = ? AND km.semester = ?"
    );
    if (!$stmt) return false;
    $stmt->bind_param("iss", $guru_id, $tahun_ajaran, $semester);
    $stmt->execute();
    return $stmt->get_result();
}
