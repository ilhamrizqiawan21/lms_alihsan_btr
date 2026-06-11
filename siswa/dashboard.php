<?php
include '../config.php';
cek_login([3]);
$title = 'Dashboard Siswa';
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// ✅ Ambil data siswa dengan prepared statement
$stmt_s = $conn->prepare(
    "SELECT s.id, s.nis, s.kelas_id, k.nama_kelas
     FROM siswa s JOIN kelas k ON s.kelas_id = k.id
     WHERE s.user_id = ? LIMIT 1"
);
$stmt_s->bind_param("i", $user_id);
$stmt_s->execute();
$siswa = $stmt_s->get_result()->fetch_assoc();

if (!$siswa) {
    echo '<div style="padding:2rem;text-align:center;color:#dc2626;">Data siswa tidak ditemukan. Hubungi administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$siswa_id  = $siswa['id'];
$kelas_id  = $siswa['kelas_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$bulan_ini = date('Y-m');

// ✅ Ambil tahun_ajaran_id
$stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun=? AND is_active=1 LIMIT 1");
$stmt_ta->bind_param("s", $tahun_aktif);
$stmt_ta->execute();
$ta_row = $stmt_ta->get_result()->fetch_assoc();
$ta_id  = $ta_row['id'] ?? 0;

// ✅ Tugas belum dikumpul
$belum_kumpul = 0;
if ($ta_id) {
    $stmt_tk = $conn->prepare(
        "SELECT COUNT(*) as total FROM tugas t
         JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
         LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
         WHERE km.kelas_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?
           AND (pt.id IS NULL OR pt.status != 'sudah')"
    );
    $stmt_tk->bind_param("iiis", $siswa_id, $kelas_id, $ta_id, $semester_aktif);
    $stmt_tk->execute();
    $belum_kumpul = $stmt_tk->get_result()->fetch_assoc()['total'] ?? 0;
}

// ✅ Kehadiran bulan ini
$stmt_ab = $conn->prepare(
    "SELECT status, COUNT(*) as total FROM absensi
     WHERE siswa_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ?
     GROUP BY status"
);
$stmt_ab->bind_param("is", $siswa_id, $bulan_ini);
$stmt_ab->execute();
$ab_res = $stmt_ab->get_result();
$hadir = $sakit = $izin = $alpha = 0;
while ($ab = $ab_res->fetch_assoc()) {
    match($ab['status']) {
        'hadir' => $hadir = $ab['total'],
        'sakit' => $sakit = $ab['total'],
        'izin'  => $izin  = $ab['total'],
        'alpha' => $alpha = $ab['total'],
        default => null,
    };
}
$total_absen  = $hadir + $sakit + $izin + $alpha;
$persen_hadir = $total_absen > 0 ? round(($hadir / $total_absen) * 100) : 0;

// ✅ Pengumuman
$stmt_peng = $conn->prepare(
    "SELECT p.*, u.nama_lengkap as penulis FROM pengumuman p
     JOIN users u ON p.created_by = u.id
     WHERE p.target = 'semua'
        OR (p.target = 'kelas' AND p.target_kelas = ?)
     ORDER BY p.created_at DESC LIMIT 5"
);
$stmt_peng->bind_param("i", $kelas_id);
$stmt_peng->execute();
$pengumuman = $stmt_peng->get_result();

// ✅ Tugas terbaru (5 tugas + status pengumpulan siswa ini)
$tugas_terbaru = null;
if ($ta_id) {
    $stmt_tug = $conn->prepare(
        "SELECT t.id, t.judul, t.batas_waktu, mp.nama_mapel,
                pt.status as status_kumpul, pt.nilai
         FROM tugas t
         JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
         LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
         WHERE km.kelas_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?
         ORDER BY t.created_at DESC LIMIT 5"
    );
    $stmt_tug->bind_param("iiis", $siswa_id, $kelas_id, $ta_id, $semester_aktif);
    $stmt_tug->execute();
    $tugas_terbaru = $stmt_tug->get_result();
}
?>

<style>
.progress-bar-wrap { background:#e5e7eb; border-radius:20px; height:10px; margin-top:6px; }
.progress-bar-fill { background:linear-gradient(90deg, var(--primary-500), var(--primary-600)); border-radius:20px; height:10px; transition:width 0.6s ease; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-home"></i> Dashboard Siswa</h2>
    <p class="page-subtitle">
        Selamat datang, <?= e($_SESSION['nama']) ?> &mdash;
        Kelas <?= e($siswa['nama_kelas']) ?> &mdash;
        TA <?= e($tahun_aktif) ?> Semester <?= $semester_aktif == '1' ? 'Ganjil' : 'Genap' ?>
    </p>
    <div style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.75rem;">
        <a href="progress.php" class="btn btn-primary"><i class="fas fa-chart-line"></i> Lihat Progress Belajar</a>
        <a href="calendar.php" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> Kalender & Reminder</a>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-tasks"></i></div>
        <div>
            <h3>Tugas Belum Dikumpul</h3>
            <div class="stat-number" style="color:<?= $belum_kumpul > 0 ? '#dc2626' : 'inherit' ?>">
                <?= $belum_kumpul ?>
            </div>
            <?php if ($belum_kumpul > 0): ?>
            <small style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> Segera kumpulkan!</small>
            <?php else: ?>
            <small style="color:#16a34a;"><i class="fas fa-check-circle"></i> Semua sudah dikumpul</small>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div style="width:100%;">
            <h3>Kehadiran <?= date('F') ?></h3>
            <div class="stat-number"><?= $persen_hadir ?>%</div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:<?= $persen_hadir ?>%"></div>
            </div>
            <small style="color:var(--gray-500); margin-top:4px; display:block;">
                H:<?= $hadir ?> S:<?= $sakit ?> I:<?= $izin ?> A:<?= $alpha ?>
            </small>
        </div>
    </div>
</div>

<div class="form-row">
    <!-- Pengumuman -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-bullhorn"></i> Pengumuman</div>
        <?php if ($pengumuman->num_rows > 0):
            while ($p = $pengumuman->fetch_assoc()): ?>
            <div style="border-left:4px solid var(--primary-500); padding:0.6rem 0.75rem; margin-bottom:0.6rem; background:#f8fafc; border-radius:0 0.5rem 0.5rem 0;">
                <strong><?= e($p['judul']) ?></strong>
                <div style="font-size:0.72rem; color:var(--gray-500); margin:2px 0;">
                    <?= tgl_indonesia($p['created_at']) ?>
                    <?php if (!empty($p['penulis'])): ?>
                        &mdash; <?= e($p['penulis']) ?>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.85rem;"><?= nl2br(e($p['isi'])) ?></div>
            </div>
        <?php endwhile;
        else: ?>
            <p style="color:var(--gray-500);">Belum ada pengumuman.</p>
        <?php endif; ?>
    </div>

    <!-- Tugas Terbaru -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-tasks"></i> Tugas Terbaru</div>
        <?php if ($tugas_terbaru && $tugas_terbaru->num_rows > 0): ?>
        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Mata Pelajaran</th>
                        <th>Judul Tugas</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Nilai</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($t = $tugas_terbaru->fetch_assoc()):
                        $lewat_deadline = $t['batas_waktu'] && strtotime($t['batas_waktu']) < time();
                    ?>
                    <tr>
                        <td><?= e($t['nama_mapel']) ?></td>
                        <td><strong><?= e($t['judul']) ?></strong></td>
                        <td style="white-space:nowrap;">
                            <?= $t['batas_waktu'] ? date('d/m/Y H:i', strtotime($t['batas_waktu'])) : '-' ?>
                            <?php if ($lewat_deadline && $t['status_kumpul'] !== 'sudah'): ?>
                                <br><small style="color:#dc2626;">Lewat deadline!</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['status_kumpul'] === 'sudah'): ?>
                                <span class="badge-hadir"><i class="fas fa-check"></i> Sudah</span>
                            <?php else: ?>
                                <span class="badge-alpha"><i class="fas fa-times"></i> Belum</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $t['nilai'] !== null ? $t['nilai'] : '-' ?></strong></td>
                        <td>
                            <a href="tugas_saya" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem; text-align:right;">
            <a href="tugas_saya" class="btn btn-outline">Lihat Semua Tugas <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php else: ?>
            <p style="color:var(--gray-500);">Belum ada tugas untuk semester ini.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>