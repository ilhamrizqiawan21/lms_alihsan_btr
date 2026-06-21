<?php
include '../config.php';
cek_login([2]);
$title = 'Input Nilai Sikap';
include '../includes/header.php';

$guru_id        = $_SESSION['user_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;

$kolom_spiritual = ['taqwa','kejujuran','disiplin','sabar','syukur','tawadhu'];
$kolom_sosial    = ['empati','kerjasama','toleransi','percaya_diri','komunikasi'];
$nilai_allowed   = ['', '1','2','3','4','5'];

// ✅ Validasi kepemilikan kelas_mapel
$km_check = null;
if ($kelas_mapel_id > 0) {
    $stmt_km = $conn->prepare(
        "SELECT km.*, k.nama_kelas, k.id as kelas_id
         FROM kelas_mapel km JOIN kelas k ON km.kelas_id = k.id
         WHERE km.id = ? AND km.guru_id = ? LIMIT 1"
    );
    $stmt_km->bind_param("ii", $kelas_mapel_id, $guru_id);
    $stmt_km->execute();
    $km_check = $stmt_km->get_result()->fetch_assoc();
}

// ========== AMBIL tahun_ajaran_id ==========
$ta_id = 0;
if ($tahun_aktif) {
    $stmt_ta = $conn->prepare("SELECT id FROM tahun_ajaran WHERE tahun=? AND is_active=1 LIMIT 1");
    $stmt_ta->bind_param("s", $tahun_aktif);
    $stmt_ta->execute();
    $ta_row = $stmt_ta->get_result()->fetch_assoc();
    $ta_id  = $ta_row['id'] ?? 0;
}

// ========== PROSES SIMPAN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_sikap'])) {
    csrf_verify();

    if (!$km_check || !$ta_id) {
        set_flash('warning', 'Akses tidak diizinkan.');
        header("Location: sikap?kelas_mapel_id=$kelas_mapel_id");
        exit;
    }

    // ✅ Fungsi UPSERT sikap dengan prepared statement
    $upsert_sikap = function($conn, int $siswa_id, int $km_id, int $ta_id, string $semester,
                              array $data, string $jenis) use ($kolom_spiritual, $kolom_sosial, $nilai_allowed) {
        $table   = $jenis === 'spiritual' ? 'sikap_spiritual' : 'sikap_sosial';
        $kolom   = $jenis === 'spiritual' ? $kolom_spiritual  : $kolom_sosial;

        // Bangun SET clause
        $set_parts = [];
        $vals      = [];
        foreach ($kolom as $col) {
            $v = isset($data[$col]) && in_array((string)$data[$col], $nilai_allowed) && $data[$col] !== ''
                 ? (int)$data[$col] : null;
            $set_parts[] = "$col = ?";
            $vals[]      = $v;
        }

        // Cek apakah sudah ada
        $stmt_cek = $conn->prepare(
            "SELECT id FROM $table WHERE siswa_id=? AND kelas_mapel_id=? AND tahun_ajaran_id=? AND semester=? LIMIT 1"
        );
        $stmt_cek->bind_param("iiis", $siswa_id, $km_id, $ta_id, $semester);
        $stmt_cek->execute();

        if ($stmt_cek->get_result()->num_rows > 0) {
            // UPDATE
            $sql   = "UPDATE $table SET " . implode(', ', $set_parts) .
                     " WHERE siswa_id=? AND kelas_mapel_id=? AND tahun_ajaran_id=? AND semester=?";
            $types = str_repeat('i', count($kolom)) . "iiis";
            $params = array_merge($vals, [$siswa_id, $km_id, $ta_id, $semester]);
        } else {
            // INSERT
            $cols  = implode(',', $kolom);
            $ph    = implode(',', array_fill(0, count($kolom), '?'));
            $sql   = "INSERT INTO $table (siswa_id, kelas_mapel_id, tahun_ajaran_id, semester, $cols)
                      VALUES (?,?,?,?,$ph)";
            $types = "iiis" . str_repeat('i', count($kolom));
            $params = array_merge([$siswa_id, $km_id, $ta_id, $semester], $vals);
        }

        $stmt = $conn->prepare($sql);
        // bind_param dengan spread — tipe nullable int butoh referensi
        $bind_args = [$types];
        foreach ($params as &$p) $bind_args[] = &$p;
        call_user_func_array([$stmt, 'bind_param'], $bind_args);
        $stmt->execute();
    };

    // Proses spiritual
    if (isset($_POST['spiritual']) && is_array($_POST['spiritual'])) {
        foreach ($_POST['spiritual'] as $siswa_id => $sp) {
            $upsert_sikap($conn, (int)$siswa_id, $kelas_mapel_id, $ta_id, $semester_aktif, $sp, 'spiritual');
        }
    }
    // Proses sosial
    if (isset($_POST['sosial']) && is_array($_POST['sosial'])) {
        foreach ($_POST['sosial'] as $siswa_id => $so) {
            $upsert_sikap($conn, (int)$siswa_id, $kelas_mapel_id, $ta_id, $semester_aktif, $so, 'sosial');
        }
    }

    set_flash('success', 'Nilai sikap berhasil disimpan.');
    header("Location: sikap?kelas_mapel_id=$kelas_mapel_id");
    exit;
}

// ========== DATA SISWA + SIKAP ==========
$siswa_sikap = null;
if ($km_check && $ta_id) {
    $kelas_id = $km_check['kelas_id'];
    $stmt_s = $conn->prepare(
        "SELECT s.id, s.nis, u.nama_lengkap as nama,
                sp.taqwa, sp.kejujuran, sp.disiplin, sp.sabar, sp.syukur, sp.tawadhu,
                so.empati, so.kerjasama, so.toleransi, so.percaya_diri, so.komunikasi
         FROM siswa s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN sikap_spiritual sp
             ON sp.siswa_id = s.id AND sp.kelas_mapel_id = ? AND sp.tahun_ajaran_id = ? AND sp.semester = ?
         LEFT JOIN sikap_sosial so
             ON so.siswa_id = s.id AND so.kelas_mapel_id = ? AND so.tahun_ajaran_id = ? AND so.semester = ?
         WHERE s.kelas_id = ?
         ORDER BY u.nama_lengkap"
    );
    $stmt_s->bind_param("iisiisi",
        $kelas_mapel_id, $ta_id, $semester_aktif,
        $kelas_mapel_id, $ta_id, $semester_aktif,
        $kelas_id
    );
    $stmt_s->execute();
    $siswa_sikap = $stmt_s->get_result();
}

$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);

if (!$kelas_mapel_options || $kelas_mapel_options->num_rows == 0) {
    set_flash('warning', 'Anda belum memiliki penugasan kelas/mapel pada tahun/semester ini.');
}

// Label nilai
$label_nilai = [1=>'TB', 2=>'KB', 3=>'C', 4=>'B', 5=>'SB'];
?>

<style>
.modern-table td, .modern-table th { padding:0.5rem 0.35rem; font-size:0.78rem; vertical-align:middle; }
.sikap-select { width:90px; padding:0.25rem; font-size:0.72rem; border-radius:8px; }
@media(max-width:768px){ .modern-table{ min-width:800px; } }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-heart"></i> Input Nilai Sikap</h2>
    <p class="page-subtitle">
        KI-1 (Spiritual) &amp; KI-2 (Sosial) &mdash;
        TA <?= e($tahun_aktif) ?> Semester <?= $semester_aktif == '1' ? 'Ganjil' : 'Genap' ?>
    </p>
</div>

<?= show_flash(); ?>

<!-- Pilih Kelas -->
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Pilih Kelas &amp; Mata Pelajaran</label>
            <select name="kelas_mapel_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Pilih --</option>
                <?php if ($kelas_mapel_options):
                    while ($km = $kelas_mapel_options->fetch_assoc()): ?>
                    <option value="<?= $km['id'] ?>" <?= $kelas_mapel_id == $km['id'] ? 'selected' : '' ?>>
                        <?= e($km['nama_kelas']) ?> &mdash; <?= e($km['nama_mapel']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($km_check && $siswa_sikap && $siswa_sikap->num_rows > 0): ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- Sikap Spiritual -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-praying-hands"></i> Sikap Spiritual (KI-1) &mdash; <?= e($km_check['nama_kelas']) ?></div>
        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th><th>NIS</th><th>Nama</th>
                        <th>Taqwa</th><th>Jujur</th><th>Disiplin</th>
                        <th>Sabar</th><th>Syukur</th><th>Tawadhu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; $rows = []; while ($row = $siswa_sikap->fetch_assoc()) $rows[] = $row; ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td style="text-align:center"><?= $no++ ?></td>
                        <td><?= e($row['nis']) ?></td>
                        <td><strong><?= e($row['nama']) ?></strong></td>
                        <?php foreach ($kolom_spiritual as $col): ?>
                        <td>
                            <select name="spiritual[<?= $row['id'] ?>][<?= $col ?>]" class="form-select sikap-select">
                                <option value="">--</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string)($row[$col] ?? '') === (string)$i ? 'selected' : '' ?>>
                                        <?= $i ?> (<?= $label_nilai[$i] ?>)
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sikap Sosial -->
    <div class="form-container">
        <div class="form-title"><i class="fas fa-handshake"></i> Sikap Sosial (KI-2)</div>
        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th><th>NIS</th><th>Nama</th>
                        <th>Empati</th><th>Kerjasama</th><th>Toleransi</th>
                        <th>Percaya Diri</th><th>Komunikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rows as $row): ?>
                    <tr>
                        <td style="text-align:center"><?= $no++ ?></td>
                        <td><?= e($row['nis']) ?></td>
                        <td><strong><?= e($row['nama']) ?></strong></td>
                        <?php foreach ($kolom_sosial as $col): ?>
                        <td>
                            <select name="sosial[<?= $row['id'] ?>][<?= $col ?>]" class="form-select sikap-select">
                                <option value="">--</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string)($row[$col] ?? '') === (string)$i ? 'selected' : '' ?>>
                                        <?= $i ?> (<?= $label_nilai[$i] ?>)
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem; display:flex; justify-content:flex-end;">
            <button type="submit" name="simpan_sikap" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Nilai Sikap
            </button>
        </div>
    </div>
</form>

<?php elseif ($kelas_mapel_id > 0 && $km_check): ?>
<div class="form-container">
    <p style="color:var(--gray-500);"><i class="fas fa-info-circle"></i> Tidak ada siswa di kelas ini.</p>
</div>
<?php elseif ($kelas_mapel_id > 0): ?>
<div class="form-container">
    <p style="color:#dc2626;"><i class="fas fa-ban"></i> Akses tidak diizinkan.</p>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>