<?php
include '../config.php';
cek_login([3]);
$title = 'Tugas Saya';

$user_id = $_SESSION['user_id'];

// Ambil data siswa dengan prepared statement
$stmt = $conn->prepare("SELECT s.id, s.kelas_id FROM siswa s WHERE s.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();

if (!$siswa) {
    set_flash('error', 'Data siswa tidak ditemukan.');
    header('Location: ../index.php');
    exit;
}

$siswa_id = (int)$siswa['id'];
$kelas_id = (int)$siswa['kelas_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

// ── Proses hapus pengumpulan ──────────────────────────────────────────────────
if (isset($_GET['hapus_pengumpulan'])) {
    $tugas_id = (int)$_GET['hapus_pengumpulan'];
    $token    = $_GET['token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        set_flash('error', 'Token keamanan tidak valid.');
        header('Location: tugas_saya.php');
        exit;
    }

    $stmt_cek = $conn->prepare("SELECT file_upload FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?");
    $stmt_cek->bind_param("ii", $tugas_id, $siswa_id);
    $stmt_cek->execute();
    $data = $stmt_cek->get_result()->fetch_assoc();

    if ($data) {
        // Hapus file fisik jika ada
        if ($data['file_upload']) {
            $file_path = "../uploads/tugas_siswa/" . $data['file_upload'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $stmt_del = $conn->prepare("DELETE FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?");
        $stmt_del->bind_param("ii", $tugas_id, $siswa_id);
        $stmt_del->execute();
        set_flash('success', 'Pengumpulan tugas berhasil dihapus.');
    } else {
        set_flash('error', 'Data pengumpulan tidak ditemukan.');
    }

    header('Location: tugas_saya.php');
    exit;
}

// ── Ambil daftar tugas ────────────────────────────────────────────────────────
$stmt_tugas = $conn->prepare("
    SELECT t.*, mp.nama_mapel,
           pt.id       AS pengumpulan_id,
           pt.status,
           pt.nilai,
           pt.file_upload,
           pt.teks_jawaban,
           pt.catatan,
           pt.tanggal_kumpul
    FROM tugas t
    JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
    WHERE km.kelas_id = ?
      AND km.tahun_ajaran_id = (SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1 LIMIT 1)
      AND km.semester = ?
    ORDER BY t.batas_waktu ASC, t.created_at DESC
");
$stmt_tugas->bind_param("iiss", $siswa_id, $kelas_id, $tahun_aktif, $semester_aktif);
$stmt_tugas->execute();
$tugas_list = $stmt_tugas->get_result();

include '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-tasks"></i> Tugas Saya</h2>
    <p class="page-subtitle">Daftar tugas untuk semester <?= htmlspecialchars($semester_aktif) ?> tahun ajaran <?= htmlspecialchars($tahun_aktif) ?></p>
</div>

<div class="form-container">
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Mapel</th>
                    <th>Judul Tugas</th>
                    <th>Deskripsi</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Nilai</th>
                    <th>Komentar Guru</th>
                    <th>File / Jawaban</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tugas_list->num_rows === 0): ?>
                    <tr><td colspan="9" style="text-align:center">Belum ada tugas untuk semester ini.</td></tr>
                <?php else: ?>
                    <?php while ($t = $tugas_list->fetch_assoc()):
                        $status      = $t['status'] ?? 'belum';
                        $sudah_kumpul = ($status === 'sudah');
                        $nilai   = $t['nilai'];
                        $catatan = $t['catatan'];
                        $file    = $t['file_upload'];
                        $jawaban = $t['teks_jawaban'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['nama_mapel']) ?></strong></td>
                        <td><?= htmlspecialchars($t['judul']) ?></td>
                        <td><?= nl2br(htmlspecialchars($t['deskripsi'])) ?></td>
                        <td><?= $t['batas_waktu'] ? tgl_indonesia($t['batas_waktu']) : '-' ?></td>
                        <td>
                            <?php if ($sudah_kumpul): ?>
                                <span class="badge-sudah"><i class="fas fa-check"></i> Sudah</span>
                            <?php else: ?>
                                <span class="badge-belum"><i class="fas fa-clock"></i> Belum</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $nilai !== null ? '<span class="badge-nilai">' . htmlspecialchars($nilai) . '</span>' : '-' ?>
                        </td>
                        <td>
                            <?php if ($catatan): ?>
                                <div class="komentar-guru">
                                    <i class="fas fa-comment-dots"></i>
                                    <?= nl2br(htmlspecialchars($catatan)) ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            // Ambil semua file untuk pengumpulan ini
                            if ($sudah_kumpul) {
                                $stmt_f = $conn->prepare("SELECT file_name, file_path FROM pengumpulan_files WHERE pengumpulan_id = ?");
                                $stmt_f->bind_param("i", $t['pengumpulan_id']);
                                $stmt_f->execute();
                                $files_res = $stmt_f->get_result();
                                
                                if ($files_res->num_rows > 0) {
                                    while ($f = $files_res->fetch_assoc()) {
                                        echo '<a href="../uploads/tugas_siswa/'.htmlspecialchars($f['file_path']).'" target="_blank" class="btn btn-sm btn-primary" style="margin-bottom:4px; display:inline-block;">
                                                <i class="fas fa-file"></i> '.htmlspecialchars($f['file_name']).'
                                              </a><br>';
                                    }
                                } elseif ($file) { // Fallback untuk data lama
                                    echo '<a href="../uploads/tugas_siswa/'.htmlspecialchars($file).'" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="fas fa-file"></i> Lihat File
                                          </a>';
                                }
                            }
                            ?>
                            <?php if ($jawaban): ?>
                                <button class="btn btn-sm btn-outline"
                                        onclick="document.getElementById('jawaban-<?= $t['id'] ?>').style.display='block'">
                                    <i class="fas fa-eye"></i> Lihat Jawaban
                                </button>
                                <div id="jawaban-<?= $t['id'] ?>" style="display:none; margin-top:6px; padding:8px; background:#f8fafc; border-radius:6px; font-size:0.85rem;">
                                    <?= nl2br(htmlspecialchars($jawaban)) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$file && !$jawaban): ?>-<?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$sudah_kumpul): ?>
                                <a href="upload_tugas.php?id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-upload"></i> Kumpul
                                </a>
                            <?php else: ?>
                                <a href="edit_pengumpulan.php?id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?hapus_pengumpulan=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   data-confirm="Yakin hapus pengumpulan ini?">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>