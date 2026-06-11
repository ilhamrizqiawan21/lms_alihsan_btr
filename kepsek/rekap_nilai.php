<?php
include '../config.php';
cek_login([4]);

// Redirect untuk export/cetak
if (isset($_GET['export_excel']) && isset($_GET['kelas_id'])) {
    header("Location: export_nilai_excel.php?kelas_id=".(int)$_GET['kelas_id']."&semester=".urlencode($_GET['semester'])."&tahun=".urlencode($_GET['tahun']));
    exit;
}
if (isset($_GET['cetak']) && isset($_GET['kelas_id'])) {
    header("Location: cetak_nilai.php?kelas_id=".(int)$_GET['kelas_id']."&semester=".urlencode($_GET['semester'])."&tahun=".urlencode($_GET['tahun']));
    exit;
}

$title = 'Rekap Nilai Akhir';
include '../includes/header.php';

$tahun_ajaran = isset($_GET['tahun']) ? $_GET['tahun'] : get_tahun_ajaran_aktif($conn);
$semester = isset($_GET['semester']) ? $_GET['semester'] : get_semester_aktif($conn);
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas");

// Ambil tahun_ajaran_id dengan prepared statement
$ta_id = 0;
if ($tahun_ajaran) {
    $stmt = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $tahun_ajaran);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $ta_id = $res->fetch_assoc()['id'];
    }
}

$nilai_data = null;
if ($kelas_id > 0 && $ta_id > 0) {
    $query = "SELECT s.nis, u.nama_lengkap as nama, mp.nama_mapel, na.* 
              FROM nilai_akhir na
              JOIN siswa s ON na.siswa_id = s.id
              JOIN users u ON s.user_id = u.id
              JOIN kelas_mapel km ON na.kelas_mapel_id = km.id
              JOIN mata_pelajaran mp ON km.mapel_id = mp.id
              WHERE s.kelas_id = ? AND na.semester = ? AND na.tahun_ajaran_id = ?
              ORDER BY u.nama_lengkap, mp.urutan";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $kelas_id, $semester, $ta_id);
    $stmt->execute();
    $nilai_data = $stmt->get_result();
}
?>
<div class="page-header"><h2>Rekap Nilai Akhir</h2></div>
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group"><label>Kelas</label>
            <select name="kelas_id" class="form-select">
                <option value="">-- Pilih Kelas --</option>
                <?php while($k = $kelas_list->fetch_assoc()): ?>
                    <option value="<?= $k['id'] ?>" <?= $kelas_id == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group"><label>Semester</label>
            <select name="semester" class="form-select">
                <option value="1" <?= $semester == '1' ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?= $semester == '2' ? 'selected' : '' ?>>Semester 2 (Genap)</option>
            </select>
        </div>
        <div class="form-group"><label>Tahun Ajaran</label>
            <select name="tahun" class="form-select">
                <?php foreach(['2024/2025','2025/2026','2026/2027','2027/2028'] as $ta): ?>
                    <option value="<?= $ta ?>" <?= $tahun_ajaran == $ta ? 'selected' : '' ?>><?= $ta ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
            <?php if($kelas_id > 0 && $nilai_data && $nilai_data->num_rows > 0): ?>
                <a href="?export_excel=1&kelas_id=<?= $kelas_id ?>&semester=<?= $semester ?>&tahun=<?= $tahun_ajaran ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
                <a href="?cetak=1&kelas_id=<?= $kelas_id ?>&semester=<?= $semester ?>&tahun=<?= $tahun_ajaran ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if($kelas_id > 0 && $nilai_data && $nilai_data->num_rows > 0): ?>
<div class="form-container">
    <div class="form-title">Nilai Kelas <?= htmlspecialchars(mysqli_fetch_assoc($conn->query("SELECT nama_kelas FROM kelas WHERE id=$kelas_id"))['nama_kelas']) ?> - Semester <?= $semester == 1 ? 'Ganjil' : 'Genap' ?> <?= $tahun_ajaran ?></div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr><th>NIS</th><th>Nama</th><th>Mata Pelajaran</th><th>SUM1</th><th>SUM2</th><th>SUM3</th><th>SUM4</th><th>STS</th><th>SAS</th><th>SAT</th><th>Rata</th></tr>
            </thead>
            <tbody>
            <?php
            $current_siswa = '';
            while($row = $nilai_data->fetch_assoc()):
                $rata = hitung_rata_akhir($row['sum1'], $row['sum2'], $row['sum3'], $row['sum4'], $row['sts'], $row['sas'], $row['sat']);
                if($current_siswa != $row['nis']):
                    if($current_siswa != ''):
                        echo "</tbody><tr><div style='margin-top:20px'></div><table class='modern-table'><thead>
                                <tr><th>NIS</th><th>Nama</th><th>Mata Pelajaran</th><th>SUM1</th><th>SUM2</th><th>SUM3</th><th>SUM4</th><th>STS</th><th>SAS</th><th>SAT</th><th>Rata</th>
                              </tr></thead><tbody>";
                    endif;
                    $current_siswa = $row['nis'];
                endif;
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['nis']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                    <td><?= $row['sum1'] ?? '-' ?></td>
                    <td><?= $row['sum2'] ?? '-' ?></td>
                    <td><?= $row['sum3'] ?? '-' ?></td>
                    <td><?= $row['sum4'] ?? '-' ?></td>
                    <td><?= $row['sts'] ?? '-' ?></td>
                    <td><?= $row['sas'] ?? '-' ?></td>
                    <td><?= $row['sat'] ?? '-' ?></td>
                    <td><strong><?= $rata ?></strong></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif($kelas_id > 0): ?>
<div class="form-container"><p>Belum ada data nilai untuk kriteria yang dipilih.</p></div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>