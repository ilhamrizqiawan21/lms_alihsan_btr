<?php
include '../config.php';
cek_login([2]);
$title = 'Rekap Nilai Sikap';
include '../includes/header.php';

$guru_id = $_SESSION['user_id'];
$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;

$km_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT km.*, k.nama_kelas FROM kelas_mapel km 
    JOIN kelas k ON km.kelas_id = k.id 
    WHERE km.id = $kelas_mapel_id AND km.guru_id = $guru_id"));
if (!$km_check && $kelas_mapel_id > 0) die("Akses ditolak");

$siswa_sikap = [];
if ($kelas_mapel_id && $km_check) {
    $kelas_id = $km_check['kelas_id'];
    $tahun_ajaran_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM tahun_ajaran WHERE tahun='$tahun_aktif' AND is_active=1"))['id'];
    $query = "SELECT s.id, s.nis, u.nama_lengkap as nama,
              sp.taqwa, sp.kejujuran, sp.disiplin, sp.sabar, sp.syukur, sp.tawadhu,
              so.empati, so.kerjasama, so.toleransi, so.percaya_diri, so.komunikasi
              FROM siswa s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN sikap_spiritual sp ON sp.siswa_id = s.id AND sp.kelas_mapel_id = $kelas_mapel_id AND sp.tahun_ajaran_id = $tahun_ajaran_id AND sp.semester = '$semester_aktif'
              LEFT JOIN sikap_sosial so ON so.siswa_id = s.id AND so.kelas_mapel_id = $kelas_mapel_id AND so.tahun_ajaran_id = $tahun_ajaran_id AND so.semester = '$semester_aktif'
              WHERE s.kelas_id = $kelas_id
              ORDER BY u.nama_lengkap";
    $siswa_sikap = mysqli_query($conn, $query);
}

$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);
?>
<div class="page-header"><h2>Rekap Nilai Sikap</h2></div>
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group"><label>Kelas & Mapel</label>
            <select name="kelas_mapel_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Pilih --</option>
                <?php while($km = mysqli_fetch_assoc($kelas_mapel_options)): ?>
                <option value="<?= $km['id'] ?>" <?= $kelas_mapel_id==$km['id']?'selected':'' ?>><?= $km['nama_kelas'] ?> - <?= $km['nama_mapel'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <?php if($kelas_mapel_id && mysqli_num_rows($siswa_sikap)>0): ?>
            <a href="../admin/export_sikap_excel.php?kelas_id=<?= $km_check['kelas_id'] ?>&semester=<?= $semester_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="../admin/cetak_sikap.php?kelas_id=<?= $km_check['kelas_id'] ?>&semester=<?= $semester_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if($kelas_mapel_id && $km_check && mysqli_num_rows($siswa_sikap) > 0): ?>
<div class="form-container">
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr><th>NIS</th><th>Nama</th><th colspan="6">Spiritual</th><th colspan="5">Sosial</th></tr>
                <tr><th></th><th></th><th>Taqwa</th><th>Jujur</th><th>Disiplin</th><th>Sabar</th><th>Syukur</th><th>Tawadhu</th><th>Empati</th><th>Kerjasama</th><th>Toleransi</th><th>Percaya Diri</th><th>Komunikasi</th></tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($siswa_sikap)): ?>
                <tr>
                    <td><?= $row['nis'] ?></td>
                    <td><?= $row['nama'] ?></td>
                    <?php foreach(['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu','empati','kerjasama','toleransi','percaya_diri','komunikasi'] as $col): ?>
                    <td><?= predikat_sikap($row[$col]) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif($kelas_mapel_id && $km_check): ?>
    <div class="form-container"><p>Tidak ada data sikap untuk kelas ini.</p></div>
<?php endif; ?>
<?php include '../includes/footer.php'; 