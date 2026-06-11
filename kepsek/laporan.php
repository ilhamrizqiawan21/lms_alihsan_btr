<?php
include '../config.php';
cek_login([4]);
$title = 'Laporan Lengkap Kepala Sekolah';
include '../includes/header.php';

$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$ta_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM tahun_ajaran WHERE tahun='$tahun_aktif' AND is_active=1"))['id'] ?? 0;

$bulan_pilih = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

// ==================== 1. LAPORAN KEHADIRAN PER BULAN ====================
$query_absensi = "SELECT k.nama_kelas, 
                  COUNT(a.id) as total_absen,
                  SUM(CASE WHEN a.status='hadir' THEN 1 ELSE 0 END) as hadir,
                  SUM(CASE WHEN a.status='sakit' THEN 1 ELSE 0 END) as sakit,
                  SUM(CASE WHEN a.status='izin' THEN 1 ELSE 0 END) as izin,
                  SUM(CASE WHEN a.status='alpha' THEN 1 ELSE 0 END) as alpha
                  FROM absensi a
                  JOIN siswa s ON a.siswa_id = s.id
                  JOIN kelas k ON s.kelas_id = k.id
                  WHERE DATE_FORMAT(a.tanggal, '%Y-%m') = '$bulan_pilih'
                  GROUP BY k.id
                  ORDER BY k.nama_kelas";
$rekap_absensi = mysqli_query($conn, $query_absensi);

// ==================== 2. TUGAS & PERSENTASE PENGUMPULAN ====================
// Gunakan kelas_mapel sebagai basis, hitung tugas dan pengumpulan
$query_tugas = "SELECT k.nama_kelas, mp.nama_mapel, 
                COUNT(DISTINCT t.id) as total_tugas,
                (SELECT COUNT(*) FROM pengumpulan_tugas pt 
                 WHERE pt.tugas_id IN (SELECT id FROM tugas WHERE kelas_mapel_id = km.id)
                ) as sudah_kumpul,
                (SELECT COUNT(*) FROM siswa WHERE kelas_id = k.id) as total_siswa
                FROM kelas_mapel km
                JOIN kelas k ON km.kelas_id = k.id
                JOIN mata_pelajaran mp ON km.mapel_id = mp.id
                LEFT JOIN tugas t ON t.kelas_mapel_id = km.id
                WHERE km.tahun_ajaran_id = $ta_id AND km.semester = '$semester_aktif'
                GROUP BY km.id
                ORDER BY k.nama_kelas, mp.nama_mapel";
$tugas_data = mysqli_query($conn, $query_tugas);

// Hitung total keseluruhan
$total_target = 0;
$total_kumpul = 0;
$tugas_reset = mysqli_query($conn, $query_tugas);
while($row = mysqli_fetch_assoc($tugas_reset)) {
    $target = $row['total_tugas'] * $row['total_siswa'];
    $total_target += $target;
    $total_kumpul += $row['sudah_kumpul'];
}
$persen_all = $total_target > 0 ? round(($total_kumpul / $total_target) * 100) : 0;
mysqli_data_seek($tugas_data, 0);

// ==================== 3. RATA-RATA NILAI PER KELAS ====================
$query_rata_kelas = "SELECT k.nama_kelas, AVG(na.rata_akhir) as rata_kelas
                     FROM nilai_akhir na
                     JOIN siswa s ON na.siswa_id = s.id
                     JOIN kelas k ON s.kelas_id = k.id
                     WHERE na.semester='$semester_aktif' AND na.tahun_ajaran_id=$ta_id
                     GROUP BY k.id
                     ORDER BY rata_kelas DESC";
$rata_kelas = mysqli_query($conn, $query_rata_kelas);

// ==================== 4. RATA-RATA MATA PELAJARAN TERKECIL ====================
$query_mapel_terendah = "SELECT mp.nama_mapel, AVG(na.rata_akhir) as rata_mapel
                         FROM nilai_akhir na
                         JOIN kelas_mapel km ON na.kelas_mapel_id = km.id
                         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
                         WHERE na.semester='$semester_aktif' AND na.tahun_ajaran_id=$ta_id
                         GROUP BY mp.id
                         ORDER BY rata_mapel ASC
                         LIMIT 5";
$mapel_terendah = mysqli_query($conn, $query_mapel_terendah);

// ==================== 5. KETIDAKHADIRAN TERBANYAK PER MAPEL ====================
$query_ketidakhadiran = "SELECT mp.nama_mapel, 
                         COUNT(a.id) as total_tidak_hadir,
                         SUM(CASE WHEN a.status='sakit' THEN 1 ELSE 0 END) as sakit,
                         SUM(CASE WHEN a.status='izin' THEN 1 ELSE 0 END) as izin,
                         SUM(CASE WHEN a.status='alpha' THEN 1 ELSE 0 END) as alpha
                         FROM absensi a
                         JOIN kelas_mapel km ON a.kelas_mapel_id = km.id
                         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
                         WHERE a.status != 'hadir'
                         AND km.tahun_ajaran_id = $ta_id AND km.semester = '$semester_aktif'
                         GROUP BY mp.id
                         ORDER BY total_tidak_hadir DESC
                         LIMIT 5";
$ketidakhadiran_mapel = mysqli_query($conn, $query_ketidakhadiran);
?>

<style>
.laporan-section {
    margin-bottom: 2rem;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 1rem;
}
.laporan-section h3 {
    background: var(--primary-100);
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}
.stat-badge {
    background: #f0fdf4;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: inline-block;
}
</style>

<div class="page-header">
    <h2 class="page-title">Laporan Lengkap</h2>
    <p class="page-subtitle">Rekapitulasi data kehadiran, tugas, dan nilai - Semester <?= $semester_aktif == 1 ? 'Ganjil' : 'Genap' ?> <?= $tahun_aktif ?></p>
</div>

<!-- Filter Bulan -->
<div class="form-container">
    <div class="form-title">Filter Laporan Kehadiran</div>
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Bulan</label>
            <input type="month" name="bulan" value="<?= $bulan_pilih ?>" class="form-input">
        </div>
        <div class="form-group" style="display: flex; gap: 8px; align-items: flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            <button type="button" onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Cetak Semua</button>
        </div>
    </form>
</div>

<!-- 1. Laporan Kehadiran per Bulan -->
<div class="laporan-section">
    <h3><i class="fas fa-calendar-check"></i> 1. Laporan Kehadiran Bulan <?= date('F Y', strtotime($bulan_pilih)) ?></h3>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr><th>Kelas</th><th>Total Absensi</th><th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpha</th><th>% Kehadiran</th></tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($rekap_absensi) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($rekap_absensi)): 
                        $persen = $row['total_absen'] > 0 ? round(($row['hadir']/$row['total_absen'])*100) : 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        <td><?= $row['total_absen'] ?></td>
                        <td><?= $row['hadir'] ?></td>
                        <td><?= $row['sakit'] ?></td>
                        <td><?= $row['izin'] ?></td>
                        <td><?= $row['alpha'] ?></td>
                        <td><strong><?= $persen ?>%</strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7">Belum ada data absensi untuk bulan <?= date('F Y', strtotime($bulan_pilih)) ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 2. Jumlah Tugas & Persentase Pengumpulan -->
<div class="laporan-section">
    <h3><i class="fas fa-tasks"></i> 2. Jumlah Tugas & Persentase Pengumpulan</h3>
    <div class="stat-badge">
        <strong>Ringkasan Keseluruhan:</strong> 
        Total Target Pengumpulan: <?= $total_target ?> | 
        Total Sudah Dikumpul: <?= $total_kumpul ?> | 
        Persentase: <strong><?= $persen_all ?>%</strong>
    </div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr><th>Kelas</th><th>Mata Pelajaran</th><th>Jumlah Tugas</th><th>Sudah Dikumpul</th><th>Target (Tugas x Siswa)</th><th>Persentase</th></tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($tugas_data) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($tugas_data)): 
                        $target = $row['total_tugas'] * $row['total_siswa'];
                        $persen = $target > 0 ? round(($row['sudah_kumpul']/$target)*100) : 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                        <td><?= $row['total_tugas'] ?></td>
                        <td><?= $row['sudah_kumpul'] ?></td>
                        <td><?= $target ?></td>
                        <td><strong><?= $persen ?>%</strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">Belum ada data tugas untuk semester ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 3. Rata-rata Nilai per Kelas -->
<div class="laporan-section">
    <h3><i class="fas fa-chart-line"></i> 3. Rata-rata Nilai Akhir per Kelas</h3>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead><tr><th>Kelas</th><th>Rata-rata Nilai</th></tr></thead>
            <tbody>
                <?php if(mysqli_num_rows($rata_kelas) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($rata_kelas)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        <td><strong><?= round($row['rata_kelas'], 2) ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2">Belum ada data nilai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 4. Mata Pelajaran dengan Rata-rata Terkecil -->
<div class="laporan-section">
    <h3><i class="fas fa-chart-line"></i> 4. Mata Pelajaran dengan Rata-rata Nilai Terendah (Akumulasi Semua Kelas)</h3>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead><tr><th>Mata Pelajaran</th><th>Rata-rata Nilai</th></tr></thead>
            <tbody>
                <?php if(mysqli_num_rows($mapel_terendah) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($mapel_terendah)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                        <td><strong class="text-danger"><?= round($row['rata_mapel'], 2) ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <td><td colspan="2">Belum ada data nilai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 5. Ketidakhadiran Terbanyak per Mata Pelajaran -->
<div class="laporan-section">
    <h3><i class="fas fa-user-times"></i> 5. Mata Pelajaran dengan Jumlah Ketidakhadiran Terbanyak</h3>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Mata Pelajaran</th>
                    <th>Total Tidak Hadir</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Alpha</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($ketidakhadiran_mapel) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($ketidakhadiran_mapel)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                        <td><strong class="text-danger"><?= $row['total_tidak_hadir'] ?></strong></td>
                        <td><?= $row['sakit'] ?></td>
                        <td><?= $row['izin'] ?></td>
                        <td><?= $row['alpha'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">Belum ada data ketidakhadiran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; 