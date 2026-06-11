<?php
include '../config.php';
cek_login([4]);

$kelas_id = (int)$_GET['kelas_id'];
$bulan = $_GET['bulan'];
if ($kelas_id == 0) die("Kelas tidak dipilih");

$kelas_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=$kelas_id"));
$kelas_nama = $kelas_info['nama_kelas'];

$siswa = mysqli_query($conn, "SELECT s.id, s.nis, u.nama_lengkap as nama FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.kelas_id = $kelas_id ORDER BY u.nama_lengkap");

$tgl_query = "SELECT DISTINCT tanggal FROM absensi WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$bulan' ORDER BY tanggal";
$tgl_res = mysqli_query($conn, $tgl_query);
$tanggal_list = [];
while($tgl = mysqli_fetch_assoc($tgl_res)) $tanggal_list[] = $tgl['tanggal'];

$rekap = [];
while ($s = mysqli_fetch_assoc($siswa)) {
    $siswa_id = $s['id'];
    $rekap[$siswa_id] = ['nama'=>$s['nama'], 'nis'=>$s['nis'], 'absensi'=>[]];
    $absen = mysqli_query($conn, "SELECT status, tanggal FROM absensi WHERE siswa_id = $siswa_id AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan'");
    while ($ab = mysqli_fetch_assoc($absen)) $rekap[$siswa_id]['absensi'][$ab['tanggal']] = $ab['status'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi <?= $kelas_nama ?> - <?= date('F Y', strtotime($bulan)) ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; text-align: center; }
        th { background-color: #f2f2f2; }
        td:first-child, td:nth-child(2), td:nth-child(3) { text-align: left; }
        .footer { margin-top: 30px; text-align: right; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
<div class="header">
    <h2>MTs. Al-Ihsan Batujajar</h2>
    <p>Jl. Batujajar Blok Pasantren 05/07, Batujajar, Bandung Barat</p>
    <h3>REKAP ABSENSI SISWA</h3>
    <p>Kelas : <?= $kelas_nama ?> - Bulan : <?= date('F Y', strtotime($bulan)) ?></p>
</div>
<table>
    <thead>
        <tr><th>No</th><th>NIS</th><th>Nama</th>
        <?php foreach($tanggal_list as $tgl): ?><th><?= tgl_indonesia($tgl) ?></th><?php endforeach; ?>
        <th>H</th><th>S</th><th>I</th><th>A</th>
        </tr>
    </thead>
    <tbody>
        <?php $no=1; foreach($rekap as $data): $hadir=$sakit=$izin=$alpha=0; ?>
        <tr>
            <td style="text-align:center"><?= $no++ ?></td>
            <td><?= $data['nis'] ?></td>
            <td><?= $data['nama'] ?></td>
            <?php foreach($tanggal_list as $tgl): 
                $status = $data['absensi'][$tgl]??'-';
                if($status=='hadir') $hadir++;
                elseif($status=='sakit') $sakit++;
                elseif($status=='izin') $izin++;
                elseif($status=='alpha') $alpha++;
                $label = ($status=='hadir')?'H':(($status=='sakit')?'S':(($status=='izin')?'I':(($status=='alpha')?'A':'-')));
                echo "<td style='text-align:center'>$label</td>";
            endforeach; ?>
            <td style="text-align:center"><?= $hadir ?></td>
            <td style="text-align:center"><?= $sakit ?></td>
            <td style="text-align:center"><?= $izin ?></td>
            <td style="text-align:center"><?= $alpha ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="footer">
    Batujajar, <?= date('d F Y') ?><br>
    Kepala Sekolah,<br><br><br>
    <u>__________________</u>
</div>
<div class="no-print" style="margin-top:20px; text-align:center;">
    <button onclick="window.print()">Cetak / Simpan PDF</button>
    <button onclick="window.close()">Tutup</button>
</div>
</body>
</html>