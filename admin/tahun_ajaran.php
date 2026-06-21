<?php
include '../config.php';
cek_login([1]);

// ========== PROSES TAMBAH ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    csrf_verify();
    $tahun = trim($_POST['tahun'] ?? '');
    if (preg_match('/^\d{4}\/\d{4}$/', $tahun)) {
        $stmt = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ?");
        $stmt->bind_param("s", $tahun);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt2 = $conn->prepare("INSERT INTO tahun_ajaran (tahun, is_active) VALUES (?, 0)");
            $stmt2->bind_param("s", $tahun);
            $stmt2->execute();
            set_flash('success', "Tahun ajaran '$tahun' berhasil ditambahkan.");
        } else {
            set_flash('danger', "Tahun ajaran '$tahun' sudah ada.");
        }
    } else {
        set_flash('danger', "Format tahun ajaran tidak valid. Gunakan format YYYY/YYYY.");
    }
    header('Location: tahun_ajaran.php');
    exit;
}

// ========== PROSES HAPUS ==========
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $conn->prepare("SELECT id FROM kelas_mapel WHERE tahun_ajaran_id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        set_flash('danger', "Tahun ajaran tidak bisa dihapus karena sudah digunakan dalam penugasan guru.");
    } else {
        $stmt2 = $conn->prepare("DELETE FROM tahun_ajaran WHERE id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        set_flash('success', "Tahun ajaran berhasil dihapus.");
    }
    header('Location: tahun_ajaran.php');
    exit;
}

$title = 'Kelola Tahun Ajaran';
include '../includes/header.php';

$tahun_list = $conn->query("SELECT * FROM tahun_ajaran ORDER BY tahun DESC");
?>
<style>
/* Gaya tombol khusus */
.btn-hapus {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
}
.btn-hapus:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(239,68,68,0.3);
}
.badge-aktif {
    background: #d1fae5;
    color: #065f46;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    display: inline-block;
}
.badge-tidak-aktif {
    background: #fee2e2;
    color: #991b1b;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    display: inline-block;
}
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
}
@media (max-width: 768px) {
    .action-buttons { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-calendar-alt"></i> Tahun Ajaran</h2>
    <p class="page-subtitle">Kelola tahun ajaran dan tentukan tahun aktif</p>
</div>

<!-- Form Tambah Tahun Ajaran -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-plus-circle"></i> Tambah Tahun Ajaran Baru</div>
    <form method="POST" class="form-row">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Tahun Ajaran <span class="text-danger">*</span></label>
            <input type="text" name="tahun" class="form-input" pattern="\d{4}/\d{4}" placeholder="Contoh: 2025/2026" required>
            <small>Format: YYYY/YYYY (contoh: 2025/2026)</small>
        </div>
        <div class="form-group" style="display: flex; align-items: flex-end;">
            <button type="submit" name="tambah" class="btn btn-primary"><i class="fas fa-save"></i> Tambah</button>
        </div>
    </form>
</div>

<!-- Daftar Tahun Ajaran -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-list"></i> Daftar Tahun Ajaran</div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Tahun Ajaran</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tahun_list->num_rows > 0): ?>
                    <?php while($t = $tahun_list->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['tahun']) ?></strong></td>
                        <td>
                            <?= $t['is_active'] 
                                ? '<span class="badge-aktif"><i class="fas fa-check-circle"></i> Aktif</span>' 
                                : '<span class="badge-tidak-aktif"><i class="fas fa-times-circle"></i> Tidak Aktif</span>' 
                            ?>
                        </td>
                        <td class="action-buttons">
                            <a href="?hapus=<?= $t['id'] ?>" class="btn btn-sm btn-hapus" onclick="var _href=this.href;event.preventDefault();confirmModal('Hapus tahun ajaran <?= htmlspecialchars($t['tahun']) ?>?').then(r=>r&&(window.location=_href))">
                                <i class="fas fa-trash-alt"></i> Hapus
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 30px;">Belum ada data tahun ajaran. Silakan tambah.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>