<?php
include '../config.php';
cek_login([1]);
// ========== TAMBAH ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    csrf_verify();
    $kode   = trim($_POST['kode']);
    $nama   = trim($_POST['nama']);
    $urutan = (int)$_POST['urutan'];

    $stmt = $conn->prepare("INSERT INTO mata_pelajaran (kode, nama_mapel, urutan) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $kode, $nama, $urutan);
    $stmt->execute();
    set_flash('success', 'Mata pelajaran berhasil ditambahkan.');
    header('Location: mata_pelajaran');
    exit;
}

// ========== EDIT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();
    $id     = (int)$_POST['id'];
    $kode   = trim($_POST['kode']);
    $nama   = trim($_POST['nama']);
    $urutan = (int)$_POST['urutan'];

    $stmt = $conn->prepare("UPDATE mata_pelajaran SET kode=?, nama_mapel=?, urutan=? WHERE id=?");
    $stmt->bind_param("ssii", $kode, $nama, $urutan, $id);
    $stmt->execute();
    set_flash('success', 'Mata pelajaran berhasil diupdate.');
    header('Location: mata_pelajaran');
    exit;
}

// ========== HAPUS ==========
if (isset($_GET['hapus'])) {
    $id  = (int)$_GET['hapus'];
    $cek = $conn->prepare("SELECT id FROM kelas_mapel WHERE mapel_id=? LIMIT 1");
    $cek->bind_param("i", $id);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        set_flash('warning', 'Mata pelajaran tidak bisa dihapus karena sudah digunakan dalam penugasan guru.');
    } else {
        $del = $conn->prepare("DELETE FROM mata_pelajaran WHERE id=?");
        $del->bind_param("i", $id);
        $del->execute();
        set_flash('success', 'Mata pelajaran dihapus.');
    }
    header('Location: mata_pelajaran');
    exit;
}
$title = 'Kelola Mata Pelajaran';
include '../includes/header.php';


$mapel = $conn->query("SELECT * FROM mata_pelajaran ORDER BY urutan, id");
?>
<style>
.edit-form-table td { vertical-align: middle; padding: 10px 8px; }
.action-buttons { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
.btn-danger { background: linear-gradient(135deg,#ef4444,#dc2626); color: white; border: none; }
.btn-danger:hover { background: linear-gradient(135deg,#dc2626,#b91c1c); }
@media(max-width:768px){ .action-buttons{flex-direction:column; align-items:center;} }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-book"></i> Mata Pelajaran</h2>
    <p class="page-subtitle">Kelola daftar mata pelajaran kurikulum merdeka</p>
</div>

<!-- Form Tambah -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-plus-circle"></i> Tambah Mata Pelajaran Baru</div>
    <form method="POST" class="form-row">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Kode <span style="color:red">*</span></label>
            <input type="text" name="kode" class="form-input" required maxlength="10" placeholder="Contoh: BIN">
        </div>
        <div class="form-group">
            <label>Nama Mata Pelajaran <span style="color:red">*</span></label>
            <input type="text" name="nama" class="form-input" required placeholder="Contoh: Bahasa Indonesia">
        </div>
        <div class="form-group">
            <label>Urutan (Sorting)</label>
            <input type="number" name="urutan" class="form-input" value="0" min="0">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
            <button type="submit" name="tambah" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
        </div>
    </form>
</div>

<!-- Daftar Mata Pelajaran (edit inline) -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-list"></i> Daftar Mata Pelajaran</div>
    <div class="table-wrapper">
        <table class="modern-table edit-form-table">
            <thead>
                <tr><th>Kode</th><th>Nama Mata Pelajaran</th><th>Urutan</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if ($mapel->num_rows > 0):
                while ($m = $mapel->fetch_assoc()): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <!-- PENTING: form tidak bisa sebagai display:table-row di semua browser,
                         jadi input diletakkan di dalam <td> masing-masing -->
                        <td><input type="text" name="kode" value="<?= e($m['kode']) ?>" class="form-input" required maxlength="10"></td>
                        <td><input type="text" name="nama" value="<?= e($m['nama_mapel']) ?>" class="form-input" required></td>
                        <td><input type="number" name="urutan" value="<?= (int)$m['urutan'] ?>" class="form-input" min="0" style="width:80px;"></td>
                        <td class="action-buttons">
                            <button type="submit" name="edit" class="btn btn-sm btn-primary">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <a href="?hapus=<?= $m['id'] ?>" class="btn btn-sm btn-danger"
                               data-confirm="Hapus mata pelajaran <?= e($m['nama_mapel']) ?>?">
                                <i class="fas fa-trash-alt"></i> Hapus
                            </a>
                        </td>
                    </form>
                </tr>
            <?php endwhile;
            else: ?>
                <tr><td colspan="4" style="text-align:center; padding:30px;">
                    Belum ada data. Silakan tambah mata pelajaran di atas.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; 