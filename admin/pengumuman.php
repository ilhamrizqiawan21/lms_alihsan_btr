<?php
// admin/pengumuman.php - untuk role admin, guru, kepala sekolah
include '../config.php';


// Izinkan role 1 (admin), 2 (guru), 4 (kepsek)
if (!in_array($_SESSION['role_id'], [1, 2, 4])) {
    header('Location: ../index.php');
    exit;
}

$title    = 'Kelola Pengumuman';
$role_id  = $_SESSION['role_id'];
$user_id  = $_SESSION['user_id'];

// ── Ambil daftar kelas ────────────────────────────────────────────────────────
// Guru hanya melihat kelas yang diampu; admin & kepsek melihat semua.
if ($role_id == 2) {
    $stmt = $conn->prepare("
        SELECT DISTINCT k.id, k.nama_kelas
        FROM kelas k
        JOIN kelas_mapel km ON km.kelas_id = k.id
        WHERE km.guru_id = ?
          AND km.tahun_ajaran_id = (SELECT id FROM tahun_ajaran WHERE is_active = 1 LIMIT 1)
          AND km.semester        = (SELECT value FROM pengaturan WHERE `key` = 'semester_aktif' LIMIT 1)
        ORDER BY k.nama_kelas
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $kelas_res = $stmt->get_result();
} else {
    $kelas_res = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
}
$kelas_list = $kelas_res->fetch_all(MYSQLI_ASSOC);

// ── Proses TAMBAH ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    csrf_verify();

    $judul  = trim($_POST['judul']  ?? '');
    $isi    = trim($_POST['isi']    ?? '');
    $target = $_POST['target'] ?? 'semua';

    // Validasi sederhana
    if ($judul === '' || $isi === '') {
        set_flash('error', 'Judul dan isi pengumuman tidak boleh kosong.');
    } elseif (!in_array($target, ['semua', 'guru', 'siswa', 'kelas'])) {
        set_flash('error', 'Target pengumuman tidak valid.');
    } else {
        $target_kelas = '';
        if ($target === 'kelas') {
            $ids_raw = $_POST['target_kelas_ids'] ?? [];
            if (empty($ids_raw)) {
                set_flash('error', 'Pilih minimal satu kelas tujuan.');
                header('Location: pengumuman.php');
                exit;
            }
            // Validasi setiap ID adalah integer positif
            $ids_clean = array_filter(array_map('intval', $ids_raw), fn($v) => $v > 0);
            $target_kelas = implode(',', $ids_clean);
        }

        $stmt = $conn->prepare("
            INSERT INTO pengumuman (judul, isi, target, target_kelas, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssssi", $judul, $isi, $target, $target_kelas, $user_id);

        if ($stmt->execute()) {
            set_flash('success', 'Pengumuman berhasil ditambahkan.');
        } else {
            set_flash('error', 'Gagal menyimpan pengumuman. Silakan coba lagi.');
        }
    }

    header('Location: pengumuman.php');
    exit;
}

// ── Proses HAPUS (hanya admin) ────────────────────────────────────────────────
if (isset($_GET['hapus']) && $role_id == 1) {
    $id = (int)$_GET['hapus'];

    if ($id <= 0) {
        set_flash('error', 'ID pengumuman tidak valid.');
    } else {
        // Cek dulu apakah data ada
        $cek = $conn->prepare("SELECT id FROM pengumuman WHERE id = ?");
        $cek->bind_param("i", $id);
        $cek->execute();
        if ($cek->get_result()->num_rows === 0) {
            set_flash('error', 'Pengumuman tidak ditemukan.');
        } else {
            $stmt = $conn->prepare("DELETE FROM pengumuman WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                set_flash('success', 'Pengumuman berhasil dihapus.');
            } else {
                set_flash('error', 'Gagal menghapus pengumuman.');
            }
        }
    }

    header('Location: pengumuman.php');
    exit;
}

// ── Proses EDIT (hanya admin) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit']) && $role_id == 1) {
    $id    = (int)($_POST['id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $isi   = trim($_POST['isi']   ?? '');

    if ($id <= 0 || $judul === '' || $isi === '') {
        set_flash('error', 'Data tidak lengkap untuk memperbarui pengumuman.');
    } else {
        $stmt = $conn->prepare("UPDATE pengumuman SET judul = ?, isi = ? WHERE id = ?");
        $stmt->bind_param("ssi", $judul, $isi, $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_flash('success', 'Pengumuman berhasil diperbarui.');
        } else {
            set_flash('error', 'Gagal memperbarui pengumuman atau tidak ada perubahan.');
        }
    }

    header('Location: pengumuman.php');
    exit;
}

// ── Ambil daftar pengumuman ───────────────────────────────────────────────────
$pengumuman = $conn->query("
    SELECT p.*, u.nama_lengkap AS penulis
    FROM pengumuman p
    JOIN users u ON p.created_by = u.id
    ORDER BY p.created_at DESC
");

// Siapkan lookup nama kelas untuk display (satu query, bukan N query di loop)
$kelas_map = [];
$res_kelas = $conn->query("SELECT id, nama_kelas FROM kelas");
while ($row = $res_kelas->fetch_assoc()) {
    $kelas_map[$row['id']] = $row['nama_kelas'];
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title">Kelola Pengumuman</h2>
    <p class="page-subtitle">Tambah, edit, atau hapus pengumuman yang akan ditampilkan</p>
</div>
<?= show_flash(); ?>

<!-- ── Form Tambah ──────────────────────────────────────────────────────────── -->
<div class="form-container">
    <div class="form-title">Tambah Pengumuman Baru</div>
    <form method="POST"> 
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Judul</label>
            <input type="text" name="judul" class="form-input" maxlength="200" required>
        </div>
        <div class="form-group">
            <label>Isi Pengumuman</label>
            <textarea name="isi" class="form-textarea" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label>Target Pengumuman</label><br>
            <label><input type="radio" name="target" value="semua" checked> Semua Pengguna</label><br>
            <label><input type="radio" name="target" value="kelas" id="target_kelas_radio"> Kelas Tertentu</label>
        </div>
        <div class="form-group" id="kelas_checkbox_group" style="display:none;">
            <label>Pilih Kelas Tujuan</label><br>
            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <?php foreach ($kelas_list as $kelas): ?>
                    <label>
                        <input type="checkbox" name="target_kelas_ids[]" value="<?= $kelas['id'] ?>">
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" name="tambah" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Simpan Pengumuman
        </button>
    </form>
</div>

<!-- ── Daftar Pengumuman ────────────────────────────────────────────────────── -->
<div class="form-container">
    <div class="form-title">Daftar Pengumuman</div>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Judul</th>
                    <th>Isi</th>
                    <th>Penulis</th>
                    <th>Target</th>
                    <th>Tanggal</th>
                    <?php if ($role_id == 1): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($pengumuman && $pengumuman->num_rows > 0):
                    $no = 1;
                    while ($p = $pengumuman->fetch_assoc()):
                        // Resolusi target dari lookup array, bukan query di dalam loop
                        if ($p['target'] === 'semua') {
                            $label_target = 'Semua Pengguna';
                        } elseif ($p['target'] === 'kelas' && $p['target_kelas'] !== '') {
                            $ids   = explode(',', $p['target_kelas']);
                            $names = array_map(fn($id) => $kelas_map[(int)$id] ?? "Kelas #$id", $ids);
                            $label_target = 'Kelas: ' . implode(', ', $names);
                        } else {
                            $label_target = ucfirst($p['target']);
                        }
                ?>
                <tr>
                    <td style="text-align:center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($p['judul']) ?></td>
                    <td><?= nl2br(htmlspecialchars($p['isi'])) ?></td>
                    <td><?= htmlspecialchars($p['penulis']) ?></td>
                    <td><?= htmlspecialchars($label_target) ?></td>
                    <td><?= tgl_indonesia($p['created_at']) ?></td>
                    <?php if ($role_id == 1): ?>
                    <td>
                        <a href="?hapus=<?= $p['id'] ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="Hapus pengumuman ini?">
                            <i class="fas fa-trash"></i> Hapus
                        </a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" style="text-align:center">Belum ada pengumuman.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
// Toggle tampilan checkbox kelas
document.querySelectorAll('input[name="target"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.getElementById('kelas_checkbox_group').style.display =
            (this.value === 'kelas') ? 'block' : 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>