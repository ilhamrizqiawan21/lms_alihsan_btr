<?php
include '../config.php';
cek_login([1]);
$title = 'Rekap Tugas';
include '../includes/header.php';

$tahun_ajaran = isset($_GET['tahun']) ? $_GET['tahun'] : get_tahun_ajaran_aktif($conn);
$semester = isset($_GET['semester']) ? $_GET['semester'] : get_semester_aktif($conn);
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas");

$tugas_data = null;
if ($kelas_id > 0) {
    // Dapatkan id tahun_ajaran
    $stmt = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1");
    $stmt->bind_param("s", $tahun_ajaran);
    $stmt->execute();
    $ta_id = $stmt->get_result()->fetch_assoc()['id'] ?? 0;

    $query = "SELECT t.*, k.nama_kelas, mp.nama_mapel, u.nama_lengkap as guru,
                     (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id) as sudah_kumpul,
                     (SELECT COUNT(*) FROM siswa WHERE kelas_id = ?) as total_siswa
              FROM tugas t
              JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
              JOIN kelas k ON km.kelas_id = k.id
              JOIN mata_pelajaran mp ON km.mapel_id = mp.id
              JOIN users u ON km.guru_id = u.id
              WHERE k.id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?
              ORDER BY t.created_at DESC";
    $stmt2 = $conn->prepare($query);
    $stmt2->bind_param("iiis", $kelas_id, $kelas_id, $ta_id, $semester);
    $stmt2->execute();
    $tugas_data = $stmt2->get_result();
}

// Export Excel
if (isset($_GET['export_excel']) && $kelas_id > 0 && $tugas_data && $tugas_data->num_rows > 0) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=rekap_tugas.xls");
    echo "<table border='1'>";
    echo "</td><th>Judul</th><th>Mapel</th><th>Guru</th><th>Deadline</th><th>Kategori</th><th>Sudah Kumpul</th><th>Total Siswa</th></tr>";
    while($t = $tugas_data->fetch_assoc()){
        echo "<tr>
            <td>".htmlspecialchars($t['judul'])."</td>
            <td>".$t['nama_mapel']."</td>
            <td>".$t['guru']."</td>
            <td>".tgl_indonesia($t['batas_waktu'])."</td>
            <td>".$t['kategori_nilai']."</td>
            <td>".$t['sudah_kumpul']."</td>
            <td>".$t['total_siswa']."</td>
        　
            
        ";
    }
    echo "</table>";
    exit;
}
?>
<div class="page-header"><h2>Rekap Tugas</h2></div>
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
            <?php if($kelas_id>0 && $tugas_data && $tugas_data->num_rows > 0): ?>
                <a href="?export_excel=1&kelas_id=<?= $kelas_id ?>&semester=<?= $semester ?>&tahun=<?= $tahun_ajaran ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
                <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Cetak</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if($kelas_id>0 && $tugas_data && $tugas_data->num_rows > 0): ?>
<div class="form-container">
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Judul Tugas</th>
                    <th>Mapel</th>
                    <th>Guru</th>
                    <th>Deadline</th>
                    <th>Kategori</th>
                    <th>Sudah Kumpul</th>
                    <th>Total Siswa</th>
                </tr>
            </thead>
            <tbody>
                <?php while($t = $tugas_data->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($t['judul']) ?></td>
                    <td><?= $t['nama_mapel'] ?></td>
                    <td><?= $t['guru'] ?></td>
                    <td><?= tgl_indonesia($t['batas_waktu']) ?></td>
                    <td><?= $t['kategori_nilai'] ?></td>
                    <td><?= $t['sudah_kumpul'] ?></td>
                    <td><?= $t['total_siswa'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif($kelas_id>0): ?>
<div class="form-container"><p>Tidak ada tugas untuk kelas, semester, dan tahun ajaran yang dipilih.</p></div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>