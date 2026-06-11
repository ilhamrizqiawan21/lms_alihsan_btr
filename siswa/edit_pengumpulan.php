<?php
include '../config.php';
cek_login([3]);

$tugas_id = (int)($_GET['id'] ?? 0);
$title    = 'Edit Pengumpulan Tugas';

if ($tugas_id <= 0) {
    set_flash('error', 'ID tugas tidak valid.');
    header('Location: tugas_saya.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil siswa_id
$stmt = $conn->prepare("SELECT id FROM siswa WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();

if (!$siswa) {
    set_flash('error', 'Data siswa tidak ditemukan.');
    header('Location: tugas_saya.php');
    exit;
}
$siswa_id = (int)$siswa['id'];

// Ambil data pengumpulan yang sudah ada
$stmt_p = $conn->prepare("SELECT * FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?");
$stmt_p->bind_param("ii", $tugas_id, $siswa_id);
$stmt_p->execute();
$pengumpulan = $stmt_p->get_result()->fetch_assoc();

if (!$pengumpulan) {
    set_flash('error', 'Data pengumpulan tidak ditemukan.');
    header('Location: tugas_saya.php');
    exit;
}

// Ambil data tugas untuk tampilan
$stmt_t = $conn->prepare("
    SELECT t.judul, t.deskripsi, t.batas_waktu, mp.nama_mapel
    FROM tugas t
    JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id
    WHERE t.id = ?
");
$stmt_t->bind_param("i", $tugas_id);
$stmt_t->execute();
$tugas = $stmt_t->get_result()->fetch_assoc();

if (!$tugas) {
    set_flash('error', 'Data tugas tidak ditemukan.');
    header('Location: tugas_saya.php');
    exit;
}

// ── Proses simpan perubahan ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $teks      = trim($_POST['jawaban_teks'] ?? '');
    $file_name = $pengumpulan['file_upload']; // Pertahankan file lama secara default
    $error     = null;
    $target_dir = "../uploads/tugas_siswa/";

    // Proses upload file baru jika ada
    if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf','jpg','jpeg'];
        $ext     = strtolower(pathinfo($_FILES['file_tugas']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Format file tidak diizinkan. Format yang diterima: " . implode(', ', $allowed);
        } elseif ($_FILES['file_tugas']['size'] > 10 * 1024 * 1024) {
            $error = "Ukuran file maksimal 10MB.";
        } else {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $new_file = time() . '_' . $siswa_id . '.' . $ext;
            if (move_uploaded_file($_FILES['file_tugas']['tmp_name'], $target_dir . $new_file)) {
                // Hapus file lama jika ada
                if ($file_name && file_exists($target_dir . $file_name)) {
                    unlink($target_dir . $file_name);
                }
                $file_name = $new_file;
            } else {
                $error = "Gagal mengunggah file. Silakan coba lagi.";
            }
        }
    }

    // Validasi: minimal ada file atau teks jawaban
    if (!$error && empty($file_name) && $teks === '') {
        $error = "Harap isi jawaban teks atau upload file.";
    }

    if ($error) {
        set_flash('error', $error);
        header('Location: edit_pengumpulan.php?id=' . $tugas_id);
        exit;
    }

    // Update dengan prepared statement
    $teks_val = $teks !== '' ? $teks : null;
    $stmt_upd = $conn->prepare("
        UPDATE pengumpulan_tugas
        SET file_upload = ?, teks_jawaban = ?
        WHERE id = ?
    ");
    $stmt_upd->bind_param("ssi", $file_name, $teks_val, $pengumpulan['id']);

    if ($stmt_upd->execute()) {
        set_flash('success', 'Pengumpulan berhasil diperbarui.');
    } else {
        set_flash('error', 'Gagal memperbarui pengumpulan. Silakan coba lagi.');
    }

    // Setelah $stmt_save->execute() berhasil
// Dapatkan guru_id dari kelas_mapel
$stmt_guru = $conn->prepare("SELECT guru_id FROM kelas_mapel WHERE id = (SELECT kelas_mapel_id FROM tugas WHERE id = ?)");
$stmt_guru->bind_param("i", $tugas_id);
$stmt_guru->execute();
$guru = $stmt_guru->get_result()->fetch_assoc();
if ($guru) {
    // Ambil nama siswa dari session atau database
    $nama_siswa = $_SESSION['nama'];
    tambah_notifikasi($conn, $guru['guru_id'], 'kumpul_tugas', 'Siswa mengumpulkan tugas', "$nama_siswa telah mengumpulkan tugas.", "../guru/tugas.php");
}

    header('Location: tugas_saya.php');
    exit;
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-edit"></i> Edit Pengumpulan Tugas</h2>
    <p class="page-subtitle">Perbarui jawaban atau file yang sudah dikumpulkan</p>
</div>

<?= show_flash(); ?>

<div class="form-container">
    <div class="form-title">
        <?= htmlspecialchars($tugas['judul']) ?>
        <small style="font-weight:400; color:var(--gray-400)"> — <?= htmlspecialchars($tugas['nama_mapel']) ?></small>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Deskripsi / Soal</label>
            <div class="form-input" style="background:#f1f5f9; min-height:60px;">
                <?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?>
            </div>
        </div>
        <div class="form-group">
            <label>Batas Waktu</label>
            <div class="form-input" style="background:#f1f5f9;">
                <?= $tugas['batas_waktu'] ? tgl_indonesia($tugas['batas_waktu']) : '-' ?>
            </div>
        </div>
        <div class="form-group">
            <label>Jawaban Teks <small style="color:var(--gray-400)">(opsional jika ada file)</small></label>
            <textarea name="jawaban_teks" class="form-textarea" rows="6"
                      placeholder="Tulis jawaban Anda di sini..."><?= htmlspecialchars($pengumpulan['teks_jawaban'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Upload File Baru <small style="color:var(--gray-400)">(kosongkan jika tidak ingin mengganti)</small></label>
            <input type="file" name="file_tugas" class="form-input"
                   accept=".pdf,.jpg,.jpeg">
            <small style="color:var(--gray-400)">Format: PDF, JPG, JPEG — maks 10MB</small>
            <?php if ($pengumpulan['file_upload']): ?>
                <div style="margin-top:8px; padding:8px 12px; background:#f1f5f9; border-radius:6px; font-size:0.85rem;">
                    <i class="fas fa-paperclip"></i> File saat ini:
                    <a href="../uploads/tugas_siswa/<?= htmlspecialchars($pengumpulan['file_upload']) ?>"
                       target="_blank" style="color:var(--primary)">
                        <?= htmlspecialchars($pengumpulan['file_upload']) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
            <a href="tugas_saya.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Batal
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>