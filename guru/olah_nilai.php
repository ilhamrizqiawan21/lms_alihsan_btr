<?php
include '../config.php';
cek_login([2]);
$title = 'Olah Nilai Akhir';
include '../includes/header.php';

$guru_id = $_SESSION['user_id'];
$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;

// Validasi akses
$km_check = null;
if ($kelas_mapel_id > 0) {
    $stmt = $conn->prepare("
        SELECT km.*, k.nama_kelas, mp.nama_mapel 
        FROM kelas_mapel km 
        JOIN kelas k ON km.kelas_id = k.id 
        JOIN mata_pelajaran mp ON km.mapel_id = mp.id 
        WHERE km.id = ? AND km.guru_id = ?
    ");
    $stmt->bind_param("ii", $kelas_mapel_id, $guru_id);
    $stmt->execute();
    $km_check = $stmt->get_result()->fetch_assoc();
    if (!$km_check) {
        die("Akses ditolak atau data tidak ditemukan.");
    }
}

// Proses simpan nilai (SUM1-4, STS, SAS, SAT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    csrf_verify();

    // Dapatkan tahun_ajaran_id
    $stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1 LIMIT 1");
    $stmt_ta->bind_param("s", $tahun_aktif);
    $stmt_ta->execute();
    $ta_id = $stmt_ta->get_result()->fetch_assoc()['id'] ?? 0;

    if (!$ta_id) {
        set_flash('error', 'Tahun ajaran aktif tidak ditemukan.');
        header("Location: olah_nilai?kelas_mapel_id=$kelas_mapel_id");
        exit;
    }

    foreach ($_POST['sum1'] as $siswa_id => $sum1) {
        $siswa_id = (int)$siswa_id;
        $sum1 = $sum1 !== '' ? (float)$sum1 : null;
        $sum2 = isset($_POST['sum2'][$siswa_id]) && $_POST['sum2'][$siswa_id] !== '' ? (float)$_POST['sum2'][$siswa_id] : null;
        $sum3 = isset($_POST['sum3'][$siswa_id]) && $_POST['sum3'][$siswa_id] !== '' ? (float)$_POST['sum3'][$siswa_id] : null;
        $sum4 = isset($_POST['sum4'][$siswa_id]) && $_POST['sum4'][$siswa_id] !== '' ? (float)$_POST['sum4'][$siswa_id] : null;
        $sts  = isset($_POST['sts'][$siswa_id]) && $_POST['sts'][$siswa_id] !== '' ? (float)$_POST['sts'][$siswa_id] : null;
        $sas  = isset($_POST['sas'][$siswa_id]) && $_POST['sas'][$siswa_id] !== '' ? (float)$_POST['sas'][$siswa_id] : null;
        $sat  = isset($_POST['sat'][$siswa_id]) && $_POST['sat'][$siswa_id] !== '' ? (float)$_POST['sat'][$siswa_id] : null;

        // Nilai harian diambil otomatis (tidak disimpan di sini, tapi dihitung ulang saat tampil)
        // Kita hanya update SUM1-4, STS, SAS, SAT. Nilai harian tetap dihitung via fungsi.
        $stmt_cek = $conn->prepare("
            SELECT id FROM nilai_akhir 
            WHERE siswa_id = ? AND kelas_mapel_id = ? AND tahun_ajaran_id = ? AND semester = ?
        ");
        $stmt_cek->bind_param("iiis", $siswa_id, $kelas_mapel_id, $ta_id, $semester_aktif);
        $stmt_cek->execute();
        $exists = $stmt_cek->get_result()->fetch_assoc();

        if ($exists) {
            $stmt_upd = $conn->prepare("
                UPDATE nilai_akhir 
                SET sum1 = ?, sum2 = ?, sum3 = ?, sum4 = ?, sts = ?, sas = ?, sat = ?
                WHERE siswa_id = ? AND kelas_mapel_id = ? AND tahun_ajaran_id = ? AND semester = ?
            ");
            $stmt_upd->bind_param("ddddddiiis", $sum1, $sum2, $sum3, $sum4, $sts, $sas, $sat, $siswa_id, $kelas_mapel_id, $ta_id, $semester_aktif);
            $stmt_upd->execute();
        } else {
            $stmt_ins = $conn->prepare("
                INSERT INTO nilai_akhir (siswa_id, kelas_mapel_id, tahun_ajaran_id, semester, sum1, sum2, sum3, sum4, sts, sas, sat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_ins->bind_param("iiisddddddd", $siswa_id, $kelas_mapel_id, $ta_id, $semester_aktif, $sum1, $sum2, $sum3, $sum4, $sts, $sas, $sat);
            $stmt_ins->execute();
        }
    }
    set_flash('success', 'Nilai berhasil disimpan.');
    header("Location: olah_nilai?kelas_mapel_id=$kelas_mapel_id");
    exit;
}

// Ambil data siswa dan nilai yang sudah ada
$siswa_nilai = null;
if ($kelas_mapel_id && $km_check) {
    $kelas_id = $km_check['kelas_id'];
    $stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND is_active = 1 LIMIT 1");
    $stmt_ta->bind_param("s", $tahun_aktif);
    $stmt_ta->execute();
    $ta_id = $stmt_ta->get_result()->fetch_assoc()['id'] ?? 0;

    $stmt_siswa = $conn->prepare("
        SELECT s.id, s.nis, u.nama_lengkap as nama,
               na.sum1, na.sum2, na.sum3, na.sum4, na.sts, na.sas, na.sat
        FROM siswa s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN nilai_akhir na ON na.siswa_id = s.id AND na.kelas_mapel_id = ? AND na.tahun_ajaran_id = ? AND na.semester = ?
        WHERE s.kelas_id = ?
        ORDER BY u.nama_lengkap
    ");
    $stmt_siswa->bind_param("iisi", $kelas_mapel_id, $ta_id, $semester_aktif, $kelas_id);
    $stmt_siswa->execute();
    $siswa_nilai = $stmt_siswa->get_result();
}

$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);

if (!$kelas_mapel_options || $kelas_mapel_options->num_rows == 0) {
    set_flash('warning', 'Anda belum memiliki penugasan kelas/mapel pada tahun/semester ini.');
}
?>

<style>
    /* Perbaikan lebar kolom di tabel olah nilai */
.modern-table th,
.modern-table td {
    white-space: nowrap;
}
.modern-table th:nth-child(2), /* kolom Nama */
.modern-table td:nth-child(2) {
    min-width: 180px;
    max-width: 250px;
    white-space: normal;
    word-break: break-word;
}
.modern-table th:nth-child(3), /* Nilai Harian */
.modern-table td:nth-child(3),
.modern-table th:nth-child(4), /* SUM1 */
.modern-table td:nth-child(4),
.modern-table th:nth-child(5),
.modern-table td:nth-child(5),
.modern-table th:nth-child(6),
.modern-table td:nth-child(6),
.modern-table th:nth-child(7),
.modern-table td:nth-child(7),
.modern-table th:nth-child(8),
.modern-table td:nth-child(8),
.modern-table th:nth-child(9),
.modern-table td:nth-child(9),
.modern-table th:nth-child(10),
.modern-table td:nth-child(10) {
    width: 70px;
    min-width: 70px;
}
.modern-table input[type="number"] {
    width: 65px;
    padding: 4px;
    font-size: 0.8rem;
}
@media (max-width: 768px) {
    .table-wrapper {
        overflow-x: auto;
    }
    .modern-table {
        min-width: 800px;
    }
}

</style>


<div class="page-header">
    <h2 class="page-title"><i class="fas fa-chart-line"></i> Olah Nilai Akhir</h2>
    <p class="page-subtitle">
        Input SUM1-4, STS, SAS, SAT. Nilai Harian otomatis dari tugas.
    </p>
</div>

<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Pilih Kelas & Mata Pelajaran</label>
            <select name="kelas_mapel_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Pilih --</option>
                <?php if ($kelas_mapel_options):
                    while($km = $kelas_mapel_options->fetch_assoc()): ?>
                    <option value="<?= $km['id'] ?>" <?= $kelas_mapel_id == $km['id'] ? 'selected' : '' ?>>
                        <?= e($km['nama_kelas']) ?> - <?= e($km['nama_mapel']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($kelas_mapel_id && $km_check && $siswa_nilai && $siswa_nilai->num_rows > 0): ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-container">
        <div class="form-title">
            <i class="fas fa-edit"></i> Nilai Kelas <?= e($km_check['nama_kelas']) ?> - <?= e($km_check['nama_mapel']) ?>
            <small>(Semester <?= $semester_aktif == 1 ? 'Ganjil' : 'Genap' ?> <?= e($tahun_aktif) ?>)</small>
        </div>
        <div class="table-wrapper">
            <table class="modern-table" id="nilaiTable">
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Nilai Harian</th>
                        <th>SUM1</th>
                        <th>SUM2</th>
                        <th>SUM3</th>
                        <th>SUM4</th>
                        <th>STS</th>
                        <th>SAS</th>
                        <th>SAT</th>
                        <th>Rata Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $siswa_nilai->fetch_assoc()):
                        $nilai_harian = get_rata_nilai_harian($conn, $row['id'], $kelas_mapel_id);
                        $rata = hitung_rata_akhir($row['sum1'], $row['sum2'], $row['sum3'], $row['sum4'], $nilai_harian, $row['sts'], $row['sas'], $row['sat']);
                    ?>
                    <tr>
                        <td><?= e($row['nis']) ?></td>
                        <td><strong><?= e($row['nama']) ?></strong></td>
                        <td>
                            <input type="text" readonly value="<?= $nilai_harian !== null ? $nilai_harian : '-' ?>" 
                                   style="width:80px; background:#f0f0f0; border:1px solid #ddd; text-align:center;">
                        </td>
                        <td><input type="number" name="sum1[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sum1'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sum2[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sum2'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sum3[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sum3'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sum4[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sum4'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sts[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sts'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sas[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sas'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sat[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sat'] ?>" class="form-input" style="width:80px"></td>
                        <td><strong><?= $rata ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="btn-group" style="margin-top:1rem;">
            <button type="submit" name="simpan_nilai" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Nilai</button>
        </div>
    </div>
</form>
<?php elseif ($kelas_mapel_id && $km_check): ?>
    <div class="form-container"><p>Tidak ada siswa di kelas ini.</p></div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>