<?php
include '../config.php';
cek_login([1]);

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

if ($kelas_id == 0) die("Kelas tidak dipilih");

$kelas_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=$kelas_id"));
$kelas_nama = $kelas_info['nama_kelas'];

$siswa = mysqli_query($conn, "SELECT s.id, s.nis, u.nama_lengkap as nama FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.kelas_id = $kelas_id ORDER BY u.nama_lengkap");

// Tanggal absensi unik
$tgl_query = "SELECT DISTINCT a.tanggal FROM absensi a 
              JOIN siswa s ON a.siswa_id = s.id 
              WHERE s.kelas_id = $kelas_id AND DATE_FORMAT(a.tanggal, '%Y-%m') = '$bulan'
              ORDER BY a.tanggal";
$tgl_res = mysqli_query($conn, $tgl_query);
$tanggal_list = [];
while($tgl = mysqli_fetch_assoc($tgl_res)) $tanggal_list[] = $tgl['tanggal'];

// Kumpulkan data
$rekap = [];
while ($s = mysqli_fetch_assoc($siswa)) {
    $siswa_id = $s['id'];
    $rekap[$siswa_id] = ['nama'=>$s['nama'], 'nis'=>$s['nis'], 'absensi'=>[]];
    $absen = mysqli_query($conn, "SELECT status, tanggal FROM absensi WHERE siswa_id = $siswa_id AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan'");
    while ($ab = mysqli_fetch_assoc($absen)) $rekap[$siswa_id]['absensi'][$ab['tanggal']] = $ab['status'];
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=rekap_absensi_".$kelas_nama."_".$bulan.".xls");
echo "<table border='1'>";
echo "<tr><th>No</th><th>NIS</th><th>Nama</th>";
foreach($tanggal_list as $tgl) echo "<th>".tgl_indonesia($tgl)."</th>";
echo "<th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpha</th></tr>";

$no=1;
foreach($rekap as $data){
    $hadir=$sakit=$izin=$alpha=0;
    echo "<tr>
        <td>".$no++."</td>
        <td>".$data['nis']."</td>
        <td>".$data['nama']."</td>";
    foreach($tanggal_list as $tgl){
        $status = $data['absensi'][$tgl]??'-';
        if($status=='hadir') $hadir++;
        elseif($status=='sakit') $sakit++;
        elseif($status=='izin') $izin++;
        elseif($status=='alpha') $alpha++;
        echo "<td>$status</td>";
    }
    echo "<td>$hadir</td>
        <td>$sakit</td>
        <td>$izin</td>
        <td>$alpha</td>
    </tr>";
}
echo "</table>";
exit;
