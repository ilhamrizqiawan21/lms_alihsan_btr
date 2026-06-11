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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Sikap - <?= $kelas_nama ?> - Semester <?= $semester ?> <?= $tahun_ajaran ?></title>
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
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
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
    <h3>REKAP NILAI SIKAP (SPIRITUAL & SOSIAL)</h3>
    <p>Kelas : <?= $kelas_nama ?> - Semester <?= $semester == 1 ? 'Ganjil' : 'Genap' ?> <?= $tahun_ajaran ?></p>
</div>

<table>
    <thead>
        <tr>
            <th>NIS</th><th>Nama</th>
            <th colspan="6">Spiritual (KI-1)</th>
            <th colspan="5">Sosial (KI-2)</th>
        </tr>
        <tr><th></th><th></th>
            <th>Taqwa</th><th>Jujur</th><th>Disiplin</th><th>Sabar</th><th>Syukur</th><th>Tawadhu</th>
            <th>Empati</th><th>Kerjasama</th><th>Toleransi</th><th>Percaya Diri</th><th>Komunikasi</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?= $row['nis'] ?></td>
            <td><?= $row['nama'] ?></td>
            <?php foreach(['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu','empati','kerjasama','toleransi','percaya_diri','komunikasi'] as $col): ?>
                <td><?= $row[$col] ?? '-' ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<div class="footer">
    Batujajar, <?= date('d F Y') ?><br>
    Guru Mata Pelajaran / Wali Kelas,<br><br><br>
    <u>Ilham Rizqiawan, S.Pd.</u>
</div>

<div class="no-print" style="margin-top: 20px; text-align: center;">
    <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px;">Cetak / Simpan PDF</button>
    <button onclick="window.close()" style="padding: 8px 16px; font-size: 14px;">Tutup</button>
</div>
</body>
</html>