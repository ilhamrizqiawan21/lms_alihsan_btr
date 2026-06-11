<?php
include '../config.php';
cek_login([1]);
$title = 'Manajemen Kelas & Bulk Actions';
include '../includes/header.php';

// Ambil daftar kelas
$kelas_res = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
$kelas_list = [];
while ($k = $kelas_res->fetch_assoc()) $kelas_list[] = $k;

$selected_kelas = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : ($kelas_list[0]['id'] ?? 0);

// Ambil siswa pada kelas terpilih
$students = [];
if ($selected_kelas) {
    $stmt = $conn->prepare("SELECT s.id, s.nis, s.nama_lengkap, s.user_id FROM siswa s WHERE s.kelas_id = ? ORDER BY s.nama_lengkap");
    $stmt->bind_param("i", $selected_kelas);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $students[] = $r;
}
?>

<style>
.table-actions { display:flex; gap:0.5rem; align-items:center; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-chalkboard"></i> Manajemen Kelas & Bulk Actions</h2>
    <p class="page-subtitle">Halaman untuk mengelola siswa per kelas dan menjalankan aksi massal (Admin saja)</p>
</div>

<div class="form-row">
    <div class="form-container">
        <form id="filterForm" method="get">
            <label>Pilih Kelas</label>
            <select name="kelas_id" onchange="this.form.submit()">
                <?php foreach ($kelas_list as $k): ?>
                    <option value="<?= e($k['id']) ?>" <?= $selected_kelas == $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div style="margin-top:1rem;">
            <div style="margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <button id="selectAllBtn" class="btn btn-secondary">Pilih Semua</button>
                    <button id="clearAllBtn" class="btn btn-secondary">Bersihkan</button>
                </div>
                <div class="table-actions">
                    <select id="bulkActionSelect">
                        <option value="">-- Aksi Bulk --</option>
                        <option value="move">Pindah Kelas</option>
                        <option value="delete">Hapus Siswa</option>
                    </select>
                    <select id="targetKelas" style="display:none;">
                        <option value="">Pilih kelas tujuan</option>
                        <?php foreach ($kelas_list as $k): ?>
                            <option value="<?= e($k['id']) ?>"><?= e($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="previewBtn" class="btn btn-primary">Preview</button>
                    <button id="commitBtn" class="btn btn-danger" style="display:none;">Eksekusi</button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="modern-table" id="studentsTable">
                    <thead>
                        <tr>
                            <th></th>
                            <th>NIS</th>
                            <th>Nama Lengkap</th>
                            <th>User ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><input type="checkbox" class="studentCheckbox" value="<?= e($s['id']) ?>"></td>
                            <td><?= e($s['nis']) ?></td>
                            <td><?= e($s['nama_lengkap']) ?></td>
                            <td><?= e($s['user_id']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="previewArea" style="margin-top:1rem; display:none;"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const previewBtn = document.getElementById('previewBtn');
    const commitBtn = document.getElementById('commitBtn');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const targetKelas = document.getElementById('targetKelas');
    const previewArea = document.getElementById('previewArea');

    selectAllBtn.addEventListener('click', (e) => { e.preventDefault(); document.querySelectorAll('.studentCheckbox').forEach(cb=>cb.checked=true); });
    clearAllBtn.addEventListener('click', (e) => { e.preventDefault(); document.querySelectorAll('.studentCheckbox').forEach(cb=>cb.checked=false); });

    bulkActionSelect.addEventListener('change', () => {
        if (bulkActionSelect.value === 'move') { targetKelas.style.display = 'inline-block'; } else { targetKelas.style.display = 'none'; }
        commitBtn.style.display = 'none'; previewArea.style.display = 'none';
    });

    previewBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const selected = Array.from(document.querySelectorAll('.studentCheckbox:checked')).map(cb=>cb.value);
        if (!selected.length) { alert('Pilih minimal 1 siswa'); return; }
        const action = bulkActionSelect.value;
        if (!action) { alert('Pilih aksi bulk'); return; }
        const payload = new FormData();
        payload.append('action', action);
        selected.forEach(id=>payload.append('student_ids[]', id));
        if (action === 'move') {
            const target = targetKelas.value;
            if (!target) { alert('Pilih kelas tujuan'); return; }
            payload.append('target_kelas', target);
        }
        payload.append('preview', 1);

        const res = await fetch('<?= $base_url ?>ajax/bulk_class_actions.php', { method: 'POST', body: payload });
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Gagal preview', 'error'); return; }
        previewArea.style.display = 'block';
        previewArea.innerHTML = `<div style="padding:1rem; border:1px solid #e5e7eb; background:#fff; border-radius:12px;"><strong>Preview (${action})</strong><div style="margin-top:0.5rem;">${data.preview_html}</div></div>`;
        commitBtn.style.display = 'inline-block';
        commitBtn.dataset.action = action;
    });

    commitBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        if (!confirm('Yakin ingin mengeksekusi aksi ini? Tindakan ini tidak bisa dibatalkan (kecuali data backup).')) return;
        const selected = Array.from(document.querySelectorAll('.studentCheckbox:checked')).map(cb=>cb.value);
        const action = commitBtn.dataset.action;
        const payload = new FormData();
        payload.append('action', action);
        selected.forEach(id=>payload.append('student_ids[]', id));
        if (action === 'move') payload.append('target_kelas', targetKelas.value);
        payload.append('preview', 0);

        const res = await fetch('<?= $base_url ?>ajax/bulk_class_actions.php', { method: 'POST', body: payload });
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Gagal menjalankan aksi', 'error'); return; }
        showToast(data.message || 'Sukses');
        setTimeout(()=> location.reload(), 900);
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
