</main> <!-- penutup dashboard-container -->

<style>
/* ── Sticky footer fix ── */
html { height: 100%; }
body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
.main-wrapper {
    flex: 1;
    min-height: calc(100vh - 58px);
    display: flex;
    flex-direction: column;
}
.dashboard-container {
    flex: 1;
    display: block; /* jangan flex, biarkan konten mengalir normal */
    width: 100%;
}
.main-footer {
    flex-shrink: 0;
    margin-top: auto !important;
}
</style>

<footer class="main-footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3><i class="fas fa-school"></i> MTs. Al-Ihsan Batujajar</h3>
            <p>Madrasah Tsanawiyah unggul dalam prestasi, berlandaskan iman dan taqwa.</p>
            <p><i class="fas fa-map-marker-alt"></i> Jl. Galanggang no. 69, Batujajar, Bandung Barat</p>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-bolt"></i> Menu Cepat</h3>
            <ul class="footer-links">
                <li><a href="<?= $base_url ?>index"><i class="fas fa-home"></i> Beranda</a></li>
                <?php if ($role_id == 1): ?>
                    <li><a href="<?= $base_url ?>admin/dashboard"> Dashboard Admin</a></li>
                <?php elseif ($role_id == 2): ?>
                    <li><a href="<?= $base_url ?>guru/dashboard"> Dashboard Guru</a></li>
                    <li><a href="<?= $base_url ?>guru/materi"> Materi</a></li>
                    <li><a href="<?= $base_url ?>guru/absensi"> Absensi</a></li>
                <?php elseif ($role_id == 3): ?>
                    <li><a href="<?= $base_url ?>siswa/dashboard"> Dashboard Siswa</a></li>
                    <li><a href="<?= $base_url ?>siswa/materi_saya"> Materi Belajar</a></li>
                    <li><a href="<?= $base_url ?>siswa/tugas_saya"> Tugas Saya</a></li>
                <?php elseif ($role_id == 4): ?>
                    <li><a href="<?= $base_url ?>kepsek/dashboard"> Dashboard Kepsek</a></li>
                    <li><a href="<?= $base_url ?>kepsek/rekap_absensi"> Rekap Absensi</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-address-card"></i> Sosial Media</h3>
            <ul class="footer-links">
                <li><i class="fas fa-video"></i>Tiktok: mtsalihsanbatujajar69</li>
                <li><i class="fab fa-youtube"></i> MTS AL-IHSAN BATUJAJAR OFFICIAL</li>
                <li><i class="fab fa-instagram"></i> @mts.alihsanbatujajar</li>
            </ul>
            <div class="social-icons">
                <a href="https://facebook.com/ilhamzp" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com/mts.alihsanbatujajar" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://youtube.com/@mtsal-ihsanbatujajaroffici4815" target="_blank"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> MTs. Al-Ihsan Batujajar | Sistem Pembelajaran Digital<br>
        Version. <strong>1.04</strong>
    </div>
</footer>

<!-- ========== SCRIPT GLOBAL UNTUK TOAST ========== -->
<script>
(function() {
    // Toast notifikasi sederhana
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        let icon = 'check-circle';
        if (type === 'error') icon = 'times-circle';
        if (type === 'warning') icon = 'exclamation-triangle';
        toast.innerHTML = `<i class="fas fa-${icon}"></i> <span>${escapeHtml(message)}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    };

    function escapeHtml(str) {
        return String(str).replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Loading state hanya untuk form yang bukan filter/pencarian
    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                setTimeout(() => {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                }, 100);
            }
        });
    });
})();
</script>

</div> <!-- penutup main-wrapper -->
</body>
</html>