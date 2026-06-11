<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session tidak ditemukan, silakan login ulang']);
    exit;
}

if ($_SESSION['role_id'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: bukan guru']);
    exit;
}

if (!isset($_GET['tugas_id']) || !isset($_GET['kelas_mapel_id'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter kurang']);
    exit;
}

$tugas_id = (int)$_GET['tugas_id'];
$kelas_mapel_id = (int)$_GET['kelas_mapel_id'];
$guru_id = $_SESSION['user_id'];

// Validasi tugas milik guru
$check = mysqli_query($conn, "SELECT t.id FROM tugas t 
    JOIN kelas_mapel km ON t.kelas_mapel_id = km.id 
    WHERE t.id = $tugas_id AND km.guru_id = $guru_id");
if (!$check || mysqli_num_rows($check) == 0) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: tugas tidak ditemukan atau bukan milik guru ini']);
    exit;
}

// Ambil kelas_id
$kelas_res = mysqli_query($conn, "SELECT kelas_id FROM kelas_mapel WHERE id=$kelas_mapel_id");
if (!$kelas_res || mysqli_num_rows($kelas_res) == 0) {
    echo json_encode(['success' => false, 'message' => 'Kelas_mapel tidak ditemukan']);
    exit;
}
$kelas_id = mysqli_fetch_assoc($kelas_res)['kelas_id'];

// Ambil siswa
$siswa_query = "SELECT s.id as siswa_id, s.nis, u.nama_lengkap as nama 
                FROM siswa s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.kelas_id = $kelas_id
                ORDER BY u.nama_lengkap";
$siswa_res = mysqli_query($conn, $siswa_query);
if (!$siswa_res) {
    echo json_encode(['success' => false, 'message' => 'Query siswa gagal: ' . mysqli_error($conn)]);
    exit;
}

// Pengumpulan tugas
$pengumpulan = [];
$peng_res = mysqli_query($conn, "SELECT * FROM pengumpulan_tugas WHERE tugas_id=$tugas_id");
if ($peng_res) {
    while($p = mysqli_fetch_assoc($peng_res)) $pengumpulan[$p['siswa_id']] = $p;
}

$siswa_data = [];
while($s = mysqli_fetch_assoc($siswa_res)) {
    $p = $pengumpulan[$s['siswa_id']] ?? null;
    
    $files = [];
    if ($p) {
        $p_id = (int)$p['id'];
        $files_res = mysqli_query($conn, "SELECT file_name, file_path FROM pengumpulan_files WHERE pengumpulan_id = $p_id");
        while ($f = mysqli_fetch_assoc($files_res)) {
            $files[] = $f;
        }
        // Fallback file_upload lama jika pengumpulan_files kosong
        if (empty($files) && !empty($p['file_upload'])) {
            $files[] = ['file_name' => 'File (Legacy)', 'file_path' => $p['file_upload']];
        }
    }

    $siswa_data[] = [
        'nis' => $s['nis'],
        'nama' => $s['nama'],
        'siswa_id' => $s['siswa_id'],
        'status' => $p ? 'sudah' : 'belum',
        'nilai' => $p['nilai'] ?? '',
        'files' => $files,
        'teks_jawaban' => $p['teks_jawaban'] ?? '',
        'catatan' => $p['catatan'] ?? '',
    ];
}
echo json_encode(['success' => true, 'siswa' => $siswa_data]);
?>