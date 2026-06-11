<?php
include '../config.php';
cek_login([1]);

// ========== PROSES TAMBAH (multi kelas) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    csrf_verify();
    $kelas_ids       = $_POST['kelas_ids'] ?? []; // array dari checkbox
    $mapel_id        = (int)$_POST['mapel_id'];
    $guru_id         = (int)$_POST['guru_id'];
    $tahun_ajaran_id = (int)$_POST['tahun_ajaran_id'];
    $semester        = in_array($_POST['semester'], ['1','2']) ? $_POST['semester'] : '1';

    if (empty($kelas_ids)) {
        set_flash('warning', 'Pilih minimal satu kelas.');
        header('Location: kelas_mapel');
        exit;
    }

    $sukses = 0;
    $gagal  = 0;
    foreach ($kelas_ids as $kelas_id) {
        $kelas_id = (int)$kelas_id;

        // Cek duplikat
        $cek = $conn->prepare(
            "SELECT id FROM kelas_mapel 
             WHERE kelas_id=? AND mapel_id=? AND tahun_ajaran_id=? AND semester=?"
        );
        $cek->bind_param("iiis", $kelas_id, $mapel_id, $tahun_ajaran_id, $semester);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $gagal++;
            continue;
        }

        $ins = $conn->prepare(
            "INSERT INTO kelas_mapel (kelas_id, mapel_id, guru_id, tahun_ajaran_id, semester) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $ins->bind_param("iiiis", $kelas_id, $mapel_id, $guru_id, $tahun_ajaran_id, $semester);
        $ins->execute();
        $sukses++;
    }

    if ($sukses > 0) {
        set_flash('success', "$sukses penugasan berhasil ditambahkan." . ($gagal > 0 ? " ($gagal gagal karena duplikat)" : ""));
    } else {
        set_flash('warning', 'Tidak ada penugasan yang ditambahkan (mungkin duplikat atau tidak ada kelas dipilih).');
    }
    header('Location: kelas_mapel');
    exit;
}

// ========== PROSES HAPUS ==========
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $del = $conn->prepare("DELETE FROM kelas_mapel WHERE id=?");
    $del->bind_param("i", $id);
    $del->execute();
    set_flash('success', 'Penugasan dihapus.');
    header('Location: kelas_mapel');
    exit;
}

// ========== PROSES EDIT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();
    $id              = (int)$_POST['id'];
    $kelas_id        = (int)$_POST['kelas_id'];
    $mapel_id        = (int)$_POST['mapel_id'];
    $guru_id         = (int)$_POST['guru_id'];
    $tahun_ajaran_id = (int)$_POST['tahun_ajaran_id'];
    $semester        = in_array($_POST['semester'], ['1','2']) ? $_POST['semester'] : '1';

    $upd = $conn->prepare(
        "UPDATE kelas_mapel 
         SET kelas_id=?, mapel_id=?, guru_id=?, tahun_ajaran_id=?, semester=? 
         WHERE id=?"
    );
    $upd->bind_param("iiiisi", $kelas_id, $mapel_id, $guru_id, $tahun_ajaran_id, $semester, $id);
    $upd->execute();
    set_flash('success', 'Penugasan berhasil diupdate.');
    header('Location: kelas_mapel');
    exit;
}

$title = 'Penugasan Guru (Kelas & Mapel)';
include '../includes/header.php';

// ========== DATA DROPDOWN ==========
$kelas_all = $conn->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas");
$mapel_list        = $conn->query("SELECT * FROM mata_pelajaran ORDER BY urutan");
$guru_list         = $conn->query("SELECT id, nama_lengkap FROM users WHERE role_id=2 ORDER BY nama_lengkap");
$tahun_ajaran_list = $conn->query("SELECT * FROM tahun_ajaran ORDER BY tahun DESC");

// ========== FILTER & DAFTAR ==========
$filter_tahun    = isset($_GET['tahun_ajaran_id']) ? (int)$_GET['tahun_ajaran_id'] : 0;
$filter_semester = isset($_GET['semester']) && in_array($_GET['semester'], ['1','2']) ? $_GET['semester'] : '';

$sql = "SELECT km.*, k.nama_kelas, mp.nama_mapel, u.nama_lengkap as nama_guru, ta.tahun 
        FROM kelas_mapel km
        JOIN kelas k ON km.kelas_id = k.id
        JOIN mata_pelajaran mp ON km.mapel_id = mp.id
        JOIN users u ON km.guru_id = u.id
        JOIN tahun_ajaran ta ON km.tahun_ajaran_id = ta.id
        WHERE 1=1";
$params = [];
$types  = '';

if ($filter_tahun > 0) {
    $sql    .= " AND km.tahun_ajaran_id = ?";
    $params[] = $filter_tahun;
    $types  .= 'i';
}
if ($filter_semester !== '') {
    $sql    .= " AND km.semester = ?";
    $params[] = $filter_semester;
    $types  .= 's';
}
$sql .= " ORDER BY ta.tahun DESC, k.nama_kelas, mp.urutan";

$stmt_list = $conn->prepare($sql);
if ($params) {
    $stmt_list->bind_param($types, ...$params);
}
$stmt_list->execute();
$kelas_mapel_data = $stmt_list->get_result();
?>

<style>
.badge-semester { background:#e0e7ff; color:#4338ca; padding:4px 10px; border-radius:20px; font-size:0.7rem; font-weight:500; display:inline-block; }
.action-buttons { display:flex; gap:8px; flex-wrap:wrap; }
.modal-overlay  { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
.modal-content  { background:white; border-radius:1rem; max-width:500px; width:90%; padding:1.5rem; }
.btn-danger     { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; }
.btn-danger:hover { background:linear-gradient(135deg,#dc2626,#b91c1c); }
.checkbox-group { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:0.5rem; max-height:200px; overflow-y:auto; border:1px solid var(--gray-200); padding:0.6rem; border-radius:var(--radius-md); background:var(--gray-50); margin-bottom:1rem; }
.checkbox-group label { display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; cursor:pointer; }
.checkbox-group input { width:auto; margin-right:0.3rem; }
@media(max-width:768px){ .action-buttons{flex-direction:column;} .checkbox-group{grid-template-columns:repeat(2,1fr);} }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-chalkboard-user"></i> Penugasan Guru</h2>
    <p class="page-subtitle">Atur mata pelajaran yang diajar oleh guru di beberapa kelas sekaligus</p>
</div>

<?= show_flash(); ?>

<div class="form-row">
    <!-- Form Tambah dengan Checkbox Kelas -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-plus-circle"></i> Tambah Penugasan</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="form-group">
                <label>Pilih Kelas (bisa lebih dari satu)</label>
                <div class="checkbox-group">
                    <?php 
                    $kelas_all->data_seek(0);
                    while ($k = $kelas_all->fetch_assoc()): ?>
                        <label>
                            <input type="checkbox" name="kelas_ids[]" value="<?= $k['id'] ?>">
                            <?= e($k['nama_kelas']) ?>
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Mata Pelajaran <span style="color:red">*</span></label>
                <select name="mapel_id" class="form-select" required>
                    <option value="">-- Pilih Mapel --</option>
                    <?php $mapel_list->data_seek(0); while ($m = $mapel_list->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['nama_mapel']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Guru <span style="color:red">*</span></label>
                <select name="guru_id" class="form-select" required>
                    <option value="">-- Pilih Guru --</option>
                    <?php while ($g = $guru_list->fetch_assoc()): ?>
                        <option value="<?= $g['id'] ?>"><?= e($g['nama_lengkap']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tahun Ajaran <span style="color:red">*</span></label>
                    <select name="tahun_ajaran_id" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php $tahun_ajaran_list->data_seek(0); while ($ta = $tahun_ajaran_list->fetch_assoc()): ?>
                            <option value="<?= $ta['id'] ?>" <?= $ta['is_active'] ? 'selected' : '' ?>>
                                <?= e($ta['tahun']) ?> <?= $ta['is_active'] ? '(Aktif)' : '' ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester <span style="color:red">*</span></label>
                    <select name="semester" class="form-select" required>
                        <option value="1">Semester 1 (Ganjil)</option>
                        <option value="2">Semester 2 (Genap)</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="tambah" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Penugasan
            </button>
        </form>
    </div>

    <!-- Daftar + Filter -->
    <div class="form-container" style="grid-column:span 2;">
        <div class="form-title"><i class="fas fa-filter"></i> Filter &amp; Daftar Penugasan</div>
        <form method="GET" class="form-row" style="margin-bottom:1rem;">
            <div class="form-group">
                <label>Tahun Ajaran</label>
                <select name="tahun_ajaran_id" class="form-select">
                    <option value="0">Semua</option>
                    <?php $tahun_ajaran_list->data_seek(0); while ($ta = $tahun_ajaran_list->fetch_assoc()): ?>
                        <option value="<?= $ta['id'] ?>" <?= $filter_tahun == $ta['id'] ? 'selected' : '' ?>>
                            <?= e($ta['tahun']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Semester</label>
                <select name="semester" class="form-select">
                    <option value="">Semua</option>
                    <option value="1" <?= $filter_semester === '1' ? 'selected' : '' ?>>Semester 1</option>
                    <option value="2" <?= $filter_semester === '2' ? 'selected' : '' ?>>Semester 2</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; gap:8px; align-items:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                <a href="kelas_mapel" class="btn btn-outline"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>

        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>No</th><th>Tahun Ajaran</th><th>Semester</th>
                        <th>Kelas</th><th>Mata Pelajaran</th><th>Guru</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($kelas_mapel_data->num_rows === 0): ?>
                    <tr><td colspan="7" style="text-align:center;padding:30px;">Tidak ada data penugasan.</td></tr>
                <?php else:
                    $no = 1;
                    while ($km = $kelas_mapel_data->fetch_assoc()): ?>
                    <tr>
                        <td style="text-align:center"><?= $no++ ?></td>
                        <td><strong><?= e($km['tahun']) ?></strong></td>
                        <td><span class="badge-semester"><?= $km['semester'] == 1 ? 'Semester 1' : 'Semester 2' ?></span></td>
                        <td><?= e($km['nama_kelas']) ?></td>
                        <td><?= e($km['nama_mapel']) ?></td>
                        <td><?= e($km['nama_guru']) ?></td>
                        <td class="action-buttons">
                            <button class="btn btn-sm btn-primary"
                                onclick="editPenugasan(<?= htmlspecialchars(json_encode($km), ENT_QUOTES) ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?hapus=<?= $km['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Hapus penugasan ini?')">
                                <i class="fas fa-trash-alt"></i> Hapus
                            </a>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Edit (tetap single kelas) -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-edit"></i> Edit Penugasan</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Kelas</label>
                <select name="kelas_id" id="edit_kelas_id" class="form-select" required>
                    <?php 
                    $kelas_all->data_seek(0);
                    while ($k = $kelas_all->fetch_assoc()): ?>
                        <option value="<?= $k['id'] ?>"><?= e($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mata Pelajaran</label>
                <select name="mapel_id" id="edit_mapel_id" class="form-select" required>
                    <?php $mapel_list->data_seek(0); while ($m = $mapel_list->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['nama_mapel']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Guru</label>
                <select name="guru_id" id="edit_guru_id" class="form-select" required>
                    <?php $guru_list->data_seek(0); while ($g = $guru_list->fetch_assoc()): ?>
                        <option value="<?= $g['id'] ?>"><?= e($g['nama_lengkap']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tahun Ajaran</label>
                <select name="tahun_ajaran_id" id="edit_tahun_ajaran_id" class="form-select" required>
                    <?php $tahun_ajaran_list->data_seek(0); while ($ta = $tahun_ajaran_list->fetch_assoc()): ?>
                        <option value="<?= $ta['id'] ?>"><?= e($ta['tahun']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Semester</label>
                <select name="semester" id="edit_semester" class="form-select" required>
                    <option value="1">Semester 1 (Ganjil)</option>
                    <option value="2">Semester 2 (Genap)</option>
                </select>
            </div>
            <div style="display:flex; gap:8px; margin-top:1rem;">
                <button type="submit" name="edit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()"><i class="fas fa-times"></i> Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editPenugasan(data) {
    document.getElementById('edit_id').value              = data.id;
    document.getElementById('edit_kelas_id').value        = data.kelas_id;
    document.getElementById('edit_mapel_id').value        = data.mapel_id;
    document.getElementById('edit_guru_id').value         = data.guru_id;
    document.getElementById('edit_tahun_ajaran_id').value = data.tahun_ajaran_id;
    document.getElementById('edit_semester').value        = data.semester;
    document.getElementById('editModal').style.display    = 'flex';
}
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('editModal')) closeModal();
});
</script>

<?php include '../includes/footer.php'; ?>