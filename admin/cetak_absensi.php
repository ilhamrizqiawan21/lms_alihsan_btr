<?php
include '../config.php';
cek_login([1]);

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

if ($kelas_id == 0) die("Kelas tidak dipilih");

$kelas_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=$kelas_id"));
$kelas_nama = $kelas_info['nama_kelas'];

$siswa = mysqli_query($conn, "SELECT s.id, s.nis, u.nama_lengkap as nama FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.kelas_id = $kelas_id ORDER BY u.nama_lengkap");

$tgl_query = "SELECT DISTINCT a.tanggal FROM absensi a 
              JOIN siswa s ON a.siswa_id = s.id 
              WHERE s.kelas_id = $kelas_id AND DATE_FORMAT(a.tanggal, '%Y-%m') = '$bulan'
              ORDER BY a.tanggal";
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Rekap Absensi - <?= $kelas_nama ?> - <?= date('F Y', strtotime($bulan)) ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 20px;
            font-size: 12pt;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
        }
        .header p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        td:first-child, td:nth-child(2), td:nth-child(3) {
            text-align: left;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10pt;
        }
        @media print {
            body {
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
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
        <tr>
            <th>No</th><th>NIS</th><th>Nama Siswa</th>
            <?php foreach($tanggal_list as $tgl): ?>
                <th><?= tgl_indonesia($tgl) ?></th>
            <?php endforeach; ?>
            <th>H</th><th>S</th><th>I</th><th>A</th>
        </tr>
    </thead>
    <tbody>
        <?php $no=1; foreach($rekap as $data): $hadir=$sakit=$izin=$alpha=0; ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= $data['nis'] ?></td>
            <td style="text-align:left"><?= $data['nama'] ?></td>
            <?php foreach($tanggal_list as $tgl): 
                $status = $data['absensi'][$tgl]??'-';
                if($status=='hadir') $hadir++;
                elseif($status=='sakit') $sakit++;
                elseif($status=='izin') $izin++;
                elseif($status=='alpha') $alpha++;
                $label = ($status=='hadir') ? 'H' : (($status=='sakit') ? 'S' : (($status=='izin') ? 'I' : (($status=='alpha') ? 'A' : '-')));
                echo "<td>$label</td>";
            endforeach; ?>
            <td><?= $hadir ?></td>
            <td><?= $sakit ?></td>
            <td><?= $izin ?></td>
            <td><?= $alpha ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="footer">
    Batujajar, <?= date('d F Y') ?><br>
    Guru Mata Pelajaran,<br><br><br>
    <u>Ilham Rizqiawan, S.Pd.</u>
</div>

<div class="no-print" style="margin-top: 20px; text-align: center;">
    <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px;">Cetak / Simpan PDF</button>
    <button onclick="window.close()" style="padding: 8px 16px; font-size: 14px;">Tutup</button>
</div>
</body>
</html>