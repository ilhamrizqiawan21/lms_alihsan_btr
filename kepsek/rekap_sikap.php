<?php
include '../config.php';
cek_login([4]);

// Redirect untuk export/cetak
if (isset($_GET['export_excel']) && isset($_GET['kelas_id'])) {
    header("Location: export_sikap_excel.php?kelas_id=".(int)$_GET['kelas_id']."&semester=".urlencode($_GET['semester'])."&tahun=".urlencode($_GET['tahun']));
    exit;
}
if (isset($_GET['cetak']) && isset($_GET['kelas_id'])) {
    header("Location: cetak_sikap.php?kelas_id=".(int)$_GET['kelas_id']."&semester=".urlencode($_GET['semester'])."&tahun=".urlencode($_GET['tahun']));
    exit;
}

$title = 'Rekap Nilai Sikap';
include '../includes/header.php';

$tahun_ajaran = isset($_GET['tahun']) ? $_GET['tahun'] : get_tahun_ajaran_aktif($conn);
$semester = isset($_GET['semester']) ? $_GET['semester'] : get_semester_aktif($conn);
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

$kelas_list = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas");
$ta_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM tahun_ajaran WHERE tahun='$tahun_ajaran' AND is_active=1"))['id'] ?? 0;
$km_id = 0;
if ($kelas_id > 0) {
    $km_res = mysqli_query($conn, "SELECT id FROM kelas_mapel WHERE kelas_id=$kelas_id LIMIT 1");
    if ($km_res && mysqli_num_rows($km_res)) $km_id = mysqli_fetch_assoc($km_res)['id'];
}
$sikap_data = null;
if ($kelas_id > 0 && $km_id && $ta_id) {
    $query = "SELECT s.nis, u.nama_lengkap as nama, 
              sp.taqwa, sp.kejujuran, sp.disiplin, sp.sabar, sp.syukur, sp.tawadhu,
              so.empati, so.kerjasama, so.toleransi, so.percaya_diri, so.komunikasi
              FROM siswa s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN sikap_spiritual sp ON sp.siswa_id = s.id AND sp.kelas_mapel_id = $km_id AND sp.tahun_ajaran_id = $ta_id AND sp.semester='$semester'
              LEFT JOIN sikap_sosial so ON so.siswa_id = s.id AND so.kelas_mapel_id = $km_id AND so.tahun_ajaran_id = $ta_id AND so.semester='$semester'
              WHERE s.kelas_id = $kelas_id
              ORDER BY u.nama_lengkap";
    $sikap_data = mysqli_query($conn, $query);
}
?>
<div class="page-header"><h2>Rekap Nilai Sikap</h2></div>
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group"><label>Kelas</label><select name="kelas_id" class="form-select"><?php while($k=mysqli_fetch_assoc($kelas_list)): ?><option value="<?= $k['id'] ?>" <?= $kelas_id==$k['id']?'selected':'' ?>><?= $k['nama_kelas'] ?></option><?php endwhile; ?></select></div>
        <div class="form-group"><label>Semester</label><select name="semester" class="form-select"><option value="1" <?= $semester=='1'?'selected':'' ?>>Semester 1 (Ganjil)</option><option value="2" <?= $semester=='2'?'selected':'' ?>>Semester 2 (Genap)</option></select></div>
        <div class="form-group"><label>Tahun Ajaran</label><select name="tahun" class="form-select"><?php foreach(['2024/2025','2025/2026','2026/2027','2027/2028'] as $ta): $sel = ($tahun_ajaran==$ta)?'selected':''; ?><option value="<?=$ta?>" <?=$sel?>><?=$ta?></option><?php endforeach; ?></select></div>
        <div class="form-group" style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
            <?php if($kelas_id>0 && $sikap_data && mysqli_num_rows($sikap_data)>0): ?>
                <a href="?export_excel=1&kelas_id=<?= $kelas_id ?>&semester=<?= $semester ?>&tahun=<?= $tahun_ajaran ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
                <a href="?cetak=1&kelas_id=<?= $kelas_id ?>&semester=<?= $semester ?>&tahun=<?= $tahun_ajaran ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if($kelas_id>0 && $sikap_data && mysqli_num_rows($sikap_data)>0): ?>
<div class="form-container">
    <div class="table-wrapper">
        <table class="modern-table">
            <thead><tr><th>NIS</th><th>Nama</th><th colspan="6">Spiritual</th><th colspan="5">Sosial</th></tr>
            <tr><th></th><th></th><th>Taqwa</th><th>Jujur</th><th>Disiplin</th><th>Sabar</th><th>Syukur</th><th>Tawadhu</th><th>Empati</th><th>Kerjasama</th><th>Toleransi</th><th>Percaya Diri</th><th>Komunikasi</th></tr></thead>
            <tbody><?php while($row=mysqli_fetch_assoc($sikap_data)): ?>
                <tr><td><?= $row['nis'] ?></td><td><?= $row['nama'] ?></td><?php foreach(['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu','empati','kerjasama','toleransi','percaya_diri','komunikasi'] as $col): ?><td><?= predikat_sikap($row[$col]) ?></td><?php endforeach; ?></tr>
            <?php endwhile; ?></tbody>
        </table>
    </div>
</div>
<?php elseif($kelas_id>0): ?>
<div class="form-container"><p>Tidak ada data sikap untuk kelas ini.</p></div>
<?php endif; ?>
<?php include '../includes/footer.php'; 