<?php
include '../config.php';
cek_login([1]);
$title = 'Rekap Nilai Sikap';
include '../includes/header.php';

$tahun_ajaran = isset($_GET['tahun']) ? $_GET['tahun'] : get_tahun_ajaran_aktif($conn);
$semester = isset($_GET['semester']) ? $_GET['semester'] : get_semester_aktif($conn);
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas");

// Dapatkan tahun_ajaran_id
$stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1");
$stmt_ta->bind_param("s", $tahun_ajaran);
$stmt_ta->execute();
$ta_id = $stmt_ta->get_result()->fetch_assoc()['id'] ?? 0;

$sikap_data = null;
if ($kelas_id > 0 && $ta_id) {
    $query = "SELECT s.nis, u.nama_lengkap as nama,
                     sp.taqwa, sp.kejujuran, sp.disiplin, sp.sabar, sp.syukur, sp.tawadhu,
                     so.empati, so.kerjasama, so.toleransi, so.percaya_diri, so.komunikasi
              FROM siswa s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN sikap_spiritual sp ON sp.siswa_id = s.id AND sp.tahun_ajaran_id = ? AND sp.semester = ?
              LEFT JOIN sikap_sosial so ON so.siswa_id = s.id AND so.tahun_ajaran_id = ? AND so.semester = ?
              WHERE s.kelas_id = ?
              ORDER BY u.nama_lengkap";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issi", $ta_id, $semester, $ta_id, $semester, $kelas_id);
    $stmt->execute();
    $sikap_data = $stmt->get_result();
}

// Export Excel
if (isset($_GET['export_excel']) && $kelas_id > 0 && $sikap_data && $sikap_data->num_rows > 0) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=rekap_sikap_".$kelas_id."_semester_".$semester.".xls");
    echo "<table border='1'>";
    echo "<tr><th>NIS</th><th>Nama</th><th>Taqwa</th><th>Jujur</th><th>Disiplin</th><th>Sabar</th><th>Syukur</th><th>Tawadhu</th><th>Empati</th><th>Kerjasama</th><th>Toleransi</th><th>Percaya Diri</th><th>Komunikasi</th></tr>";
    while($d = $sikap_data->fetch_assoc()){
        echo "<tr>
            <td>".$d['nis']."</td>
            <td>".$d['nama']."</td>";
        foreach(['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu','empati','kerjasama','toleransi','percaya_diri','komunikasi'] as $col){
            echo "<td>".predikat_sikap($d[$col])."</td>";
        }
        echo "</tr>";
    }
    echo "<table>";
    exit;
}
?>
<div class="page-header"><h2>Rekap Nilai Sikap</h2></div>
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Kelas</label>
            <select name="kelas_id" class="form-select">
                <option value="">-- Pilih Kelas --</option>
                <?php while($k = $kelas_list->fetch_assoc()): ?>
                    <option value="<?= $k['id'] ?>" <?= $kelas_id==$k['id']?'selected':'' ?>><?= $k['nama_kelas'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Semester</label>
            <select name="semester" class="form-select">
                <?= semester_options($semester) ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tahun Ajaran</label>
            <select name="tahun" class="form-select">
                <?= tahun_ajaran_options($tahun_ajaran) ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
            <?php if($kelas_id>0 && $sikap_data && $sikap_data->num_rows > 0): ?>
                <a href="?export_excel=1&kelas_id=<?= $kelas_id ?>&semester=<?= $semester ?>&tahun=<?= $tahun_ajaran ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
                <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Cetak</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if($kelas_id>0 && $sikap_data && $sikap_data->num_rows > 0): ?>
<div class="form-container">
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>NIS</th><th>Nama</th>
                    <th colspan="6">Spiritual</th>
                    <th colspan="5">Sosial</th>
                </tr>
                <tr>
                    <th></th><th></th>
                    <th>Taqwa</th><th>Jujur</th><th>Disiplin</th><th>Sabar</th><th>Syukur</th><th>Tawadhu</th>
                    <th>Empati</th><th>Kerjasama</th><th>Toleransi</th><th>Percaya Diri</th><th>Komunikasi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $sikap_data->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['nis'] ?></td>
                    <td><?= $row['nama'] ?></td>
                    <?php foreach(['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu','empati','kerjasama','toleransi','percaya_diri','komunikasi'] as $col): ?>
                    <td class="text-center"><?= predikat_sikap($row[$col]) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif($kelas_id>0): ?>
<div class="form-container"><p>Tidak ada data sikap untuk kelas, semester, dan tahun ajaran yang dipilih.</p></div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>