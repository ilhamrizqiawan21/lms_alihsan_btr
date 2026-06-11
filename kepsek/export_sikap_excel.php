<?php
include '../config.php';

$role_id = $_SESSION['role_id'] ?? 0;
$allowed_roles = [1,2,4];
if (!in_array($role_id, $allowed_roles)) {
    die("Akses ditolak");
}

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$semester = isset($_GET['semester']) ? $_GET['semester'] : get_semester_aktif($conn);
$tahun_ajaran = isset($_GET['tahun']) ? $_GET['tahun'] : get_tahun_ajaran_aktif($conn);

if ($kelas_id == 0) die("Kelas tidak dipilih");

$ta_res = mysqli_query($conn, "SELECT id FROM tahun_ajaran WHERE tahun='$tahun_ajaran' AND is_active=1");
$ta_id = mysqli_fetch_assoc($ta_res)['id'] ?? 0;
$kelas_nama = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=$kelas_id"))['nama_kelas'];

// Ambil kelas_mapel_id pertama untuk kelas ini (asumsi sikap per kelas)
$km_res = mysqli_query($conn, "SELECT id FROM kelas_mapel WHERE kelas_id=$kelas_id LIMIT 1");
$km_id = mysqli_fetch_assoc($km_res)['id'] ?? 0;

$query = "SELECT s.nis, u.nama_lengkap as nama, 
          sp.taqwa, sp.kejujuran, sp.disiplin, sp.sabar, sp.syukur, sp.tawadhu,
          so.empati, so.kerjasama, so.toleransi, so.percaya_diri, so.komunikasi
          FROM siswa s
          JOIN users u ON s.user_id = u.id
          LEFT JOIN sikap_spiritual sp ON sp.siswa_id = s.id AND sp.kelas_mapel_id = $km_id AND sp.tahun_ajaran_id = $ta_id AND sp.semester = '$semester'
          LEFT JOIN sikap_sosial so ON so.siswa_id = s.id AND so.kelas_mapel_id = $km_id AND so.tahun_ajaran_id = $ta_id AND so.semester = '$semester'
          WHERE s.kelas_id = $kelas_id
          ORDER BY u.nama_lengkap";
$result = mysqli_query($conn, $query);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=rekap_sikap_".$kelas_nama."_semester".$semester.".xls");
echo "<table border='1'>";
echo "<tr><th>NIS</th><th>Nama</th><th>Taqwa</th><th>Jujur</th><th>Disiplin</th><th>Sabar</th><th>Syukur</th><th>Tawadhu</th><th>Empati</th><th>Kerjasama</th><th>Toleransi</th><th>Percaya Diri</th><th>Komunikasi</th></tr>";
while($row = mysqli_fetch_assoc($result)) {
    echo "<tr>
        <td>{$row['nis']}</td>
        <td>{$row['nama']}</td>";
    foreach(['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu','empati','kerjasama','toleransi','percaya_diri','komunikasi'] as $col) {
        $val = $row[$col] ?? '-';
        echo "<td>$val</td>";
    }
    echo "</tr>";
}
echo "</table>";
exit;
