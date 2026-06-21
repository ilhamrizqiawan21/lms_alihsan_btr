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

<!-- ========== TOAST CONTAINER ========== -->
<div id="toastContainer" class="toast-container"></div>

<!-- ========== SCRIPT GLOBAL ========== -->
<script>
(function() {
    // ── Toast container ──
    var container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    // ── Escape HTML helper ──
    function escapeHtml(str) {
        return String(str).replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // ── Toast ──
    window.showToast = function(message, type) {
        if (!type) type = 'success';
        var iconMap = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        var icon = iconMap[type] || 'fa-info-circle';

        var toast = document.createElement('div');
        toast.className = 'toast-notification ' + type;
        toast.innerHTML = '<i class="fas ' + icon + '"></i> <span>' + escapeHtml(message) + '</span>';
        container.appendChild(toast);

        setTimeout(function() {
            toast.classList.add('removing');
            setTimeout(function() {
                if (toast.parentNode) toast.remove();
            }, 350);
        }, 4000);
    };

    // ── Confirm Modal ──
    window.confirmModal = function(message, options) {
        options = options || {};
        var title = options.title || 'Konfirmasi';
        var confirmText = options.confirmText || 'Ya, lanjutkan';
        var cancelText = options.cancelText || 'Batal';
        var isDanger = options.danger === true;
        var iconType = isDanger ? 'danger' : (options.iconType || 'warning');
        var iconMap = {
            danger: 'fa-exclamation-triangle',
            warning: 'fa-question-circle',
            info: 'fa-info-circle'
        };
        var icon = iconMap[iconType] || 'fa-question-circle';
        var customClass = isDanger ? 'danger' : '';

        return new Promise(function(resolve) {
            // Overlay
            var overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.innerHTML =
                '<div class="confirm-modal">' +
                    '<div class="modal-icon ' + iconType + '"><i class="fas ' + icon + '"></i></div>' +
                    '<div class="modal-title">' + escapeHtml(title) + '</div>' +
                    '<div class="modal-message">' + escapeHtml(message) + '</div>' +
                    '<div class="modal-actions">' +
                        '<button class="btn-cancel" id="confirmCancel">' + escapeHtml(cancelText) + '</button>' +
                        '<button class="btn-confirm ' + customClass + '" id="confirmOk">' + escapeHtml(confirmText) + '</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(overlay);

            function cleanup(result) {
                if (overlay.parentNode) overlay.remove();
                resolve(result);
            }

            document.getElementById('confirmOk').addEventListener('click', function() {
                cleanup(true);
            });
            document.getElementById('confirmCancel').addEventListener('click', function() {
                cleanup(false);
            });
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) cleanup(false);
            });
            // Keyboard: Escape = batal
            document.addEventListener('keydown', function handler(e) {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', handler);
                    cleanup(false);
                }
            });
        });
    };

    // ── Data-confirm handler ──
    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-confirm]');
        if (!el) return;
        e.preventDefault();
        e.stopPropagation();
        var msg = el.getAttribute('data-confirm');
        var href = el.getAttribute('href');
        var formAction = el.getAttribute('data-action');
        var method = el.getAttribute('data-method') || 'get';
        confirmModal(msg, { danger: el.classList.contains('btn-danger') }).then(function(ok) {
            if (!ok) return;
            if (formAction) {
                var form = document.createElement('form');
                form.method = method === 'post' ? 'POST' : 'GET';
                form.action = formAction;
                document.body.appendChild(form);
                form.submit();
            } else if (href) {
                window.location.href = href;
            }
        });
    });

    // ── Loading state pada form POST ──
    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                setTimeout(function() {
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
