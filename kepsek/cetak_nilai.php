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

$data_per_siswa = [];
while($row = mysqli_fetch_assoc($result)) {
    $data_per_siswa[$row['nis']]['nama'] = $row['nama'];
    $data_per_siswa[$row['nis']]['mapel'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Nilai - <?= $kelas_nama ?> - Semester <?= $semester ?> <?= $tahun_ajaran ?></title>
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
        .header h2 { margin: 0; }
        .header p { margin: 5px 0; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        td:first-child, td:nth-child(2) {
            text-align: left;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10pt;
        }
        .siswa-group {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .siswa-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0 10px;
            background: #e0e0e0;
            padding: 5px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="header">
    <h2>MTs. Al-Ihsan Batujajar</h2>
    <p>Jl. Batujajar Blok Pasantren 05/07, Batujajar, Bandung Barat</p>
    <h3>REKAP NILAI AKHIR</h3>
    <p>Kelas : <?= $kelas_nama ?> - Semester <?= $semester == 1 ? 'Ganjil' : 'Genap' ?> <?= $tahun_ajaran ?></p>
</div>

<?php foreach($data_per_siswa as $nis => $data): ?>
<div class="siswa-group">
    <div class="siswa-title">NIS: <?= $nis ?> - <?= $data['nama'] ?></div>
    <table>
        <thead>
            <tr><th>Mata Pelajaran</th><th>SUM1</th><th>SUM2</th><th>SUM3</th><th>SUM4</th><th>STS</th><th>SAS</th><th>SAT</th><th>Rata</th></tr>
        </thead>
        <tbody>
            <?php foreach($data['mapel'] as $m): 
                $rata = hitung_rata_akhir($m['sum1'], $m['sum2'], $m['sum3'], $m['sum4'], $m['sts'], $m['sas'], $m['sat']);
            ?>
            <tr>
                <td style="text-align:left"><?= $m['nama_mapel'] ?></td>
                <td><?= $m['sum1'] ?? '-' ?></td>
                <td><?= $m['sum2'] ?? '-' ?></td>
                <td><?= $m['sum3'] ?? '-' ?></td>
                <td><?= $m['sum4'] ?? '-' ?></td>
                <td><?= $m['sts'] ?? '-' ?></td>
                <td><?= $m['sas'] ?? '-' ?></td>
                <td><?= $m['sat'] ?? '-' ?></td>
                <td><strong><?= $rata ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<div class="footer">
    Batujajar, <?= date('d F Y') ?><br>
    Kepala Madrasah / Guru Mata Pelajaran,<br><br><br>
    <u>Ilham Rizqiawan, S.Pd.</u>
</div>

<div class="no-print" style="margin-top: 20px; text-align: center;">
    <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px;">Cetak / Simpan PDF</button>
    <button onclick="window.close()" style="padding: 8px 16px; font-size: 14px;">Tutup</button>
</div>
</body>
</html>