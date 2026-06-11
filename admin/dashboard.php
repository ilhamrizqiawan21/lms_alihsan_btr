<?php
include '../config.php';
cek_login([1]);

$title = 'Dashboard Admin - MTs Al-Ihsan';
include '../includes/header.php';

// ✅ Initialize database table if it doesn't exist (fallback for migration)
$check_widgets_table = $conn->query("
    SELECT COUNT(*) as count 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'dashboard_widgets'
");
$has_widgets_table = $check_widgets_table->fetch_assoc()['count'] > 0;

if (!$has_widgets_table) {
    @$conn->query("
        CREATE TABLE dashboard_widgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            widget_key VARCHAR(100) NOT NULL,
            is_visible BOOLEAN DEFAULT 1,
            widget_order INT DEFAULT 0,
            is_pinned BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_widget (user_id, widget_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Get user's widget preferences
$user_id = $_SESSION['user_id'];
$user_widgets = get_user_widgets($conn, $user_id);

// Default widget configuration for admin
$default_widgets = [
    'stat_cards' => ['label' => 'Stat Cards', 'icon' => 'fas fa-chart-bar'],
    'login_history' => ['label' => 'Login History', 'icon' => 'fas fa-history'],
    'pengumuman' => ['label' => 'Announcements', 'icon' => 'fas fa-bullhorn'],
    'attendance_chart' => ['label' => 'Attendance Chart', 'icon' => 'fas fa-chart-line']
];

// Merge user preferences with defaults
foreach ($default_widgets as $key => $config) {
    if (!isset($user_widgets[$key])) {
        $user_widgets[$key] = [
            'widget_key' => $key,
            'is_visible' => 1,
            'widget_order' => count($user_widgets) + 1,
            'is_pinned' => 0
        ];
    }
}

// ✅ Statistik dengan query sederhana (tidak ada input user, aman)
$total_siswa = $conn->query("SELECT COUNT(*) as total FROM siswa")->fetch_assoc()['total'];
$total_guru  = $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id=2")->fetch_assoc()['total'];
$total_kelas = $conn->query("SELECT COUNT(*) as total FROM kelas")->fetch_assoc()['total'];
$total_mapel = $conn->query("SELECT COUNT(*) as total FROM mata_pelajaran")->fetch_assoc()['total'];

// ✅ Grafik kehadiran 30 hari — tidak ada input user, aman
$labels = $hadir_data = $sakit_data = $izin_data = $alpha_data = [];
for ($i = 29; $i >= 0; $i--) {
    $tanggal  = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($tanggal));

    $stmt = $conn->prepare(
        "SELECT 
            SUM(CASE WHEN status='hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN status='sakit' THEN 1 ELSE 0 END) as sakit,
            SUM(CASE WHEN status='izin'  THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN status='alpha' THEN 1 ELSE 0 END) as alpha
         FROM absensi WHERE tanggal = ?"
    );
    $stmt->bind_param("s", $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $hadir_data[] = (int)($row['hadir'] ?? 0);
    $sakit_data[] = (int)($row['sakit'] ?? 0);
    $izin_data[]  = (int)($row['izin']  ?? 0);
    $alpha_data[] = (int)($row['alpha'] ?? 0);
}

// ✅ Log login terbaru
$log_login   = $conn->query("SELECT * FROM log_login ORDER BY login_time DESC LIMIT 6");
// ✅ Pengumuman terbaru
$pengumuman  = $conn->query(
    "SELECT p.*, u.nama_lengkap as penulis 
     FROM pengumuman p 
     JOIN users u ON p.created_by = u.id 
     ORDER BY p.created_at DESC LIMIT 5"
);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.pengumuman-item {
    background: #f8fafc;
    border-left: 4px solid var(--primary-500);
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    border-radius: 0.5rem;
}
.pengumuman-meta {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 0.3rem;
}
.widget-card {
    position: relative;
    transition: all 0.3s ease;
}
.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.widget-toolbar {
    display: flex;
    gap: 0.5rem;
}
.widget-btn {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    background: var(--gray-100);
    color: var(--gray-700);
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.widget-btn:hover {
    background: var(--gray-200);
    color: var(--primary-600);
}
.widget-btn.active {
    background: var(--primary-500);
    color: white;
}
.widgets-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1.5rem;
}
@media (max-width: 768px) {
    .widgets-container {
        grid-template-columns: 1fr;
    }
}

/* Widget Settings Modal */
.widget-settings-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.widget-settings-modal.show {
    display: flex;
}
.widget-settings-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.widget-settings-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}
.widget-settings-list {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 1.5rem;
}
.widget-settings-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 6px;
    background: var(--gray-50);
}
.widget-settings-item input[type="checkbox"] {
    margin-right: 1rem;
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.widget-settings-item label {
    flex: 1;
    cursor: pointer;
    margin: 0;
}
.widget-settings-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 class="page-title">Dashboard Admin</h2>
        <p class="page-subtitle">Selamat datang, <?= e($_SESSION['nama']) ?></p>
    </div>
    <button class="btn btn-outline" onclick="openWidgetSettings()" title="Customize dashboard">
        <i class="fas fa-sliders-h"></i> Customize
    </button>
</div>

<!-- Widget Settings Modal -->
<div id="widgetSettingsModal" class="widget-settings-modal">
    <div class="widget-settings-content">
        <h3 class="widget-settings-title">Dashboard Widgets</h3>
        <div class="widget-settings-list" id="widgetsList"></div>
        <div class="widget-settings-actions">
            <button class="btn btn-outline" onclick="closeWidgetSettings()">Close</button>
            <button class="btn btn-primary" onclick="resetDashboardWidgets()">Reset to Default</button>
        </div>
    </div>
</div>

<div class="widgets-container">
    <?php if (isset($user_widgets['stat_cards']) && $user_widgets['stat_cards']['is_visible']): ?>
    <div class="widget-card" id="widget-stat_cards">
        <div class="widget-header">
            <h3 style="margin:0;"><i class="fas fa-chart-bar"></i> Overview</h3>
            <div class="widget-toolbar">
                <button class="widget-btn" onclick="togglePin('stat_cards', this)" title="Pin widget">
                    <i class="fas fa-thumbtack"></i>
                </button>
                <button class="widget-btn" onclick="hideWidget('stat_cards')" title="Hide widget">
                    <i class="fas fa-eye-slash"></i>
                </button>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div><h3>Total Siswa</h3><div class="stat-number"><?= $total_siswa ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div><h3>Total Guru</h3><div class="stat-number"><?= $total_guru ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-school"></i></div>
                <div><h3>Total Kelas</h3><div class="stat-number"><?= $total_kelas ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div><h3>Mata Pelajaran</h3><div class="stat-number"><?= $total_mapel ?></div></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($user_widgets['login_history']) && $user_widgets['login_history']['is_visible']): ?>
    <div class="widget-card form-container" id="widget-login_history">
        <div class="widget-header">
            <h3 style="margin:0;"><i class="fas fa-history"></i> Riwayat Login Terbaru</h3>
            <div class="widget-toolbar">
                <button class="widget-btn" onclick="togglePin('login_history', this)" title="Pin widget">
                    <i class="fas fa-thumbtack"></i>
                </button>
                <button class="widget-btn" onclick="hideWidget('login_history')" title="Hide widget">
                    <i class="fas fa-eye-slash"></i>
                </button>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($log_login && $log_login->num_rows > 0): ?>
                        <?php while ($log = $log_login->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i:s', strtotime($log['login_time'])) ?></td>
                            <td><?= e($log['username']) ?></td>
                            <td><?= e($log['nama_lengkap']) ?></td>
                            <td><?= ucfirst(e($log['role'])) ?></td>
                            <td><?= e($log['ip_address']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:20px;">Belum ada riwayat login.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; text-align: right;">
            <a href="log_login" class="btn btn-outline"><i class="fas fa-eye"></i> Lihat Selengkapnya</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($user_widgets['pengumuman']) && $user_widgets['pengumuman']['is_visible']): ?>
    <div class="widget-card form-container" id="widget-pengumuman">
        <div class="widget-header">
            <h3 style="margin:0;"><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</h3>
            <div class="widget-toolbar">
                <button class="widget-btn" onclick="togglePin('pengumuman', this)" title="Pin widget">
                    <i class="fas fa-thumbtack"></i>
                </button>
                <button class="widget-btn" onclick="hideWidget('pengumuman')" title="Hide widget">
                    <i class="fas fa-eye-slash"></i>
                </button>
            </div>
        </div>
        <?php if ($pengumuman && $pengumuman->num_rows > 0): ?>
            <?php while ($p = $pengumuman->fetch_assoc()): ?>
            <div class="pengumuman-item">
                <strong><?= e($p['judul']) ?></strong>
                <div style="margin-top:0.3rem;"><?= nl2br(e($p['isi'])) ?></div>
                <div class="pengumuman-meta">
                    <i class="fas fa-user"></i> <?= e($p['penulis']) ?> &nbsp;|&nbsp;
                    <i class="far fa-calendar-alt"></i> <?= tgl_indonesia($p['created_at']) ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:var(--gray-500);">Belum ada pengumuman.</p>
        <?php endif; ?>
        <div style="margin-top: 1rem;">
            <a href="pengumuman" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Kelola Pengumuman</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($user_widgets['attendance_chart']) && $user_widgets['attendance_chart']['is_visible']): ?>
<div class="widget-card form-container" id="widget-attendance_chart" style="margin-top: 1.5rem;">
    <div class="widget-header">
        <h3 style="margin:0;"><i class="fas fa-chart-line"></i> Grafik Kehadiran 30 Hari Terakhir</h3>
        <div class="widget-toolbar">
            <button class="widget-btn" onclick="togglePin('attendance_chart', this)" title="Pin widget">
                <i class="fas fa-thumbtack"></i>
            </button>
            <button class="widget-btn" onclick="hideWidget('attendance_chart')" title="Hide widget">
                <i class="fas fa-eye-slash"></i>
            </button>
        </div>
    </div>
    <canvas id="kehadiranChart" style="width:100%; max-height:350px;"></canvas>
</div>
<?php endif; ?>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function openWidgetSettings() {
    const modal = document.getElementById('widgetSettingsModal');
    const widgetsList = document.getElementById('widgetsList');
    widgetsList.innerHTML = '';
    
    const widgets = <?= json_encode($default_widgets) ?>;
    const userWidgets = <?= json_encode($user_widgets) ?>;
    
    Object.entries(widgets).forEach(([key, config]) => {
        const isVisible = userWidgets[key]?.is_visible ?? 1;
        const item = document.createElement('div');
        item.className = 'widget-settings-item';
        item.innerHTML = `
            <input type="checkbox" id="widget_${key}" ${isVisible ? 'checked' : ''} 
                   onchange="toggleWidgetVisibility('${key}', this.checked)">
            <label for="widget_${key}">
                <i class="${config.icon}"></i> ${config.label}
            </label>
        `;
        widgetsList.appendChild(item);
    });
    
    modal.classList.add('show');
}

function closeWidgetSettings() {
    const modal = document.getElementById('widgetSettingsModal');
    modal.classList.remove('show');
}

function hideWidget(widgetKey) {
    toggleWidgetVisibility(widgetKey, false);
}

function toggleWidgetVisibility(widgetKey, isVisible) {
    const formData = new FormData();
    formData.append('action', 'toggle_visibility');
    formData.append('widget_key', widgetKey);
    formData.append('is_visible', isVisible ? 1 : 0);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('../ajax/manage_widgets.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.classList.remove('loading');
        if (data.success) {
            const widget = document.getElementById(`widget-${widgetKey}`);
            if (widget) {
                if (isVisible) {
                    widget.style.display = '';
                } else {
                    widget.style.display = 'none';
                }
            }
        }
    })
    .catch(e => {
        btn.classList.remove('loading');
        console.error('Error:', e);
    });
}

function togglePin(widgetKey, btn) {
    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const isPinned = !btn.classList.contains('active');
    const formData = new FormData();
    formData.append('action', 'toggle_pin');
    formData.append('widget_key', widgetKey);
    formData.append('is_pinned', isPinned ? 1 : 0);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('../ajax/manage_widgets.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-thumbtack"></i>';
        if (data.success) {
            btn.classList.toggle('active');
        }
    })
    .catch(e => {
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-thumbtack"></i>';
        console.error('Error:', e);
    });
}

function resetDashboardWidgets(btn) {
    if (!confirm('Reset dashboard to default layout?')) return;
    
    if (btn) {
        btn.classList.add('loading');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
    }
    
    const formData = new FormData();
    formData.append('action', 'reset');
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('../ajax/manage_widgets.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            if (btn) {
                btn.classList.remove('loading');
                btn.innerHTML = 'Reset to Default';
            }
        }
    })
    .catch(e => {
        if (btn) {
            btn.classList.remove('loading');
            btn.innerHTML = 'Reset to Default';
        }
        console.error('Error:', e);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Init chart
    const ctx = document.getElementById('kehadiranChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    { label: 'Hadir', data: <?= json_encode($hadir_data) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.3, fill: true },
                    { label: 'Sakit', data: <?= json_encode($sakit_data) ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', tension: 0.3, fill: true },
                    { label: 'Izin',  data: <?= json_encode($izin_data) ?>,  borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)',  tension: 0.3, fill: true },
                    { label: 'Alpha', data: <?= json_encode($alpha_data) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)',   tension: 0.3, fill: true }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Jumlah Siswa' } },
                    x: { ticks: { maxRotation: 45, autoSkip: true } }
                }
            }
        });
    }
    
    // Close modal on background click
    document.getElementById('widgetSettingsModal').addEventListener('click', function(e) {
        if (e.target === this) closeWidgetSettings();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
