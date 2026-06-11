<?php
include '../config.php';
cek_login([3]);
$title = 'Materi Saya';
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// ✅ Ambil kelas_id siswa
$stmt_s = $conn->prepare("SELECT s.kelas_id FROM siswa s WHERE s.user_id=? LIMIT 1");
$stmt_s->bind_param("i", $user_id);
$stmt_s->execute();
$siswa = $stmt_s->get_result()->fetch_assoc();

if (!$siswa) {
    echo '<div style="padding:2rem;color:#dc2626;">Data siswa tidak ditemukan.</div>';
    include '../includes/footer.php';
    exit;
}

$kelas_id       = $siswa['kelas_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

// ✅ Ambil tahun_ajaran_id
$stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun=? AND is_active=1 LIMIT 1");
$stmt_ta->bind_param("s", $tahun_aktif);
$stmt_ta->execute();
$ta_id = $stmt_ta->get_result()->fetch_assoc()['id'] ?? 0;

// ✅ Ambil materi dengan prepared statement
$materi_list = null;
if ($ta_id) {
    $stmt_m = $conn->prepare(
        "SELECT m.id, m.judul, m.deskripsi, m.file_materi, m.created_at,
                mp.nama_mapel
         FROM materi m
         JOIN kelas_mapel km ON m.kelas_mapel_id = km.id
         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
         WHERE km.kelas_id = ? AND km.tahun_ajaran_id = ? AND km.semester = ?
         ORDER BY mp.urutan, m.created_at DESC"
    );
    $stmt_m->bind_param("iis", $kelas_id, $ta_id, $semester_aktif);
    $stmt_m->execute();
    $materi_list = $stmt_m->get_result();
}

// Icon file
function icon_materi(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'           => 'fa-file-pdf text-danger',
        'doc','docx'    => 'fa-file-word text-primary',
        'ppt','pptx'    => 'fa-file-powerpoint',
        'jpg','jpeg','png','gif' => 'fa-file-image',
        default         => 'fa-file',
    };
}
?>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-book-open"></i> Materi Saya</h2>
    <p class="page-subtitle">
        Materi pembelajaran Semester <?= $semester_aktif == '1' ? 'Ganjil' : 'Genap' ?>
        &mdash; TA <?= e($tahun_aktif) ?>
    </p>
</div>

<div class="form-container">
    <?php if ($materi_list && $materi_list->num_rows > 0): ?>
    <div class="table-wrapper">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Mata Pelajaran</th>
                    <th>Judul Materi</th>
                    <th>Deskripsi</th>
                    <th>File</th>
                    <th>Tanggal Upload</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; while ($m = $materi_list->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center"><?= $no++ ?></td>
                    <td><strong><?= e($m['nama_mapel']) ?></strong></td>
                    <td><?= e($m['judul']) ?></td>
                    <td style="max-width:200px;">
                        <?= e(substr($m['deskripsi'], 0, 100)) ?><?= strlen($m['deskripsi']) > 100 ? '...' : '' ?>
                    </td>
                    <td>
                        <?php if ($m['file_materi']): ?>
                            <a href="../uploads/materi/<?= urlencode($m['file_materi']) ?>"
                               target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas <?= icon_materi($m['file_materi']) ?>"></i> Download
                            </a>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">Tidak ada file</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= tgl_indonesia($m['created_at']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div style="text-align:center; padding:2rem; color:var(--gray-500);">
            <i class="fas fa-book-open" style="font-size:3rem; opacity:0.3;"></i>
            <p style="margin-top:1rem;">Belum ada materi untuk kelas Anda pada semester ini.</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>