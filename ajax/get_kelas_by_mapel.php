<?php
include '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    echo json_encode([]);
    exit;
}

$guru_id = $_SESSION['user_id'];
$mapel_id = (int)$_GET['mapel_id'];
$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

$query = "SELECT km.id as kelas_mapel_id, k.nama_kelas 
          FROM kelas_mapel km 
          JOIN kelas k ON km.kelas_id = k.id 
          WHERE km.mapel_id = $mapel_id 
          AND km.guru_id = $guru_id 
          AND km.tahun_ajaran_id = (SELECT id FROM tahun_ajaran WHERE tahun='$tahun_aktif' AND is_active=1)
          AND km.semester = '$semester_aktif'
          ORDER BY k.nama_kelas";
$result = mysqli_query($conn, $query);
if (!$result) {
    echo json_encode([]);
    exit;
}
$data = [];
while($row = mysqli_fetch_assoc($result)) {
    $data[] = ['kelas_mapel_id' => $row['kelas_mapel_id'], 'nama_kelas' => $row['nama_kelas']];
}
echo json_encode($data);
?>