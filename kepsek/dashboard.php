<?php
include '../config.php';
cek_login([4]);
$title = 'Dashboard Kepala Sekolah';
include '../includes/header.php';

$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

// Ambil tahun_ajaran_id dengan aman
$ta_id = 0;
$stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1 LIMIT 1");
$stmt_ta->bind_param("s", $tahun_aktif);
$stmt_ta->execute();
$res_ta = $stmt_ta->get_result();
if ($res_ta->num_rows > 0) {
    $ta_id = $res_ta->fetch_assoc()['id'];
}

// Statistik
$total_siswa = $conn->query("SELECT COUNT(*) as total FROM siswa")->fetch_assoc()['total'];
$total_guru  = $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id=2")->fetch_assoc()['total'];
$total_kelas = $conn->query("SELECT COUNT(*) as total FROM kelas")->fetch_assoc()['total'];
$total_mapel = $conn->query("SELECT COUNT(*) as total FROM mata_pelajaran")->fetch_assoc()['total'];

// Rata-rata nilai (hanya jika ada ta_id)
$rata_nilai = 0;
if ($ta_id > 0) {
    $res_nilai = $conn->query("SELECT AVG(rata_akhir) as rata FROM nilai_akhir WHERE semester='$semester_aktif' AND tahun_ajaran_id=$ta_id");
    if ($res_nilai && $res_nilai->num_rows > 0) {
        $rata_nilai = round($res_nilai->fetch_assoc()['rata'] ?? 0, 2);
    }
}

// Kehadiran bulan ini
$bulan_ini = date('Y-m');
$hadir_month = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE status='hadir' AND DATE_FORMAT(tanggal, '%Y-%m')='$bulan_ini'")->fetch_assoc()['total'];
$total_absen_month = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE DATE_FORMAT(tanggal, '%Y-%m')='$bulan_ini'")->fetch_assoc()['total'];
$persen_hadir = $total_absen_month > 0 ? round(($hadir_month / $total_absen_month) * 100) : 0;

// Grafik 30 hari
$labels = $hadir_data = $sakit_data = $izin_data = $alpha_data = [];
for ($i = 29; $i >= 0; $i--) {
    $tanggal = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($tanggal));
    $query = "SELECT 
                SUM(CASE WHEN status='hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status='sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN status='izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status='alpha' THEN 1 ELSE 0 END) as alpha
              FROM absensi WHERE tanggal='$tanggal'";
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    $hadir_data[] = (int)$row['hadir'];
    $sakit_data[] = (int)$row['sakit'];
    $izin_data[] = (int)$row['izin'];
    $alpha_data[] = (int)$row['alpha'];
}

// Top absen
$top_absen = $conn->query("SELECT s.nis, u.nama_lengkap as nama,
    SUM(CASE WHEN a.status='sakit' THEN 1 ELSE 0 END) as sakit,
    SUM(CASE WHEN a.status='izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN a.status='alpha' THEN 1 ELSE 0 END) as alpha
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.id
    ORDER BY (SUM(CASE WHEN a.status='sakit' THEN 1 ELSE 0 END) + 
              SUM(CASE WHEN a.status='izin' THEN 1 ELSE 0 END) + 
              SUM(CASE WHEN a.status='alpha' THEN 1 ELSE 0 END)) DESC
    LIMIT 5");

// Top tugas
$top_tugas = $conn->query("SELECT s.nis, u.nama_lengkap as nama, 
    COUNT(DISTINCT t.id) as total_tugas,
    SUM(CASE WHEN pt.id IS NULL THEN 1 ELSE 0 END) as belum_kumpul
    FROM tugas t
    JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
    JOIN siswa s ON s.kelas_id = km.kelas_id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = s.id
    WHERE km.semester = '$semester_aktif' AND km.tahun_ajaran_id = $ta_id
    GROUP BY s.id
    ORDER BY belum_kumpul DESC
    LIMIT 5");

// Pengumuman
$pengumuman = $conn->query("SELECT p.*, u.nama_lengkap as penulis 
    FROM pengumuman p 
    JOIN users u ON p.created_by = u.id 
    ORDER BY p.created_at DESC LIMIT 5");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.pengumuman-item {
    background: #f8fafc;
    border-left: 4px solid var(--primary-500);
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    border-radius: 0.5rem;
}
.top-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--gray-200);
    padding: 0.5rem 0;
}
.top-item-name { font-weight: 500; }
.top-item-stats { font-size: 0.7rem; color: var(--gray-600); }
.badge-sakit-sm { background: #fed7aa; color: #9b2c1d; padding: 2px 6px; border-radius: 12px; }
.badge-izin-sm { background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 12px; }
.badge-alpha-sm { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 12px; }
.badge-tugas { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 12px; }
</style>

<div class="page-header">
    <h2 class="page-title">Dashboard Kepala Sekolah</h2>
    <p class="page-subtitle">Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?> | Semester <?= $semester_aktif == 1 ? 'Ganjil' : 'Genap' ?> <?= $tahun_aktif ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div><h3>Total Siswa</h3><div class="stat-number"><?= $total_siswa ?></div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div><div><h3>Total Guru</h3><div class="stat-number"><?= $total_guru ?></div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-school"></i></div><div><h3>Total Kelas</h3><div class="stat-number"><?= $total_kelas ?></div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-book"></i></div><div><h3>Mata Pelajaran</h3><div class="stat-number"><?= $total_mapel ?></div></div></div>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(2,1fr);">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div><h3>Rata-rata Nilai (Semester <?= $semester_aktif ?>)</h3><div class="stat-number"><?= $rata_nilai ?></div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div><h3>Kehadiran Bulan <?= date('F') ?></h3><div class="stat-number"><?= $persen_hadir ?>%</div><div>Hadir <?= $hadir_month ?> dari <?= $total_absen_month ?> absensi</div></div></div>
</div>

<!-- Dua kolom: Grafik Kehadiran (kiri) dan Pengumuman Terbaru (kanan) -->
<div class="form-row">
    <div class="form-container">
        <div class="form-title"><i class="fas fa-chart-line"></i> Grafik Kehadiran 30 Hari Terakhir</div>
        <canvas id="kehadiranChart" style="width:100%; max-height: 350px;"></canvas>
    </div>
    <div class="form-container">
        <div class="form-title"><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</div>
        <div style="max-height: 350px; overflow-y: auto;">
            <?php if($pengumuman && $pengumuman->num_rows > 0): ?>
                <?php while($p = $pengumuman->fetch_assoc()): ?>
                <div class="pengumuman-item">
                    <h4 style="margin:0 0 5px;"><?= htmlspecialchars($p['judul']) ?></h4>
                    <small><?= tgl_indonesia($p['created_at']) ?> - oleh <?= htmlspecialchars($p['penulis']) ?></small>
                    <p><?= nl2br(htmlspecialchars($p['isi'])) ?></p>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Belum ada pengumuman.</p>
            <?php endif; ?>
        </div>
        <div class="btn-group" style="margin-top: 1rem;">
            <a href="../admin/pengumuman.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle"></i> Kelola Pengumuman</a>
        </div>
    </div>
</div>

<!-- Dua kolom: Ketidakhadiran vs Tugas Belum Dikumpul -->
<div class="form-row">
    <div class="form-container">
        <div class="form-title"><i class="fas fa-user-times"></i> Siswa dengan Ketidakhadiran Terbanyak (30 hari)</div>
        <?php if($top_absen && $top_absen->num_rows > 0): ?>
            <?php while($row = $top_absen->fetch_assoc()): 
                $total = $row['sakit'] + $row['izin'] + $row['alpha'];
            ?>
            <div class="top-item">
                <div>
                    <div class="top-item-name"><?= htmlspecialchars($row['nama']) ?> (<?= $row['nis'] ?>)</div>
                    <div class="top-item-stats">
                        <?php if($row['sakit'] > 0): ?><span class="badge-sakit-sm">Sakit <?= $row['sakit'] ?></span><?php endif; ?>
                        <?php if($row['izin'] > 0): ?><span class="badge-izin-sm">Izin <?= $row['izin'] ?></span><?php endif; ?>
                        <?php if($row['alpha'] > 0): ?><span class="badge-alpha-sm">Alpha <?= $row['alpha'] ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="stat-number" style="font-size: 1.2rem;"><?= $total ?></div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Belum ada data ketidakhadiran dalam 30 hari.</p>
        <?php endif; ?>
    </div>
    <div class="form-container">
        <div class="form-title"><i class="fas fa-tasks"></i> Siswa Paling Banyak Belum Mengumpulkan Tugas</div>
        <?php if($top_tugas && $top_tugas->num_rows > 0): ?>
            <?php while($row = $top_tugas->fetch_assoc()): ?>
            <div class="top-item">
                <div>
                    <div class="top-item-name"><?= htmlspecialchars($row['nama']) ?> (<?= $row['nis'] ?>)</div>
                    <div class="top-item-stats">
                        <span class="badge-tugas">Belum kumpul <?= $row['belum_kumpul'] ?> dari <?= $row['total_tugas'] ?> tugas</span>
                    </div>
                </div>
                <div class="stat-number" style="font-size: 1.2rem;"><?= $row['belum_kumpul'] ?></div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Belum ada data tugas untuk semester ini.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('kehadiranChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                { label: 'Hadir', data: <?= json_encode($hadir_data) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.2, fill: true },
                { label: 'Sakit', data: <?= json_encode($sakit_data) ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', tension: 0.2, fill: true },
                { label: 'Izin', data: <?= json_encode($izin_data) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', tension: 0.2, fill: true },
                { label: 'Alpha', data: <?= json_encode($alpha_data) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', tension: 0.2, fill: true }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Jumlah Siswa' } }, x: { ticks: { maxRotation: 45, autoSkip: true } } }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>