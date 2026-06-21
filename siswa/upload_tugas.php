<?php
include '../config.php';
cek_login([3]);

$tugas_id = (int)($_GET['id'] ?? 0);
$title    = 'Kumpul Tugas';

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

// Cek apakah sudah mengumpulkan
$stmt_cek = $conn->prepare("SELECT id, status FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?");
$stmt_cek->bind_param("ii", $tugas_id, $siswa_id);
$stmt_cek->execute();
$cek = $stmt_cek->get_result()->fetch_assoc();

if ($cek && $cek['status'] === 'sudah') {
    set_flash('warning', 'Anda sudah mengumpulkan tugas ini. Gunakan tombol Edit jika ingin mengubah.');
    header('Location: tugas_saya.php');
    exit;
}

// Ambil data tugas
$stmt_tugas = $conn->prepare("
    SELECT t.*, mp.nama_mapel
    FROM tugas t
    JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id
    WHERE t.id = ?
");
$stmt_tugas->bind_param("i", $tugas_id);
$stmt_tugas->execute();
$tugas = $stmt_tugas->get_result()->fetch_assoc();

if (!$tugas) {
    set_flash('error', 'Tugas tidak ditemukan.');
    header('Location: tugas_saya.php');
    exit;
}

// ── Proses submit ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $teks       = trim($_POST['jawaban_teks'] ?? '');
    $file_names = [];  // array untuk menyimpan hasil upload
    $error      = null;
    $target_dir = "../uploads/tugas_siswa/";

    // Proses upload file (multi-file support)
    if (isset($_FILES['file_tugas']) && is_array($_FILES['file_tugas']['name'])) {
        $allowed      = ['pdf', 'jpg', 'jpeg'];
        $max_per_file = 10 * 1024 * 1024; // 10MB per file
        $max_total    = 40 * 1024 * 1024; // 40MB total (sesuai label form)

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_count = count($_FILES['file_tugas']['name']);

        // Hitung total size terlebih dahulu
        $total_size = 0;
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['file_tugas']['error'][$i] === UPLOAD_ERR_OK) {
                $total_size += $_FILES['file_tugas']['size'][$i];
            }
        }

        if ($total_size > $max_total) {
            $error = "Total ukuran file melebihi 40MB.";
        }

        if (!$error) {
            for ($i = 0; $i < $file_count; $i++) {
                // Lewati file yang gagal / tidak dipilih
                if ($_FILES['file_tugas']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $original_name = $_FILES['file_tugas']['name'][$i];
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                // Validasi ekstensi
                if (!in_array($ext, $allowed)) {
                    $error = "Format file tidak diizinkan: " . htmlspecialchars($original_name)
                           . ". Format yang diterima: " . implode(', ', $allowed);
                    break;
                }

                // Validasi ukuran per file
                if ($_FILES['file_tugas']['size'][$i] > $max_per_file) {
                    $error = "Ukuran file " . htmlspecialchars($original_name) . " melebihi 10MB.";
                    break;
                }

                // Generate nama file unik
                $unique_name = time() . '_' . $siswa_id . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest_path   = $target_dir . $unique_name;

                // Pindahkan file dari temporary
                if (!move_uploaded_file($_FILES['file_tugas']['tmp_name'][$i], $dest_path)) {
                    $error = "Gagal mengunggah file: " . htmlspecialchars($original_name);
                    break;
                }

                $file_names[] = [
                    'file_name' => $original_name,  // nama asli
                    'file_path' => $unique_name,     // nama tersimpan
                ];
            }
        }
    }

    // Validasi: minimal file atau teks jawaban
    if (!$error && empty($file_names) && $teks === '') {
        $error = "Harap isi jawaban teks atau upload file.";
    }

    if ($error) {
        set_flash('error', $error);
        header('Location: upload_tugas.php?id=' . $tugas_id);
        exit;
    }

    // Simpan ke pengumpulan_tugas
    $first_file = !empty($file_names) ? $file_names[0]['file_path'] : null;

    if ($cek) {
        // Update existing (status 'belum')
        $stmt_save = $conn->prepare("
            UPDATE pengumpulan_tugas
            SET status = 'sudah', file_upload = ?, teks_jawaban = ?, tanggal_kumpul = NOW()
            WHERE id = ?
        ");
        $stmt_save->bind_param("ssi", $first_file, $teks ?: null, $cek['id']);
    } else {
        // Insert baru
        $stmt_save = $conn->prepare("
            INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, status, file_upload, teks_jawaban, tanggal_kumpul)
            VALUES (?, ?, 'sudah', ?, ?, NOW())
        ");
        $teks_val = $teks !== '' ? $teks : null;
        $stmt_save->bind_param("iiss", $tugas_id, $siswa_id, $first_file, $teks_val);
    }

    if ($stmt_save->execute()) {
        $pengumpulan_id = $cek ? $cek['id'] : $conn->insert_id;

        // Hapus file lama jika update (status sebelumnya 'belum')
        if ($cek) {
            $stmt_old = $conn->prepare("SELECT file_path FROM pengumpulan_files WHERE pengumpulan_id = ?");
            $stmt_old->bind_param("i", $pengumpulan_id);
            $stmt_old->execute();
            $old_files = $stmt_old->get_result();
            while ($old = $old_files->fetch_assoc()) {
                $old_path = $target_dir . basename($old['file_path']);
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            $stmt_del = $conn->prepare("DELETE FROM pengumpulan_files WHERE pengumpulan_id = ?");
            $stmt_del->bind_param("i", $pengumpulan_id);
            $stmt_del->execute();
        }

        // Insert file records ke pengumpulan_files
        if (!empty($file_names)) {
            $stmt_file = $conn->prepare("
                INSERT INTO pengumpulan_files (pengumpulan_id, file_name, file_path)
                VALUES (?, ?, ?)
            ");
            foreach ($file_names as $f) {
                $stmt_file->bind_param("iss", $pengumpulan_id, $f['file_name'], $f['file_path']);
                $stmt_file->execute();
            }
        }

        set_flash('success', 'Tugas berhasil dikumpulkan.');
    } else {
        set_flash('error', 'Gagal menyimpan pengumpulan. Silakan coba lagi.');
    }

    // Kirim notifikasi ke guru
    $stmt_guru = $conn->prepare("SELECT guru_id FROM kelas_mapel WHERE id = (SELECT kelas_mapel_id FROM tugas WHERE id = ?)");
    $stmt_guru->bind_param("i", $tugas_id);
    $stmt_guru->execute();
    $guru = $stmt_guru->get_result()->fetch_assoc();
    if ($guru) {
        $nama_siswa = $_SESSION['nama'];
        tambah_notifikasi($conn, $guru['guru_id'], 'kumpul_tugas', 'Siswa mengumpulkan tugas', "$nama_siswa telah mengumpulkan tugas.", "../guru/tugas.php");
    }

    header('Location: tugas_saya.php');
    exit;
}

include '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-upload"></i> Kumpul Tugas</h2>
    <p class="page-subtitle">Unggah jawaban atau tulis jawaban teks Anda</p>
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
                      placeholder="Tulis jawaban Anda di sini..."></textarea>
        </div>
        <div class="form-group">
            <label>Upload File <small style="color:var(--gray-400)">(Bisa pilih banyak file sekaligus, maks total 40MB)</small></label>
            <input type="file" name="file_tugas[]" class="form-input"
                   accept=".pdf,.jpg,.jpeg" multiple>
            <small style="color:var(--gray-400)">Format: PDF, JPG, JPEG (Bisa pilih lebih dari 1 file)</small>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Kumpulkan Tugas
            </button>
            <a href="tugas_saya.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Batal
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
