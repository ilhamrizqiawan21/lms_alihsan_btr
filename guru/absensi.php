<?php
include '../config.php';
cek_login([2]);

$guru_id            = $_SESSION['user_id'];
$tahun_ajaran_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif     = get_semester_aktif($conn);

// ========== HELPER: Tahun kalender dari tahun ajaran + bulan ==========
function get_tahun_kalender(string $tahun_ajaran, int $bulan): int {
    [$tahun1, $tahun2] = explode('/', $tahun_ajaran);
    return $bulan >= 7 ? (int)$tahun1 : (int)$tahun2;
}

// Tanggal representatif minggu ke-N dalam bulan
function get_tanggal_minggu(int $tahun, int $bulan, int $minggu_ke): string {
    $hari = 1 + ($minggu_ke - 1) * 7;
    return sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
}

// ========== AMBIL STATUS ABSEN (prepared) ==========
function get_status_absen($conn, int $siswa_id, int $kelas_mapel_id, string $tanggal): ?string {
    $stmt = $conn->prepare(
        "SELECT status FROM absensi WHERE siswa_id=? AND kelas_mapel_id=? AND tanggal=? LIMIT 1"
    );
    $stmt->bind_param("iis", $siswa_id, $kelas_mapel_id, $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['status'] ?? null;
}

// ========== VALIDASI INPUT ==========
$tahun_allowed    = ['2024/2025', '2025/2026', '2026/2027', '2027/2028'];
$status_allowed   = ['', 'hadir', 'sakit', 'izin', 'alpha'];

$tahun_ajaran   = isset($_GET['tahun_ajaran']) && in_array($_GET['tahun_ajaran'], $tahun_allowed)
                  ? $_GET['tahun_ajaran'] : $tahun_ajaran_aktif;
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;
$bulan          = isset($_GET['bulan']) ? max(1, min(12, (int)$_GET['bulan'])) : (int)date('m');

// ========== PROSES SIMPAN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_absensi'])) {
    csrf_verify();

    $km_post   = (int)$_POST['kelas_mapel_id'];
    $ta_post   = in_array($_POST['tahun_ajaran'], $tahun_allowed) ? $_POST['tahun_ajaran'] : $tahun_ajaran_aktif;
    $bln_post  = max(1, min(12, (int)$_POST['bulan']));
    $thn_kal   = get_tahun_kalender($ta_post, $bln_post);

    // ✅ Pastikan kelas_mapel ini milik guru yang login
    $stmt_auth = $conn->prepare(
        "SELECT id FROM kelas_mapel WHERE id=? AND guru_id=? LIMIT 1"
    );
    $stmt_auth->bind_param("ii", $km_post, $guru_id);
    $stmt_auth->execute();
    if ($stmt_auth->get_result()->num_rows === 0) {
        set_flash('warning', 'Akses tidak diizinkan.');
        header("Location: absensi?kelas_mapel_id=$km_post&tahun_ajaran=$ta_post&bulan=$bln_post");
        exit;
    }

    if (isset($_POST['status']) && is_array($_POST['status'])) {
        foreach ($_POST['status'] as $siswa_id => $minggu_status) {
            $siswa_id = (int)$siswa_id;
            if (!is_array($minggu_status)) continue;
            foreach ($minggu_status as $minggu => $status) {
                $minggu = (int)$minggu;
                if ($minggu < 1 || $minggu > 4) continue;
                if (!in_array($status, $status_allowed)) continue;
                if ($status === '') continue;

                $tanggal = get_tanggal_minggu($thn_kal, $bln_post, $minggu);

                // ✅ UPSERT dengan prepared statement
                $stmt_upsert = $conn->prepare(
                    "INSERT INTO absensi (siswa_id, kelas_mapel_id, tanggal, status)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE status = VALUES(status)"
                );
                $stmt_upsert->bind_param("iiss", $siswa_id, $km_post, $tanggal, $status);
                $stmt_upsert->execute();
            }
        }
    }

    set_flash('success', 'Absensi berhasil disimpan.');
    header("Location: absensi?kelas_mapel_id=$km_post&tahun_ajaran=$ta_post&bulan=$bln_post");
    exit;
}

// ========== DATA TAMPILAN ==========
$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_ajaran_aktif, $semester_aktif);

$siswa_list  = null;
$kelas_nama  = '';
$mapel_nama  = '';

if ($kelas_mapel_id > 0) {
    // ✅ Validasi kepemilikan kelas_mapel
    $stmt_km = $conn->prepare(
        "SELECT km.*, k.nama_kelas, mp.nama_mapel, k.id as kid
         FROM kelas_mapel km
         JOIN kelas k ON km.kelas_id = k.id
         JOIN mata_pelajaran mp ON km.mapel_id = mp.id
         WHERE km.id = ? AND km.guru_id = ? LIMIT 1"
    );
    $stmt_km->bind_param("ii", $kelas_mapel_id, $guru_id);
    $stmt_km->execute();
    $km = $stmt_km->get_result()->fetch_assoc();

    if ($km) {
        $kelas_nama = $km['nama_kelas'];
        $mapel_nama = $km['nama_mapel'];

        $stmt_siswa = $conn->prepare(
            "SELECT s.id, s.nis, u.nama_lengkap as nama
             FROM siswa s JOIN users u ON s.user_id = u.id
             WHERE s.kelas_id = ? ORDER BY u.nama_lengkap"
        );
        $stmt_siswa->bind_param("i", $km['kid']);
        $stmt_siswa->execute();
        $siswa_list = $stmt_siswa->get_result();
    }
}

$nama_bulan = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];
$daftar_ta = $tahun_allowed;

$title = 'Input Absensi';
include '../includes/header.php';


?>

<style>
.modern-table td, .modern-table th { padding:0.55rem 0.4rem; font-size:0.8rem; vertical-align:middle; }
.status-select {
    width:82px; padding:0.3rem 0.2rem; font-size:0.72rem;
    border-radius:20px; border:1px solid #cbd5e1; background:white; cursor:pointer; transition:all 0.2s;
}
.status-select:hover { border-color:var(--primary-500); background:#fefce8; }
.status-select option[value="hadir"]  { color:#166534; }
.status-select option[value="sakit"]  { color:#92400e; }
.status-select option[value="izin"]   { color:#1e40af; }
.status-select option[value="alpha"]  { color:#991b1b; }
.bulk-row    { background:#f1f5f9; }
.bulk-select { width:82px; padding:0.3rem; font-size:0.7rem; border-radius:20px; border:1px solid #cbd5e1; }
@media(max-width:768px){ .modern-table{ min-width:680px; } }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-calendar-alt"></i> Absensi Siswa</h2>
    <p class="page-subtitle">Input kehadiran siswa per minggu (4 pertemuan per bulan).</p>
</div>

<?= show_flash(); ?>

<!-- Form Filter -->
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label>Kelas &amp; Mata Pelajaran</label>
            <select name="kelas_mapel_id" class="form-select" required>
                <option value="">-- Pilih --</option>
                <?php if ($kelas_mapel_options):
                    while ($km_opt = $kelas_mapel_options->fetch_assoc()): ?>
                    <option value="<?= $km_opt['id'] ?>" <?= $kelas_mapel_id == $km_opt['id'] ? 'selected' : '' ?>>
                        <?= e($km_opt['nama_kelas']) ?> &mdash; <?= e($km_opt['nama_mapel']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tahun Ajaran</label>
            <select name="tahun_ajaran" class="form-select">
                <?php foreach ($daftar_ta as $ta): ?>
                    <option value="<?= $ta ?>" <?= $tahun_ajaran === $ta ? 'selected' : '' ?>><?= $ta ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Bulan</label>
            <select name="bulan" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $bulan === $m ? 'selected' : '' ?>><?= $nama_bulan[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> Tampilkan</button>
        </div>
    </form>
    <small style="color:var(--gray-500);">
        <i class="fas fa-info-circle"></i>
        Semester aktif: <?= $semester_aktif == '1' ? 'Semester 1 (Ganjil)' : 'Semester 2 (Genap)' ?>
        (<?= e($tahun_ajaran_aktif) ?>)
    </small>
</div>

<?php if ($kelas_mapel_id > 0 && $siswa_list && $siswa_list->num_rows > 0):
    $tahun_kalender = get_tahun_kalender($tahun_ajaran, $bulan);

    // Hitung tanggal representatif tiap minggu untuk ditampilkan di header
    $tanggal_minggu = [];
    for ($mg = 1; $mg <= 4; $mg++) {
        $tanggal_minggu[$mg] = get_tanggal_minggu($tahun_kalender, $bulan, $mg);
    }
?>
<div class="form-container">
    <div class="form-title">
        <i class="fas fa-users"></i>
        <?= e($kelas_nama) ?> &mdash; <?= e($mapel_nama) ?> &mdash;
        <?= $nama_bulan[$bulan] ?> <?= $tahun_kalender ?>
        <span style="font-weight:400; font-size:0.8rem;">(TA <?= e($tahun_ajaran) ?>)</span>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token"      value="<?= csrf_token() ?>">
        <input type="hidden" name="kelas_mapel_id"  value="<?= $kelas_mapel_id ?>">
        <input type="hidden" name="tahun_ajaran"    value="<?= e($tahun_ajaran) ?>">
        <input type="hidden" name="bulan"           value="<?= $bulan ?>">

        <div class="table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2" style="text-align:left;">Nama Siswa</th>
                        <?php for ($mg = 1; $mg <= 4; $mg++): ?>
                        <th>
                            Minggu <?= $mg ?><br>
                            <small style="font-weight:400;font-size:0.68rem;">
                                <?= date('d/m', strtotime($tanggal_minggu[$mg])) ?>
                            </small>
                        </th>
                        <?php endfor; ?>
                        <th title="Sakit">S</th>
                        <th title="Izin">I</th>
                        <th title="Alpha">A</th>
                    </tr>
                    <!-- Baris isi massal -->
                    <tr class="bulk-row">
                        <td colspan="2" style="text-align:right; font-size:0.75rem; color:var(--gray-500);">
                            Isi semua kolom:
                        </td>
                        <?php for ($mg = 1; $mg <= 4; $mg++): ?>
                        <td style="text-align:center;">
                            <select class="bulk-select" data-minggu="<?= $mg ?>">
                                <option value="">--</option>
                                <option value="hadir">Hadir</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="alpha">Alpha</option>
                            </select>
                        </td>
                        <?php endfor; ?>
                        <td colspan="3"></td>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1;
                    while ($s = $siswa_list->fetch_assoc()):
                        $total_s = $total_i = $total_a = 0;
                    ?>
                    <tr>
                        <td style="text-align:center"><?= $no++ ?></td>
                        <td><strong><?= e($s['nama']) ?></strong><br>
                            <small style="color:var(--gray-500)"><?= e($s['nis']) ?></small>
                        </td>
                        <?php for ($mg = 1; $mg <= 4; $mg++):
                            $tgl    = $tanggal_minggu[$mg];
                            $status = get_status_absen($conn, $s['id'], $kelas_mapel_id, $tgl);
                            if ($status === 'sakit') $total_s++;
                            elseif ($status === 'izin') $total_i++;
                            elseif ($status === 'alpha') $total_a++;
                        ?>
                        <td style="text-align:center;">
                            <select name="status[<?= $s['id'] ?>][<?= $mg ?>]" class="status-select">
                                <option value="">--</option>
                                <option value="hadir" <?= $status === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                                <option value="sakit" <?= $status === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                                <option value="izin"  <?= $status === 'izin'  ? 'selected' : '' ?>>Izin</option>
                                <option value="alpha" <?= $status === 'alpha' ? 'selected' : '' ?>>Alpha</option>
                            </select>
                        </td>
                        <?php endfor; ?>
                        <td style="text-align:center;"><strong><?= $total_s ?></strong></td>
                        <td style="text-align:center;"><strong><?= $total_i ?></strong></td>
                        <td style="text-align:center;"><strong style="color:#dc2626"><?= $total_a ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
            <button type="submit" name="simpan_absensi" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Absensi <?= $nama_bulan[$bulan] ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($kelas_mapel_id > 0): ?>
<div class="form-container">
    <p style="color:var(--gray-500);">
        <i class="fas fa-exclamation-triangle"></i>
        Tidak ada siswa di kelas ini atau Anda tidak memiliki akses.
    </p>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Isi massal per kolom minggu
    document.querySelectorAll('.bulk-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const val   = this.value;
            const minggu = this.dataset.minggu;
            if (!val) return;
            document.querySelectorAll(`select[name$="[${minggu}]"]`).forEach(function (s) {
                s.value = val;
            });
            this.value = ''; // Reset bulk setelah dipakai
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>