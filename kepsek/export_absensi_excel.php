<?php
include '../config.php';
cek_login([4]);

$kelas_id = (int)$_GET['kelas_id'];
$bulan = $_GET['bulan'];
if ($kelas_id == 0) die("Kelas tidak dipilih");

$kelas_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=$kelas_id"));
$kelas_nama = $kelas_info['nama_kelas'];

// Ambil siswa
$siswa = mysqli_query($conn, "SELECT s.id, s.nis, u.nama_lengkap as nama FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.kelas_id = $kelas_id ORDER BY u.nama_lengkap");

// Ambil tanggal absensi unik bulan tersebut
$tgl_query = "SELECT DISTINCT tanggal FROM absensi WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan' ORDER BY tanggal";
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
echo "<th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpha</th><tr>";
$no=1;
foreach($rekap as $data){
    $hadir=$sakit=$izin=$alpha=0;
    echo "<tr><td style='text-align:center'>".$no++."</td><td>{$data['nis']}</td><td>{$data['nama']}</td>";
    foreach($tanggal_list as $tgl){
        $status = $data['absensi'][$tgl]??'-';
        if($status=='hadir') $hadir++;
        elseif($status=='sakit') $sakit++;
        elseif($status=='izin') $izin++;
        elseif($status=='alpha') $alpha++;
        echo "<td style='text-align:center'>$status</td>";
    }
    echo "<td style='text-align:center'>$hadir</td><td style='text-align:center'>$sakit</td><td style='text-align:center'>$izin</td><td style='text-align:center'>$alpha</td></tr>";
}
echo "</table>";
exit;
