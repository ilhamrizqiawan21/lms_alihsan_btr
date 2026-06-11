<?php
include '../config.php';
cek_login([4]);

// ========== CEK EXPORT DAN CETAK (harus sebelum output apapun) ==========
if (isset($_GET['export_excel'])) {
    $kelas_id = (int)$_GET['kelas_id'];
    $bulan = $_GET['bulan'];
    if ($kelas_id > 0) {
        header("Location: export_absensi_excel.php?kelas_id=$kelas_id&bulan=$bulan");
        exit;
    }
}
if (isset($_GET['cetak'])) {
    $kelas_id = (int)$_GET['kelas_id'];  // PERBAIKAN: ambil dari GET['kelas_id']
    $bulan = $_GET['bulan'];
    if ($kelas_id > 0) {
        header("Location: cetak_absensi.php?kelas_id=$kelas_id&bulan=$bulan");
        exit;
    }
}

$title = 'Rekap Absensi';
include '../includes/header.php';

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

$kelas_list = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas");
$rekap = [];
$tanggal_list = [];
$kelas_nama = '';

if ($kelas_id > 0) {
    $kelas_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id=$kelas_id"));
    $kelas_nama = $kelas_info['nama_kelas'];
    $siswa = mysqli_query($conn, "SELECT s.id, s.nis, u.nama_lengkap as nama FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.kelas_id = $kelas_id ORDER BY u.nama_lengkap");
    
    $tgl_query = "SELECT DISTINCT a.tanggal FROM absensi a 
                  JOIN siswa s ON a.siswa_id = s.id 
                  WHERE s.kelas_id = $kelas_id AND DATE_FORMAT(a.tanggal, '%Y-%m') = '$bulan'
                  ORDER BY a.tanggal";
    $tgl_res = mysqli_query($conn, $tgl_query);
    while($tgl = mysqli_fetch_assoc($tgl_res)) $tanggal_list[] = $tgl['tanggal'];
    
    while ($s = mysqli_fetch_assoc($siswa)) {
        $siswa_id = $s['id'];
        $rekap[$siswa_id] = ['nama'=>$s['nama'], 'nis'=>$s['nis'], 'absensi'=>[]];
        $absen = mysqli_query($conn, "SELECT status, tanggal FROM absensi WHERE siswa_id = $siswa_id AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan'");
        while ($ab = mysqli_fetch_assoc($absen)) $rekap[$siswa_id]['absensi'][$ab['tanggal']] = $ab['status'];
    }
}
?>
<div class="page-header"><h2>Rekap Absensi</h2></div>
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group"><label>Kelas</label><select name="kelas_id" class="form-select"><?php while($k=mysqli_fetch_assoc($kelas_list)): ?><option value="<?= $k['id'] ?>" <?= $kelas_id==$k['id']?'selected':'' ?>><?= $k['nama_kelas'] ?></option><?php endwhile; ?></select></div>
        <div class="form-group"><label>Bulan</label><input type="month" name="bulan" value="<?= $bulan ?>" class="form-input"></div>
        <div class="form-group" style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
            <?php if($kelas_id>0 && !empty($rekap)): ?>
                <a href="?export_excel=1&kelas_id=<?= $kelas_id ?>&bulan=<?= $bulan ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
                <a href="?cetak=1&kelas_id=<?= $kelas_id ?>&bulan=<?= $bulan ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if($kelas_id>0 && !empty($rekap)): ?>
<div class="form-container">
    <div class="form-title">Kelas <?= $kelas_nama ?> - Bulan <?= date('F Y', strtotime($bulan)) ?></div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead><tr><th>No</th><th>NIS</th><th>Nama</th><?php foreach($tanggal_list as $tgl): ?><th><?= tgl_indonesia($tgl) ?></th><?php endforeach; ?><th>H</th><th>S</th><th>I</th><th>A</th></tr></thead>
            <tbody><?php $no=1; foreach($rekap as $data): $hadir=$sakit=$izin=$alpha=0; ?>
                <tr><td style="text-align:center"><?= $no++ ?></td><td><?= $data['nis'] ?></td><td><?= $data['nama'] ?></td>
                <?php foreach($tanggal_list as $tgl): $status = $data['absensi'][$tgl]??'-';
                    if($status=='hadir') $hadir++; elseif($status=='sakit') $sakit++; elseif($status=='izin') $izin++; elseif($status=='alpha') $alpha++;
                    echo "<td>".status_badge($status)."</td>";
                endforeach; ?>
                <td style="text-align:center"><?= $hadir ?></td><td style="text-align:center"><?= $sakit ?></td><td style="text-align:center"><?= $izin ?></td><td style="text-align:center"><?= $alpha ?></td>
            </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php include '../includes/footer.php'; 