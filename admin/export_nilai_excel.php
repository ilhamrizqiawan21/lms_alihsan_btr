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

$query = "SELECT s.nis, u.nama_lengkap as nama, mp.nama_mapel, na.* 
          FROM nilai_akhir na
          JOIN siswa s ON na.siswa_id = s.id
          JOIN users u ON s.user_id = u.id
          JOIN kelas_mapel km ON na.kelas_mapel_id = km.id
          JOIN mata_pelajaran mp ON km.mapel_id = mp.id
          WHERE s.kelas_id = $kelas_id AND na.semester='$semester' AND na.tahun_ajaran_id = $ta_id
          ORDER BY u.nama_lengkap, mp.urutan";
$result = mysqli_query($conn, $query);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=rekap_nilai_".$kelas_nama."_semester".$semester.".xls");
echo "<table border='1'>";
echo "<td><th>NIS</th><th>Nama</th><th>Mata Pelajaran</th><th>SUM1</th><th>SUM2</th><th>SUM3</th><th>SUM4</th><th>STS</th><th>SAS</th><th>SAT</th><th>Rata</th></tr>";
while($row = mysqli_fetch_assoc($result)) {
    $rata = hitung_rata_akhir($row['sum1'], $row['sum2'], $row['sum3'], $row['sum4'], $row['sts'], $row['sas'], $row['sat']);
    echo "<tr>
        <td>{$row['nis']}</td>
        <td>{$row['nama']}</td>
        <td>{$row['nama_mapel']}</td>
        <td>{$row['sum1']}</td>
        <td>{$row['sum2']}</td>
        <td>{$row['sum3']}</td>
        <td>{$row['sum4']}</td>
        <td>{$row['sts']}</td>
        <td>{$row['sas']}</td>
        <td>{$row['sat']}</td>
        <td>$rata</td>
    　
            
    ";
}
echo "</table>";
exit;
