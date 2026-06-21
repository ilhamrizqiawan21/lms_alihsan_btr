<?php
include '../config.php';
cek_login([3]);
$title = 'Progress Siswa';
include '../includes/header.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT s.id, s.nis, k.nama_kelas, k.tingkat 
     FROM siswa s 
     LEFT JOIN kelas k ON s.kelas_id = k.id 
     WHERE s.user_id = ? LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();

if (!$siswa) {
    echo '<div style="padding:2rem;text-align:center;color:#dc2626;">Data siswa tidak ditemukan. Hubungi administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$siswa_id = $siswa['id'];
$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$periode_bulan = date('Y-m');

$gpa = get_student_gpa($conn, $siswa_id);
$attendance = get_student_attendance_summary($conn, $siswa_id, $periode_bulan);
$assignment_rate = get_student_assignment_rate($conn, $siswa_id);
$subject_scores = get_student_subject_scores($conn, $siswa_id);
$grade_trend = get_student_grade_trend($conn, $siswa_id, null, 8);

$subject_labels = array_column($subject_scores, 'nama_mapel');
$subject_data = array_column($subject_scores, 'avg_score');
$trend_labels = array_map(fn($item) => date('d M', strtotime($item['date'])), $grade_trend);
$trend_scores = array_column($grade_trend, 'score');

?>

<style>
.progress-box { display:flex; flex-direction:column; gap:0.5rem; padding:1rem; background:#f8fafc; border-radius:14px; border:1px solid #e5e7eb; }
.progress-score { font-size:2.25rem; font-weight:700; margin:0; }
.progress-subtitle { color:var(--gray-500); }
.chart-card { min-height:320px; }
.detail-table th, .detail-table td { white-space: nowrap; }
.subject-trend-list { list-style:none; padding:0; margin:0; display:grid; gap:0.75rem; }
.subject-trend-item { display:flex; justify-content:space-between; align-items:center; padding:0.9rem 1rem; border-radius:12px; border:1px solid #e5e7eb; background:#ffffff; }
.subject-trend-item .trend-meta { color:var(--gray-500); font-size:0.9rem; }
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-chart-line"></i> Progress Belajar</h2>
    <p class="page-subtitle">
        <?= e($_SESSION['nama']) ?> &mdash; Kelas <?= e($siswa['nama_kelas'] ?? 'Belum dimasukkan') ?> &mdash; TA <?= e($tahun_aktif) ?> Semester <?= $semester_aktif == '1' ? 'Ganjil' : 'Genap' ?>
    </p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div>
            <h3>Rata-rata Nilai</h3>
            <div class="stat-number"><?= $gpa ?: '0.00' ?></div>
            <small>Nilai rata-rata berdasarkan tahun ajaran aktif</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
            <h3>Kehadiran <?= date('F Y', strtotime($periode_bulan . '-01')) ?></h3>
            <div class="stat-number"><?= $attendance['percent'] ?>%</div>
            <small>Hadir <?= $attendance['hadir'] ?> dari <?= $attendance['total'] ?> hari</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
        <div>
            <h3>Penyelesaian Tugas</h3>
            <div class="stat-number"><?= $assignment_rate ?>%</div>
            <small>Progress pengumpulan tugas semester ini</small>
        </div>
    </div>
</div>

<div class="form-row">
    <div class="form-container chart-card">
        <div class="form-title"><i class="fas fa-book"></i> Rata-rata Nilai per Mapel</div>
        <?php if (!empty($subject_scores)): ?>
            <canvas id="subjectAverageChart"></canvas>
        <?php else: ?>
            <p style="color:var(--gray-500);">Belum ada data nilai untuk tahun ajaran aktif.</p>
        <?php endif; ?>
    </div>

    <div class="form-container chart-card">
        <div class="form-title"><i class="fas fa-chart-line"></i> Trend Nilai Terakhir</div>
        <?php if (!empty($grade_trend)): ?>
            <canvas id="gradeTrendChart"></canvas>
        <?php else: ?>
            <p style="color:var(--gray-500);">Belum ada catatan nilai terakhir.</p>
        <?php endif; ?>
    </div>
</div>

<div class="form-row">
    <div class="form-container">
        <div class="form-title"><i class="fas fa-list"></i> Detail Nilai per Mapel</div>
        <?php if (!empty($subject_scores)): ?>
        <div class="table-wrapper">
            <table class="modern-table detail-table">
                <thead>
                    <tr>
                        <th>Mata Pelajaran</th>
                        <th>Rata-rata</th>
                        <th>Jumlah Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subject_scores as $mapel): ?>
                    <tr>
                        <td><?= e($mapel['nama_mapel']) ?></td>
                        <td><?= e(number_format($mapel['avg_score'], 2)) ?></td>
                        <td><?= e($mapel['num_grades']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="color:var(--gray-500);">Tidak ada data nilai yang dapat ditampilkan.</p>
        <?php endif; ?>
    </div>

    <div class="form-container">
        <div class="form-title"><i class="fas fa-bell"></i> Catatan Nilai Terakhir</div>
        <?php if (!empty($grade_trend)): ?>
        <ul class="subject-trend-list">
            <?php foreach (array_reverse($grade_trend) as $item): ?>
                <li class="subject-trend-item">
                    <div>
                        <strong><?= e($item['subject']) ?></strong>
                        <div class="trend-meta"><?= e(date('d M Y', strtotime($item['date']))) ?></div>
                    </div>
                    <div><strong><?= e(number_format($item['score'], 1)) ?></strong></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
            <p style="color:var(--gray-500);">Belum ada nilai terbaru.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($subject_scores)): ?>
        new Chart(document.getElementById('subjectAverageChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($subject_labels) ?>,
                datasets: [{
                    label: 'Rata-rata Nilai',
                    data: <?= json_encode($subject_data) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    borderRadius: 10,
                    barThickness: 32
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, max: 100, title: { display: true, text: 'Nilai' } },
                    x: { ticks: { autoSkip: false } }
                },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.formattedValue + ' / 100' } } }
            }
        });
        <?php endif; ?>

        <?php if (!empty($grade_trend)): ?>
        new Chart(document.getElementById('gradeTrendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    label: 'Nilai Terakhir',
                    data: <?= json_encode($trend_scores) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 6,
                    pointBackgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, suggestedMax: 100, title: { display: true, text: 'Nilai' } },
                    x: { title: { display: true, text: 'Tanggal' } }
                }
            }
        });
        <?php endif; ?>
    });
</script>

<?php include '../includes/footer.php'; ?>
