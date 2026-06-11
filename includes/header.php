<?php
if (!isset($_SESSION)) session_start();

$role_id = $_SESSION['role_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$nama = $_SESSION['nama'] ?? 'Pengunjung';
$base_url = $GLOBALS['base_url'] ?? '/';

// Pastikan koneksi database tersedia
$conn = $GLOBALS['conn'];

// Baca warna tema dari database
$warna_tema = get_pengaturan($conn, 'warna_tema');
if (!$warna_tema) $warna_tema = 'hijau';

// Definisikan warna sesuai tema
switch ($warna_tema) {
    case 'biru-azure':
        $primary_500 = '#0078D7';
        $primary_600 = '#0063b1';
        $primary_700 = '#0050a0';
        $primary_800 = '#003d7a';
        break;
    case 'biru-aqua':
        $primary_500 = '#00b4d8';
        $primary_600 = '#0096c7';
        $primary_700 = '#0077b6';
        $primary_800 = '#023e8a';
        break;
    default: // hijau
        $primary_500 = '#10b981';
        $primary_600 = '#059669';
        $primary_700 = '#047857';
        $primary_800 = '#065f46';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $title ?? 'LMS MTs Al-Ihsan' ?></title>
    <link rel="icon" href="<?= $base_url ?>assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= $base_url ?>style.css">
        <link rel="stylesheet" href="<?= $base_url ?>assets/css/notifikasi.css">
    <style>
        :root {
            --primary-500: <?= $primary_500 ?>;
            --primary-600: <?= $primary_600 ?>;
            --primary-700: <?= $primary_700 ?>;
            --primary-800: <?= $primary_800 ?>;
        }

        /* ========== OVERRIDE WARNA DINAMIS DAN GRADASI ========== */
        /* Sidebar dengan gradasi lebih kaya */
        .sidebar {
            background: linear-gradient(160deg, var(--primary-600) 0%, var(--primary-700) 45%, var(--primary-800) 100%) !important;
        }
        /* Topbar dengan gradasi dan z-index di atas sidebar */
        .topbar {
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-600) 35%, var(--primary-800) 100%) !important;
            z-index: 400 !important;
        }
        /* Tombol primary gradasi */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700)) !important;
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-700), var(--primary-800)) !important;
        }
        /* Aksen warna lainnya */
        .stat-icon {
            background: var(--primary-100);
            color: var(--primary-600);
        }
        .form-title {
            border-left-color: var(--primary-500);
        }
        .page-subtitle {
            border-left-color: var(--primary-500);
        }
        .badge-hadir { background: #dcfce7; color: #166534; }
        .badge-sakit { background: #fed7aa; color: #9b2c1d; }
        .badge-izin { background: #dbeafe; color: #1e40af; }
        .badge-alpha { background: #fee2e2; color: #991b1b; }
        
        /* Responsif */
        @media (max-width: 768px) {
            .dashboard-container { padding: 0.8rem !important; }
            .table-wrapper { overflow-x: auto !important; }
            .modern-table { min-width: 600px; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <img src="<?= $base_url ?>assets/images/logo-sekolah.png" alt="Logo">
            </div>
            <div class="sidebar-logo-text">
                <span class="sidebar-logo-title">MTs. Al-Ihsan</span>
                <span class="sidebar-logo-sub">Batujajar</span>
            </div>
        </div>
        <button class="sidebar-close-btn" id="sidebarCloseBtn"><i class="fas fa-times"></i></button>
    </div>

    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <i class="<?= $role_id == 2 ? 'fas fa-chalkboard-user' : ($role_id == 3 ? 'fas fa-user-graduate' : 'fas fa-user-tie') ?>"></i>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role">
                <?= $role_id == 1 ? 'Administrator' : ($role_id == 2 ? 'Guru' : ($role_id == 3 ? 'Siswa' : 'Kepala Sekolah')) ?>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <?php if ($role_id == 1): // ADMIN ?>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/dashboard.php" class="sidebar-menu-link"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/calendar.php" class="sidebar-menu-link"><i class="fas fa-calendar-alt"></i><span>Kalender & Reminder</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/rekap_absensi.php" class="sidebar-menu-link"><i class="fas fa-calendar-check"></i><span>Rekap Absensi</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/rekap_tugas.php" class="sidebar-menu-link"><i class="fas fa-tasks"></i><span>Rekap Tugas</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/rekap_nilai.php" class="sidebar-menu-link"><i class="fas fa-chart-pie"></i><span>Rekap Nilai</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/rekap_sikap.php" class="sidebar-menu-link"><i class="fas fa-heart"></i><span>Rekap Sikap</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/kelas_siswa.php" class="sidebar-menu-link"><i class="fas fa-users"></i><span>Kelas & Siswa</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/mata_pelajaran.php" class="sidebar-menu-link"><i class="fas fa-book"></i><span>Mata Pelajaran</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/tahun_ajaran.php" class="sidebar-menu-link"><i class="fas fa-calendar-alt"></i><span>Tahun Ajaran</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/kelas_mapel.php" class="sidebar-menu-link"><i class="fas fa-chalkboard-user"></i><span>Penugasan Guru</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/user_management.php" class="sidebar-menu-link"><i class="fas fa-users-gear"></i><span>User Management</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>admin/pengaturan.php" class="sidebar-menu-link"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
            <?php elseif ($role_id == 2): // GURU ?>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/dashboard.php" class="sidebar-menu-link"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/calendar.php" class="sidebar-menu-link"><i class="fas fa-calendar-alt"></i><span>Kalender & Reminder</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/materi.php" class="sidebar-menu-link"><i class="fas fa-book"></i><span>Materi</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/tugas.php" class="sidebar-menu-link"><i class="fas fa-tasks"></i><span>Tugas & Nilai</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/absensi.php" class="sidebar-menu-link"><i class="fas fa-calendar-check"></i><span>Absensi</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/olah_nilai.php" class="sidebar-menu-link"><i class="fas fa-chart-pie"></i><span>Olah Nilai</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/sikap.php" class="sidebar-menu-link"><i class="fas fa-heart"></i><span>Nilai Sikap</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/rekap_sikap.php" class="sidebar-menu-link"><i class="fas fa-chart-pie"></i><span>Rekap Sikap</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/chat.php" class="sidebar-menu-link"><i class="fas fa-comments"></i><span>Chat Kelas</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>guru/profil.php" class="sidebar-menu-link"><i class="fas fa-user-circle"></i><span>Profil</span></a></li>
            <?php elseif ($role_id == 3): // SISWA ?>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/dashboard.php" class="sidebar-menu-link"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/progress.php" class="sidebar-menu-link"><i class="fas fa-chart-line"></i><span>Progress Saya</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/calendar.php" class="sidebar-menu-link"><i class="fas fa-calendar-alt"></i><span>Kalender & Reminder</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/materi_saya.php" class="sidebar-menu-link"><i class="fas fa-book-open"></i><span>Materi Saya</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/tugas_saya.php" class="sidebar-menu-link"><i class="fas fa-tasks"></i><span>Tugas Saya</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/nilai_saya.php" class="sidebar-menu-link"><i class="fas fa-chart-line"></i><span>Nilai Saya</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/chat.php" class="sidebar-menu-link"><i class="fas fa-comments"></i><span>Chat Kelas</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/profil.php" class="sidebar-menu-link"><i class="fas fa-user-circle"></i><span>Profil</span></a></li>
            <?php elseif ($role_id == 4): // KEPALA SEKOLAH ?>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>kepsek/dashboard.php" class="sidebar-menu-link"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>siswa/calendar.php" class="sidebar-menu-link"><i class="fas fa-calendar-alt"></i><span>Kalender & Reminder</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>kepsek/rekap_absensi.php" class="sidebar-menu-link"><i class="fas fa-calendar-check"></i><span>Rekap Absensi</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>kepsek/rekap_tugas.php" class="sidebar-menu-link"><i class="fas fa-tasks"></i><span>Rekap Tugas</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>kepsek/rekap_nilai.php" class="sidebar-menu-link"><i class="fas fa-chart-pie"></i><span>Rekap Nilai</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>kepsek/rekap_sikap.php" class="sidebar-menu-link"><i class="fas fa-heart"></i><span>Rekap Sikap</span></a></li>
                <li class="sidebar-menu-item"><a href="<?= $base_url ?>kepsek/laporan.php" class="sidebar-menu-link"><i class="fas fa-file-alt"></i><span>Laporan</span></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= $base_url ?>logout.php" class="sidebar-logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
</aside>

<!-- TOPBAR -->
<div class="topbar" id="topbar">
    <button class="topbar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
    <div class="topbar-brand">
        <div class="topbar-logo-icon"><img src="<?= $base_url ?>assets/images/logo-sekolah.png" alt="Logo"></div>
        <div class="topbar-title">
            <span class="topbar-title-main">Digitalisasi Pembelajaran</span>
            <span class="topbar-title-sub">MTs. Al-Ihsan Batujajar - Kurikulum Merdeka</span>
        </div>
    </div>
    <div class="topbar-notif" style="position:relative; margin-right:10px;">
    <button id="notifBtn" class="topbar-notif-btn" style="background:none; border:none; color:white; font-size:1.2rem; cursor:pointer;">
        <i class="fas fa-bell"></i>
        <span id="notifBadge" class="notif-badge" style="position:absolute; top:-5px; right:-8px; background:#ef4444; color:white; border-radius:50%; padding:2px 5px; font-size:0.65rem; display:none;">0</span>
    </button>
    <div id="notifDropdown" class="notif-dropdown" style="display:none; position:absolute; right:0; top:40px; width:320px; background:white; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:1000; max-height:400px; overflow-y:auto;">
        <div class="notif-header" style="padding:10px 15px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between;">
            <strong>Notifikasi</strong>
            <button id="markAllRead" style="background:none; border:none; color:#10b981; cursor:pointer; font-size:0.75rem;">Tandai semua baca</button>
        </div>
        <div id="notifList" class="notif-list" style="padding:5px 0;"></div>
        <div class="notif-footer" style="padding:8px; text-align:center; border-top:1px solid #e5e7eb;">
            <small style="color:#6b7280;">Notifikasi terbaru</small>
        </div>
    </div>
</div>
    <div class="topbar-user">
        <div class="topbar-user-avatar"><i class="<?= $role_id == 2 ? 'fas fa-chalkboard-user' : ($role_id == 3 ? 'fas fa-user-graduate' : 'fas fa-user-tie') ?>"></i></div>
        <div class="topbar-user-info">
            <span class="topbar-user-name"><?= htmlspecialchars($nama) ?></span>
            <span class="topbar-user-role"><?= $role_id == 1 ? 'Admin' : ($role_id == 2 ? 'Guru' : ($role_id == 3 ? 'Siswa' : 'Kepsek')) ?></span>
        </div>
        <a href="<?= $base_url ?>logout.php" class="topbar-logout"><i class="fas fa-sign-out-alt"></i><span class="topbar-logout-text">Logout</span></a>
    </div>

</div>

<div class="main-wrapper" id="mainWrapper">
    <main class="dashboard-container">
    <?php if (function_exists('show_flash')) show_flash(); ?>

<script>
(function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const mainWrapper = document.getElementById('mainWrapper');

    function openSidebar() {
        sidebar.classList.add('sidebar-open');
        overlay.classList.add('overlay-visible');
        if (window.innerWidth >= 992) mainWrapper.classList.add('main-shifted');
        localStorage.setItem('sidebarState', 'open');
    }
    function closeSidebar() {
        sidebar.classList.remove('sidebar-open');
        overlay.classList.remove('overlay-visible');
        mainWrapper.classList.remove('main-shifted');
        localStorage.setItem('sidebarState', 'closed');
    }
    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (sidebar.classList.contains('sidebar-open')) closeSidebar();
        else openSidebar();
    });
    closeBtn.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

    if (window.innerWidth >= 992 && localStorage.getItem('sidebarState') === 'open') openSidebar();

    // Pastikan sidebar tertutup pada layar kecil, dan sinkronkan saat ukuran berubah
    window.addEventListener('resize', function() {
        if (window.innerWidth < 992) {
            // tutup bila sedang terbuka pada layar kecil
            if (sidebar.classList.contains('sidebar-open')) closeSidebar();
        } else {
            // pada layar besar, kembalikan sesuai preferensi pengguna
            if (localStorage.getItem('sidebarState') === 'open') openSidebar();
        }
    });

    // Dropdown submenu (jika ada di masa depan)
    document.querySelectorAll('.sidebar-dropdown-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.sidebar-dropdown');
            if (parent) parent.classList.toggle('sidebar-dropdown-active');
        });
    });
})();

// Notifikasi polling
let lastNotifCheck = 0;

function loadNotifikasi() {
    fetch('<?= $base_url ?>ajax/get_notifikasi.php')
        .then(res => res.json())
        .then(data => {
            document.getElementById('notifBadge').style.display = data.total > 0 ? 'inline-block' : 'none';
            document.getElementById('notifBadge').innerText = data.total;
            const listDiv = document.getElementById('notifList');
            if (data.notifikasi.length === 0) {
                listDiv.innerHTML = '<div style="padding:15px; text-align:center; color:#6b7280;">Tidak ada notifikasi</div>';
            } else {
                let html = '';
                data.notifikasi.forEach(notif => {
                    html += `<div class="notif-item ${notif.is_read ? '' : 'unread'}" data-id="${notif.id}">
                                <div class="notif-title">${escapeHtml(notif.judul)}</div>
                                <div class="notif-time">${notif.created_at_fmt}</div>
                                <div class="notif-pesan" style="font-size:0.75rem; color:#4b5563; margin-top:4px;">${escapeHtml(notif.pesan)}</div>
                            </div>`;
                });
                listDiv.innerHTML = html;
                // Klik item -> tandai baca dan redirect jika ada link
                document.querySelectorAll('.notif-item').forEach(el => {
                    el.addEventListener('click', (e) => {
                        const id = el.dataset.id;
                        fetch('<?= $base_url ?>ajax/mark_read.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'id=' + id
                        }).then(() => {
                            const link = el.dataset.link;
                            if (link) window.location.href = link;
                            else loadNotifikasi();
                        });
                    });
                });
            }
        });
}

document.getElementById('notifBtn').addEventListener('click', function(e) {
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        loadNotifikasi();
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
});
document.getElementById('markAllRead').addEventListener('click', function() {
    fetch('<?= $base_url ?>ajax/mark_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'all=1'
    }).then(() => loadNotifikasi());
});
// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notifDropdown');
    const btn = document.getElementById('notifBtn');
    if (dropdown.style.display === 'block' && !btn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
// Polling setiap 15 detik
setInterval(loadNotifikasi, 15000);
// Load awal
loadNotifikasi();

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

</script>