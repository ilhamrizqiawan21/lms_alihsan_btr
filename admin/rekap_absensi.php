<?php
include '../config.php';
cek_login([1]);
$title = 'Rekap Absensi';
include '../includes/header.php';

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

// Ambil daftar kelas
$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas");

$rekap = [];
$tanggal_list = [];
$kelas_nama = '';

if ($kelas_id > 0) {
    // Ambil nama kelas
    $stmt = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $kelas_nama = $res->fetch_assoc()['nama_kelas'] ?? '';

    // Ambil siswa kelas
    $stmt_siswa = $conn->prepare("
        SELECT s.id, s.nis, u.nama_lengkap as nama
        FROM siswa s
        JOIN users u ON s.user_id = u.id
        WHERE s.kelas_id = ?
        ORDER BY u.nama_lengkap
    ");
    $stmt_siswa->bind_param("i", $kelas_id);
    $stmt_siswa->execute();
    $siswa = $stmt_siswa->get_result();

    // Ambil tanggal absensi unik bulan tersebut
    $stmt_tgl = $conn->prepare("
        SELECT DISTINCT a.tanggal FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        WHERE s.kelas_id = ? AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
        ORDER BY a.tanggal
    ");
    $stmt_tgl->bind_param("is", $kelas_id, $bulan);
    $stmt_tgl->execute();
    $tgl_res = $stmt_tgl->get_result();
    while ($row = $tgl_res->fetch_assoc()) {
        $tanggal_list[] = $row['tanggal'];
    }

    // Kumpulkan data absensi per siswa
    while ($s = $siswa->fetch_assoc()) {
        $siswa_id = $s['id'];
        $rekap[$siswa_id] = ['nama' => $s['nama'], 'nis' => $s['nis'], 'absensi' => []];
        $stmt_absen = $conn->prepare("
            SELECT status, tanggal FROM absensi
            WHERE siswa_id = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ?
        ");
        $stmt_absen->bind_param("is", $siswa_id, $bulan);
        $stmt_absen->execute();
        $absen_res = $stmt_absen->get_result();
        while ($ab = $absen_res->fetch_assoc()) {
            $rekap[$siswa_id]['absensi'][$ab['tanggal']] = $ab['status'];
        }
    }
}
?>

<div class="page-header">
    <h2 class="page-title">Rekap Absensi</h2>
    <p class="page-subtitle">Rekapitulasi kehadiran siswa per kelas dan bulan</p>
</div>

<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Kelas</label>
            <select name="kelas_id" class="form-select">
                <option value="">-- Pilih Kelas --</option>
                <?php while($k = $kelas_list->fetch_assoc()): ?>
                    <option value="<?= $k['id'] ?>" <?= $kelas_id == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Bulan</label>
            <input type="month" name="bulan" value="<?= $bulan ?>" class="form-input">
        </div>
        <div class="form-group" style="display: flex; gap: 8px; align-items: flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            <?php if($kelas_id > 0 && !empty($rekap)): ?>
                <a href="export_absensi_excel.php?kelas_id=<?= $kelas_id ?>&bulan=<?= $bulan ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
                <a href="cetak_absensi.php?kelas_id=<?= $kelas_id ?>&bulan=<?= $bulan ?>" target="_blank" class="btn btn-outline"><i class="fas fa-print"></i> Cetak</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if($kelas_id > 0 && !empty($rekap)): ?>
<div class="form-container">
    <div class="form-title">Kelas <?= htmlspecialchars($kelas_nama) ?> - Bulan <?= date('F Y', strtotime($bulan)) ?></div>
    <div class="table-wrapper">
        <table class="modern-table" id="tabel-absensi">
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
                <?php $no = 1; foreach($rekap as $data): $hadir = $sakit = $izin = $alpha = 0; ?>
                <tr>
                    <td style="text-align:center"><?= $no++ ?></td>
                    <td><?= $data['nis'] ?></td>
                    <td style="text-align:left"><strong><?= htmlspecialchars($data['nama']) ?></strong></td>
                    <?php foreach($tanggal_list as $tgl):
                        $status = $data['absensi'][$tgl] ?? '-';
                        if($status == 'hadir') $hadir++;
                        elseif($status == 'sakit') $sakit++;
                        elseif($status == 'izin') $izin++;
                        elseif($status == 'alpha') $alpha++;
                    ?>
                        <td style="text-align:center"><?= status_badge($status) ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:center"><?= $hadir ?></td>
                    <td style="text-align:center"><?= $sakit ?></td>
                    <td style="text-align:center"><?= $izin ?></td>
                    <td style="text-align:center"><?= $alpha ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif($kelas_id > 0): ?>
<div class="form-container">
    <p>Belum ada data absensi untuk kelas ini pada bulan <?= date('F Y', strtotime($bulan)) ?>.</p>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>