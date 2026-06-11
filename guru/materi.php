<?php
include '../config.php';
cek_login([2]);

$guru_id        = $_SESSION['user_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);

// ========== HAPUS MATERI ==========
if (isset($_GET['hapus_materi'])) {
    $id = (int)$_GET['hapus_materi'];
    // ✅ Pastikan materi milik guru ini
    $stmt_cek = $conn->prepare(
        "SELECT m.file_materi FROM materi m
         JOIN kelas_mapel km ON m.kelas_mapel_id = km.id
         WHERE m.id = ? AND km.guru_id = ? LIMIT 1"
    );
    $stmt_cek->bind_param("ii", $id, $guru_id);
    $stmt_cek->execute();
    $file_row = $stmt_cek->get_result()->fetch_assoc();
    if ($file_row) {
        if ($file_row['file_materi']) {
            $path = "../uploads/materi/" . basename($file_row['file_materi']);
            if (file_exists($path)) unlink($path);
        }
        $del = $conn->prepare("DELETE FROM materi WHERE id=?");
        $del->bind_param("i", $id);
        $del->execute();
        set_flash('success', 'Materi dihapus.');
    } else {
        set_flash('warning', 'Materi tidak ditemukan atau akses ditolak.');
    }
    $filter_km = isset($_GET['filter_km']) ? (int)$_GET['filter_km'] : 0;
    header("Location: materi?filter_km=$filter_km");
    exit;
}

// ========== SIMPAN MATERI ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_materi'])) {
    csrf_verify();

    $judul     = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $km_ids    = isset($_POST['kelas_mapel_ids']) && is_array($_POST['kelas_mapel_ids'])
                 ? array_map('intval', $_POST['kelas_mapel_ids']) : [];

    if (empty($km_ids)) {
        set_flash('warning', 'Pilih minimal satu kelas!');
        header('Location: materi');
        exit;
    }

    // ✅ Validasi semua km_id milik guru ini
    $ph    = implode(',', array_fill(0, count($km_ids), '?'));
    $types = str_repeat('i', count($km_ids)) . 'i';
    $params = array_merge($km_ids, [$guru_id]);
    $stmt_v = $conn->prepare("SELECT COUNT(*) as c FROM kelas_mapel WHERE id IN ($ph) AND guru_id = ?");
    $stmt_v->bind_param($types, ...$params);
    $stmt_v->execute();
    $valid_count = $stmt_v->get_result()->fetch_assoc()['c'];

    if ($valid_count != count($km_ids)) {
        set_flash('warning', 'Beberapa kelas tidak valid atau bukan milik Anda.');
        header('Location: materi');
        exit;
    }

    // ✅ Upload file dengan validasi
    $file_name = null;
    if (!empty($_FILES['file_materi']['name']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext  = ['pdf','jpg','jpeg'];
        $max_size     = 10 * 1024 * 1024; // 10MB
        $ext          = strtolower(pathinfo($_FILES['file_materi']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext)) {
            set_flash('warning', 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed_ext));
            header('Location: materi');
            exit;
        }
        if ($_FILES['file_materi']['size'] > $max_size) {
            set_flash('warning', 'Ukuran file maksimal 10MB.');
            header('Location: materi');
            exit;
        }

        $target_dir = "../uploads/materi/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $file_name  = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        move_uploaded_file($_FILES['file_materi']['tmp_name'], $target_dir . $file_name);
    }

    // ✅ Insert per kelas dengan prepared statement
    $sukses = 0;
    foreach ($km_ids as $km_id) {
        $stmt_ins = $conn->prepare(
            "INSERT INTO materi (kelas_mapel_id, judul, deskripsi, file_materi) VALUES (?, ?, ?, ?)"
        );
        $stmt_ins->bind_param("isss", $km_id, $judul, $deskripsi, $file_name);
        if ($stmt_ins->execute()) $sukses++;
    }
    set_flash('success', "Materi berhasil diupload ke $sukses kelas.");
    header('Location: materi');
    exit;
}

// ========== FILTER & DAFTAR ==========
$filter_km = isset($_GET['filter_km']) ? (int)$_GET['filter_km'] : 0;

$sql_materi = "SELECT m.*, k.nama_kelas, mp.nama_mapel
               FROM materi m
               JOIN kelas_mapel km ON m.kelas_mapel_id = km.id
               JOIN kelas k ON km.kelas_id = k.id
               JOIN mata_pelajaran mp ON km.mapel_id = mp.id
               WHERE km.guru_id = ?";
$params_m = [$guru_id];
$types_m  = 'i';

if ($filter_km > 0) {
    $sql_materi .= " AND m.kelas_mapel_id = ?";
    $params_m[] = $filter_km;
    $types_m   .= 'i';
}
$sql_materi .= " ORDER BY m.created_at DESC";

$stmt_m = $conn->prepare($sql_materi);
$stmt_m->bind_param($types_m, ...$params_m);
$stmt_m->execute();
$materi_list = $stmt_m->get_result();

// Icon file berdasarkan ekstensi
function icon_file(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf'           => 'fa-file-pdf',
        'doc','docx'    => 'fa-file-word',
        'ppt','pptx'    => 'fa-file-powerpoint',
        'jpg','jpeg','png','gif' => 'fa-file-image',
        default         => 'fa-file',
    };
}

$title = 'Kelola Materi';
include '../includes/header.php';


?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-book"></i> Kelola Materi</h2>
    <p class="page-subtitle">Upload materi ke satu atau beberapa kelas sekaligus.</p>
</div>

<?= show_flash(); ?>

<!-- Form Upload -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-upload"></i> Upload Materi Baru</div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Pilih Mata Pelajaran</label>
            <select id="mapel_select" class="form-select" required>
                <option value="">-- Pilih Mata Pelajaran --</option>
                <?php
                $temp_mapel = [];
                if ($kelas_mapel_options) {
                    $kelas_mapel_options->data_seek(0);
                    while ($km = $kelas_mapel_options->fetch_assoc()) {
                        if (!in_array($km['mapel_id'], $temp_mapel)) {
                            $temp_mapel[] = $km['mapel_id'];
                            echo "<option value='" . (int)$km['mapel_id'] . "'>" . e($km['nama_mapel']) . "</option>";
                        }
                    }
                }
                ?>
            </select>
        </div>
        <div class="form-group" id="kelas_container" style="display:none;">
            <label>Pilih Kelas Tujuan</label>
            <div id="kelas_checkbox_list" class="kelas-checkbox-grid">
                <span class="kelas-loading"><i class="fas fa-spinner fa-spin"></i> Memuat kelas...</span>
            </div>
            <div class="kelas-checkbox-actions">
                <button type="button" class="btn btn-sm btn-outline" onclick="toggleSemuaKelas(true)">
                    <i class="fas fa-check-double"></i> Pilih Semua
                </button>
                <button type="button" class="btn btn-sm btn-outline" onclick="toggleSemuaKelas(false)">
                    <i class="fas fa-times"></i> Batal Semua
                </button>
            </div>
        </div>
        <div class="form-group">
            <label>Judul Materi</label>
            <input type="text" name="judul" class="form-input" required>
        </div>
        <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="deskripsi" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>File Materi <small>(PDF / JPG / JPEG, maks 10MB)</small></label>
            <input type="file" name="file_materi" class="form-input"
                   accept=".pdf,.jpg,.jpeg">
        </div>
        <button type="submit" name="simpan_materi" class="btn btn-primary">
            <i class="fas fa-upload"></i> Upload Materi
        </button>
    </form>
</div>

<!-- Filter -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-filter"></i> Filter Materi</div>
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Filter Kelas &amp; Mapel</label>
            <select name="filter_km" class="form-select" onchange="this.form.submit()">
                <option value="0">-- Semua --</option>
                <?php if ($kelas_mapel_options) {
                    $kelas_mapel_options->data_seek(0);
                    while ($km = $kelas_mapel_options->fetch_assoc()):?>
                    <option value="<?= $km['id'] ?>" <?= $filter_km == $km['id'] ? 'selected' : '' ?>>
                        <?= e($km['nama_kelas']) ?> &mdash; <?= e($km['nama_mapel']) ?>
                    </option>
                <?php endwhile; } ?>
            </select>
        </div>
    </form>
</div>

<!-- Daftar Materi -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-list"></i> Daftar Materi</div>
    <?php if ($materi_list->num_rows > 0): ?>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>No</th><th>Kelas</th><th>Mapel</th><th>Judul</th>
                    <th>Deskripsi</th><th>File</th><th>Tanggal</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; while ($m = $materi_list->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center"><?= $no++ ?></td>
                    <td><?= e($m['nama_kelas']) ?></td>
                    <td><?= e($m['nama_mapel']) ?></td>
                    <td><strong><?= e($m['judul']) ?></strong></td>
                    <td style="max-width:200px;"><?= e(substr($m['deskripsi'], 0, 80)) ?><?= strlen($m['deskripsi']) > 80 ? '...' : '' ?></td>
                    <td>
                        <?php if ($m['file_materi']): ?>
                            <a href="../uploads/materi/<?= urlencode($m['file_materi']) ?>" target="_blank"
                               class="btn btn-sm btn-outline">
                                <i class="fas <?= icon_file($m['file_materi']) ?>"></i> Lihat
                            </a>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= tgl_indonesia($m['created_at']) ?></td>
                    <td>
                        <a href="?hapus_materi=<?= $m['id'] ?>&filter_km=<?= $filter_km ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Hapus materi ini?')">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color:var(--gray-500);">Belum ada materi. Upload materi di atas.</p>
    <?php endif; ?>
</div>

<style>
/* ── Checkbox grid kelas ── */
.kelas-checkbox-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    min-height: 54px;
    align-items: flex-start;
    align-content: flex-start;
}
.kelas-checkbox-item {
    display: flex;
    align-items: center;
    gap: 0;
}
.kelas-checkbox-item input[type="checkbox"] {
    display: none; /* sembunyikan checkbox asli, pakai label styled */
}
.kelas-checkbox-item label {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.85rem;
    background: white;
    border: 1.5px solid var(--gray-300);
    border-radius: var(--radius-full);
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--gray-700);
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
    white-space: nowrap;
}
.kelas-checkbox-item label:hover {
    border-color: var(--primary-500);
    background: var(--primary-50);
    color: var(--primary-700);
}
.kelas-checkbox-item input[type="checkbox"]:checked + label {
    background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
    border-color: var(--primary-700);
    color: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.kelas-checkbox-item input[type="checkbox"]:checked + label .cb-icon {
    display: inline-block;
}
.kelas-checkbox-item label .cb-icon {
    display: none;
    font-size: 0.7rem;
}
.kelas-loading {
    color: var(--gray-400);
    font-size: 0.83rem;
    padding: 0.25rem;
}
.kelas-checkbox-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}
</style>

<script>
// AJAX load kelas → render checkbox pill
document.getElementById('mapel_select').addEventListener('change', function () {
    const mapelId  = this.value;
    const container = document.getElementById('kelas_container');
    const list      = document.getElementById('kelas_checkbox_list');

    if (!mapelId) {
        container.style.display = 'none';
        list.innerHTML = '';
        return;
    }

    list.innerHTML = '<span class="kelas-loading"><i class="fas fa-spinner fa-spin"></i> Memuat kelas...</span>';
    container.style.display = 'block';

    fetch(`../ajax/get_kelas_by_mapel.php?mapel_id=${encodeURIComponent(mapelId)}`)
        .then(res => res.json())
        .then(data => {
            list.innerHTML = '';
            if (!Array.isArray(data) || data.length === 0) {
                list.innerHTML = '<span class="kelas-loading">Tidak ada kelas tersedia.</span>';
                return;
            }
            data.forEach((k, i) => {
                const uid  = 'km_' + k.kelas_mapel_id;
                const item = document.createElement('div');
                item.className = 'kelas-checkbox-item';
                item.innerHTML = `
                    <input type="checkbox" name="kelas_mapel_ids[]"
                           id="${uid}" value="${k.kelas_mapel_id}" checked>
                    <label for="${uid}">
                        <i class="fas fa-check cb-icon"></i>
                        ${escHtml(k.nama_kelas)}
                    </label>`;
                list.appendChild(item);
            });
        })
        .catch(() => {
            list.innerHTML = '<span class="kelas-loading" style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Gagal memuat data.</span>';
        });
});

function toggleSemuaKelas(check) {
    document.querySelectorAll('#kelas_checkbox_list input[type="checkbox"]')
        .forEach(cb => cb.checked = check);
}

function escHtml(str) {
    return String(str).replace(/[&<>"']/g, m =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
</script>

<?php include '../includes/footer.php'; ?>