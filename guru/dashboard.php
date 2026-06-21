<?php
include '../config.php';
cek_login([2]);
$title = 'Dashboard Guru';
include '../includes/header.php';

$guru_id        = $_SESSION['user_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$bulan_ini      = date('Y-m');

// ========== PENUGASAN GURU ==========
$kelas_mapel_list  = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);
$total_kelas_mapel = $kelas_mapel_list ? $kelas_mapel_list->num_rows : 0;

// ========== HANDLER ERROR: BELUM ADA PENUGASAN ==========
if ($total_kelas_mapel == 0) {
    set_flash('warning', '<strong>Peringatan!</strong> Anda belum memiliki penugasan (Kelas & Mata Pelajaran) pada semester ini. Silakan hubungi Administrator untuk pengaturan lebih lanjut.');
}

// ========== TOTAL SISWA DIAJAR (prepared statement) ==========
$stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun=? AND is_active=1 LIMIT 1");
$stmt_ta->bind_param("s", $tahun_aktif);
$stmt_ta->execute();
$ta_row = $stmt_ta->get_result()->fetch_assoc();
$ta_id  = $ta_row['id'] ?? 0;

$total_siswa = 0;
if ($ta_id) {
    $stmt_ts = $conn->prepare(
        "SELECT COUNT(DISTINCT s.id) as total FROM siswa s
         JOIN kelas_mapel km ON s.kelas_id = km.kelas_id
         WHERE km.guru_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?"
    );
    $stmt_ts->bind_param("iis", $guru_id, $ta_id, $semester_aktif);
    $stmt_ts->execute();
    $total_siswa = $stmt_ts->get_result()->fetch_assoc()['total'] ?? 0;
}

// ========== TUGAS BELUM DINILAI ==========
$stmt_tn = $conn->prepare(
    "SELECT COUNT(DISTINCT pt.id) as total
     FROM pengumpulan_tugas pt
     JOIN tugas t ON pt.tugas_id = t.id
     JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
     WHERE km.guru_id = ? AND pt.nilai IS NULL"
);
$stmt_tn->bind_param("i", $guru_id);
$stmt_tn->execute();
$tugas_belum_nilai = $stmt_tn->get_result()->fetch_assoc()['total'] ?? 0;

// ========== DATA KEHADIRAN BULAN INI ==========
$hadir = $sakit = $izin = $alpha = 0;
if ($total_siswa > 0 && $ta_id) {
    // Ambil siswa_id list milik guru ini
    $stmt_sid = $conn->prepare(
        "SELECT DISTINCT s.id FROM siswa s
         JOIN kelas_mapel km ON s.kelas_id = km.kelas_id
         WHERE km.guru_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?"
    );
    $stmt_sid->bind_param("iis", $guru_id, $ta_id, $semester_aktif);
    $stmt_sid->execute();
    $sid_res  = $stmt_sid->get_result();
    $siswa_ids = [];
    while ($r = $sid_res->fetch_assoc()) $siswa_ids[] = (int)$r['id'];

    if ($siswa_ids) {
        // IN clause dengan placeholder dinamis — aman
        $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
        $types        = str_repeat('i', count($siswa_ids)) . 's';
        $params       = array_merge($siswa_ids, [$bulan_ini]);

        $stmt_ab = $conn->prepare(
            "SELECT status, COUNT(*) as total FROM absensi
             WHERE siswa_id IN ($placeholders) AND DATE_FORMAT(tanggal,'%Y-%m') = ?
             GROUP BY status"
        );
        $stmt_ab->bind_param($types, ...$params);
        $stmt_ab->execute();
        $ab_res = $stmt_ab->get_result();
        while ($ab = $ab_res->fetch_assoc()) {
            match ($ab['status']) {
                'hadir' => $hadir = $ab['total'],
                'sakit' => $sakit = $ab['total'],
                'izin'  => $izin  = $ab['total'],
                'alpha' => $alpha = $ab['total'],
                default => null,
            };
        }
    }
}

// ========== PENGUMUMAN ==========
$kelas_ids = [];
if ($kelas_mapel_list && $kelas_mapel_list->num_rows > 0) {
    $kelas_mapel_list->data_seek(0);
    while ($km2 = $kelas_mapel_list->fetch_assoc()) $kelas_ids[] = (int)$km2['kelas_id'];
    $kelas_mapel_list->data_seek(0);
}

if ($kelas_ids) {
    $ph   = implode(',', array_fill(0, count($kelas_ids), '?'));
    $types_p = str_repeat('i', count($kelas_ids));
    $stmt_p = $conn->prepare(
        "SELECT p.*, u.nama_lengkap as penulis FROM pengumuman p
         JOIN users u ON p.created_by = u.id
         WHERE p.target = 'semua'
            OR (p.target = 'kelas' AND p.target_kelas IN ($ph))
         ORDER BY p.created_at DESC LIMIT 5"
    );
    $stmt_p->bind_param($types_p, ...$kelas_ids);
    $stmt_p->execute();
    $pengumuman = $stmt_p->get_result();
} else {
    $pengumuman = $conn->query(
        "SELECT p.*, u.nama_lengkap as penulis FROM pengumuman p
         JOIN users u ON p.created_by = u.id
         WHERE p.target = 'semua'
         ORDER BY p.created_at DESC LIMIT 5"
    );
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-chalkboard-user"></i> Dashboard Guru</h2>
    <p class="page-subtitle">
        Selamat datang, <?= e($_SESSION['nama']) ?> &mdash;
        TA <?= e($tahun_aktif) ?> Semester <?= $semester_aktif == '1' ? 'Ganjil' : 'Genap' ?>
    </p>
</div>

<?php if ($total_kelas_mapel == 0): ?>
<div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:1rem; margin-bottom:1rem; border-radius:8px;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian!</strong>
    Anda belum ditugaskan untuk mengajar pada tahun ajaran dan semester ini.
    Silakan hubungi administrator.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
        <div><h3>Kelas &amp; Mapel Diampu</h3><div class="stat-number"><?= $total_kelas_mapel ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div><h3>Total Siswa Diajar</h3><div class="stat-number"><?= $total_siswa ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-tasks"></i></div>
        <div><h3>Tugas Belum Dinilai</h3><div class="stat-number"><?= $tugas_belum_nilai ?></div></div>
    </div>
</div>

<div class="form-row">
    <!-- Kelas & Mapel yang Diampu -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-book"></i> Kelas &amp; Mata Pelajaran Diampu</div>
        <?php if ($total_kelas_mapel > 0): ?>
        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr><th>Kelas</th><th>Mata Pelajaran</th><th>Semester</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php $kelas_mapel_list->data_seek(0);
                    while ($km = $kelas_mapel_list->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($km['nama_kelas']) ?></td>
                        <td><?= e($km['nama_mapel']) ?></td>
                        <td><?= $km['semester'] == '1' ? 'Ganjil' : 'Genap' ?></td>
                        <td style="white-space:wrap;">
                            <a href="materi?kelas_mapel_id=<?= $km['id'] ?>" class="btn btn-sm btn-primary" title="Materi"><i class="fas fa-book"></i></a>
                            <a href="tugas?kelas_mapel_id=<?= $km['id'] ?>" class="btn btn-sm btn-outline" title="Tugas"><i class="fas fa-tasks"></i></a>
                            <a href="absensi?kelas_mapel_id=<?= $km['id'] ?>" class="btn btn-sm btn-outline" title="Absensi"><i class="fas fa-calendar-check"></i></a>
                            <a href="rekap_nilai?kelas_mapel_id=<?= $km['id'] ?>" class="btn btn-sm btn-outline" title="Nilai"><i class="fas fa-chart-pie"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="color:var(--gray-500);">Belum ada kelas/mapel yang diampu semester ini.</p>
        <?php endif; ?>
    </div>

    <!-- Pengumuman -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</div>
        <div style="max-height:300px; overflow-y:auto;">
            <?php if ($pengumuman && $pengumuman->num_rows > 0):
                while ($p = $pengumuman->fetch_assoc()): ?>
                <div style="border-left:4px solid var(--primary-500); padding:0.6rem 0.75rem; margin-bottom:0.6rem; background:#f8fafc; border-radius:0 0.5rem 0.5rem 0;">
                    <strong><?= e($p['judul']) ?></strong>
                    <div style="font-size:0.72rem; color:var(--gray-500); margin:3px 0;">
                        <?= tgl_indonesia($p['created_at']) ?> &mdash; <?= e($p['penulis']) ?>
                    </div>
                    <div style="font-size:0.85rem;"><?= nl2br(e($p['isi'])) ?></div>
                </div>
            <?php endwhile;
            else: ?>
                <p style="color:var(--gray-500);">Belum ada pengumuman.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Grafik Kehadiran -->
<?php if ($total_siswa > 0): ?>
<div class="form-container">
    <div class="form-title"><i class="fas fa-chart-bar"></i> Kehadiran Siswa — <?= date('F Y') ?></div>
    <div style="max-width:480px; margin:0 auto;">
        <canvas id="attendanceChart"></canvas>
    </div>
    <div class="stats-grid" style="margin-top:1rem; grid-template-columns:repeat(4,1fr); text-align:center;">
        <div><span class="badge-hadir">Hadir</span><br><strong><?= $hadir ?></strong></div>
        <div><span class="badge-sakit">Sakit</span><br><strong><?= $sakit ?></strong></div>
        <div><span class="badge-izin">Izin</span><br><strong><?= $izin ?></strong></div>
        <div><span class="badge-alpha">Alpha</span><br><strong><?= $alpha ?></strong></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('attendanceChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Hadir', 'Sakit', 'Izin', 'Alpha'],
            datasets: [{
                label: 'Jumlah',
                data: [<?= $hadir ?>, <?= $sakit ?>, <?= $izin ?>, <?= $alpha ?>],
                backgroundColor: ['#22c55e','#f59e0b','#3b82f6','#ef4444'],
                borderRadius: 8,
                barPercentage: 0.55
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Jumlah' } } }
        }
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>