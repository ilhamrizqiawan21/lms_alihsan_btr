<?php
include '../config.php';
cek_login([2]);

$guru_id            = $_SESSION['user_id'];
$tahun_ajaran_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif     = get_semester_aktif($conn);

// ========== HELPER ==========
function get_tahun_kalender(string $tahun_ajaran, int $bulan): int {
    [$tahun1, $tahun2] = explode('/', $tahun_ajaran);
    return $bulan >= 7 ? (int)$tahun1 : (int)$tahun2;
}
function get_tanggal_minggu(int $tahun, int $bulan, int $minggu_ke): string {
    $hari = 1 + ($minggu_ke - 1) * 7;
    return sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
}
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
$tahun_allowed  = ['2024/2025', '2025/2026', '2026/2027', '2027/2028'];
$status_allowed = ['', 'hadir', 'sakit', 'izin', 'alpha'];

$tahun_ajaran   = isset($_GET['tahun_ajaran']) && in_array($_GET['tahun_ajaran'], $tahun_allowed)
                  ? $_GET['tahun_ajaran'] : $tahun_ajaran_aktif;
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;
$bulan          = isset($_GET['bulan']) ? max(1, min(12, (int)$_GET['bulan'])) : (int)date('m');

// ========== PROSES SIMPAN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_absensi'])) {
    csrf_verify();
    $km_post  = (int)$_POST['kelas_mapel_id'];
    $ta_post  = in_array($_POST['tahun_ajaran'], $tahun_allowed) ? $_POST['tahun_ajaran'] : $tahun_ajaran_aktif;
    $bln_post = max(1, min(12, (int)$_POST['bulan']));
    $thn_kal  = get_tahun_kalender($ta_post, $bln_post);

    $stmt_auth = $conn->prepare("SELECT id FROM kelas_mapel WHERE id=? AND guru_id=? LIMIT 1");
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
if (!$kelas_mapel_options || $kelas_mapel_options->num_rows == 0) {
    set_flash('warning', 'Anda belum memiliki penugasan kelas/mapel pada tahun/semester ini.');
}

$siswa_list = null;
$kelas_nama = '';
$mapel_nama = '';

if ($kelas_mapel_id > 0) {
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
/* ── ABSENSI PAGE ──────────────────────────────── */
.absen-filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 0.85rem;
    align-items: flex-end;
}
@media (max-width: 768px) {
    .absen-filter-grid { grid-template-columns: 1fr 1fr; }
    .absen-filter-grid .form-group:first-child { grid-column: 1 / -1; }
    .absen-filter-grid .btn-filter { grid-column: 1 / -1; }
}

/* ── TABLE ──────────────────────────────────────── */
.absen-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
}
.absen-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-sans);
    font-size: 0.82rem;
    min-width: 640px;
}

/* Header */
.absen-table thead tr.row-title th {
    background: linear-gradient(135deg, var(--primary-700) 0%, var(--primary-800) 100%);
    color: #fff;
    font-weight: 700;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.7rem 0.6rem;
    text-align: center;
    border: none;
}
.absen-table thead tr.row-title th.col-nama {
    text-align: left;
    padding-left: 1rem;
    min-width: 180px;
}
.absen-table thead tr.row-title th.col-no {
    width: 36px;
    min-width: 36px;
}
.absen-table thead tr.row-title th.col-sia {
    width: 44px;
    min-width: 44px;
    font-size: 0.8rem;
}

/* Tanggal sub-header */
.absen-table thead tr.row-date th {
    background: var(--primary-600);
    color: rgba(255,255,255,0.85);
    font-size: 0.68rem;
    font-weight: 500;
    padding: 0.3rem 0.4rem;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.15);
}

/* Bulk row */
.absen-table thead tr.row-bulk td {
    background: #f8fafc;
    border-bottom: 2px solid var(--gray-200);
    padding: 0.45rem 0.4rem;
    text-align: center;
}

/* Body rows */
.absen-table tbody tr {
    transition: background 0.15s;
}
.absen-table tbody tr:nth-child(odd) { background: #ffffff; }
.absen-table tbody tr:nth-child(even) { background: #f9fafb; }
.absen-table tbody tr:hover { background: #f0fdf4; }

.absen-table tbody td {
    padding: 0.6rem 0.5rem;
    text-align: center;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.absen-table tbody td.cell-nama {
    text-align: left;
    padding-left: 1rem;
}
.absen-table tbody td.cell-no {
    color: var(--gray-400);
    font-size: 0.75rem;
    font-weight: 600;
}
.cell-nama .siswa-name {
    font-weight: 600;
    font-size: 0.83rem;
    color: var(--gray-800);
    line-height: 1.3;
}
.cell-nama .siswa-nis {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 1px;
}

/* Status Select */
.status-select {
    width: 86px;
    padding: 0.28rem 0.25rem;
    font-size: 0.72rem;
    font-family: var(--font-sans);
    font-weight: 600;
    border-radius: 20px;
    border: 1.5px solid var(--gray-200);
    background: #fff;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    color: var(--gray-700);
    appearance: none;
    -webkit-appearance: none;
    text-align: center;
    outline: none;
}
.status-select:hover,
.status-select:focus {
    border-color: var(--primary-500);
    box-shadow: 0 0 0 3px rgba(34,197,94,0.12);
}

/* Color coding when selected */
.status-select.val-hadir { background: #dcfce7; border-color: #86efac; color: #166534; }
.status-select.val-sakit { background: #fef3c7; border-color: #fbbf24; color: #92400e; }
.status-select.val-izin  { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
.status-select.val-alpha { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

/* Bulk select */
.bulk-select {
    width: 86px;
    padding: 0.28rem 0.25rem;
    font-size: 0.68rem;
    font-family: var(--font-sans);
    font-weight: 600;
    border-radius: 20px;
    border: 1.5px dashed var(--gray-300);
    background: #fff;
    cursor: pointer;
    transition: border-color 0.2s;
    text-align: center;
    outline: none;
}
.bulk-select:hover { border-color: var(--primary-500); border-style: solid; }

/* Rekap kolom S/I/A */
.badge-sia {
    display: inline-block;
    min-width: 24px;
    padding: 0.18rem 0.4rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.4;
}
.badge-s { background: #fef3c7; color: #92400e; }
.badge-i { background: #dbeafe; color: #1e40af; }
.badge-a { background: #fee2e2; color: #991b1b; }
.badge-zero { background: #f1f5f9; color: #94a3b8; }

/* Info bar di atas tabel */
.absen-info-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.absen-info-bar .info-label {
    font-size: 0.82rem;
    color: var(--gray-600);
}
.absen-info-bar .info-kelas {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--primary-700);
}
.absen-legend {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.72rem;
    align-items: center;
}
.legend-dot {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    font-weight: 600;
}
.legend-hadir { background:#dcfce7; color:#166534; }
.legend-sakit { background:#fef3c7; color:#92400e; }
.legend-izin  { background:#dbeafe; color:#1e40af; }
.legend-alpha { background:#fee2e2; color:#991b1b; }

/* Semester badge */
.semester-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--primary-50);
    color: var(--primary-700);
    border: 1px solid var(--primary-100);
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
}

/* Save button area */
.absen-save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--gray-100);
}
.absen-save-bar .save-hint {
    font-size: 0.75rem;
    color: var(--gray-400);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-calendar-alt"></i> Absensi Siswa</h2>
    <p class="page-subtitle">Input kehadiran siswa — 4 pertemuan per bulan.</p>
</div>

<?= show_flash(); ?>

<!-- ── FORM FILTER ───────────────────────── -->
<div class="form-container" style="margin-bottom:1.25rem;">
    <form method="GET">
        <div class="absen-filter-grid">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Kelas &amp; Mata Pelajaran</label>
                <select name="kelas_mapel_id" class="form-select" required>
                    <option value="">— Pilih Kelas / Mapel —</option>
                    <?php if ($kelas_mapel_options):
                        while ($km_opt = $kelas_mapel_options->fetch_assoc()): ?>
                        <option value="<?= $km_opt['id'] ?>" <?= $kelas_mapel_id == $km_opt['id'] ? 'selected' : '' ?>>
                            <?= e($km_opt['nama_kelas']) ?> — <?= e($km_opt['nama_mapel']) ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Tahun Ajaran</label>
                <select name="tahun_ajaran" class="form-select">
                    <?php foreach ($daftar_ta as $ta): ?>
                        <option value="<?= $ta ?>" <?= $tahun_ajaran === $ta ? 'selected' : '' ?>><?= $ta ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $bulan === $m ? 'selected' : '' ?>><?= $nama_bulan[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group btn-filter" style="margin:0;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-eye"></i> Tampilkan
                </button>
            </div>
        </div>
        <div style="margin-top:0.75rem;">
            <span class="semester-badge">
                <i class="fas fa-info-circle"></i>
                Semester <?= $semester_aktif == '1' ? '1 — Ganjil' : '2 — Genap' ?>
                &nbsp;(<?= e($tahun_ajaran_aktif) ?>)
            </span>
        </div>
    </form>
</div>

<?php if ($kelas_mapel_id > 0 && $siswa_list && $siswa_list->num_rows > 0):
    $tahun_kalender = get_tahun_kalender($tahun_ajaran, $bulan);
    $tanggal_minggu = [];
    for ($mg = 1; $mg <= 4; $mg++) {
        $tanggal_minggu[$mg] = get_tanggal_minggu($tahun_kalender, $bulan, $mg);
    }
    $total_siswa = $siswa_list->num_rows;
?>
<div class="form-container">

    <!-- Info bar -->
    <div class="absen-info-bar">
        <div>
            <div class="info-kelas">
                <i class="fas fa-chalkboard-teacher" style="color:var(--primary-500);"></i>
                <?= e($kelas_nama) ?> &mdash; <?= e($mapel_nama) ?>
            </div>
            <div class="info-label" style="margin-top:2px;">
                <?= $nama_bulan[$bulan] ?> <?= $tahun_kalender ?>
                &nbsp;&middot;&nbsp; <?= $total_siswa ?> siswa
                &nbsp;&middot;&nbsp; TA <?= e($tahun_ajaran) ?>
            </div>
        </div>
        <div class="absen-legend">
            <span class="legend-dot legend-hadir"><i class="fas fa-check"></i> Hadir</span>
            <span class="legend-dot legend-sakit"><i class="fas fa-thermometer-half"></i> Sakit</span>
            <span class="legend-dot legend-izin"><i class="fas fa-envelope-open-text"></i> Izin</span>
            <span class="legend-dot legend-alpha"><i class="fas fa-times"></i> Alpha</span>
        </div>
    </div>

    <form method="POST" id="formAbsensi">
        <input type="hidden" name="csrf_token"     value="<?= csrf_token() ?>">
        <input type="hidden" name="kelas_mapel_id" value="<?= $kelas_mapel_id ?>">
        <input type="hidden" name="tahun_ajaran"   value="<?= e($tahun_ajaran) ?>">
        <input type="hidden" name="bulan"          value="<?= $bulan ?>">

        <div class="absen-table-wrap">
            <table class="absen-table">
                <thead>
                    <!-- Baris judul kolom -->
                    <tr class="row-title">
                        <th class="col-no">#</th>
                        <th class="col-nama">Nama Siswa</th>
                        <?php for ($mg = 1; $mg <= 4; $mg++): ?>
                        <th>Minggu <?= $mg ?></th>
                        <?php endfor; ?>
                        <th class="col-sia" title="Sakit">S</th>
                        <th class="col-sia" title="Izin">I</th>
                        <th class="col-sia" title="Alpha">A</th>
                    </tr>
                    <!-- Baris tanggal -->
                    <tr class="row-date">
                        <th colspan="2" style="text-align:left; padding-left:1rem; color:rgba(255,255,255,0.6); font-size:0.65rem;">
                            Isi massal →
                        </th>
                        <?php for ($mg = 1; $mg <= 4; $mg++): ?>
                        <th><?= date('d/m', strtotime($tanggal_minggu[$mg])) ?></th>
                        <?php endfor; ?>
                        <th colspan="3"></th>
                    </tr>
                    <!-- Baris bulk action -->
                    <tr class="row-bulk">
                        <td colspan="2" style="text-align:right; font-size:0.7rem; color:var(--gray-400); padding-right:0.5rem;">
                            Isi semua:
                        </td>
                        <?php for ($mg = 1; $mg <= 4; $mg++): ?>
                        <td>
                            <select class="bulk-select" data-minggu="<?= $mg ?>" title="Isi semua minggu <?= $mg ?>">
                                <option value="">—</option>
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
                        $statuses = [];
                        for ($mg = 1; $mg <= 4; $mg++) {
                            $st = get_status_absen($conn, $s['id'], $kelas_mapel_id, $tanggal_minggu[$mg]);
                            $statuses[$mg] = $st;
                            if ($st === 'sakit') $total_s++;
                            elseif ($st === 'izin') $total_i++;
                            elseif ($st === 'alpha') $total_a++;
                        }
                    ?>
                    <tr>
                        <td class="cell-no"><?= $no++ ?></td>
                        <td class="cell-nama">
                            <div class="siswa-name"><?= e($s['nama']) ?></div>
                            <div class="siswa-nis"><?= e($s['nis']) ?></div>
                        </td>
                        <?php for ($mg = 1; $mg <= 4; $mg++):
                            $st = $statuses[$mg];
                            $valClass = $st ? 'val-' . $st : '';
                        ?>
                        <td>
                            <select name="status[<?= $s['id'] ?>][<?= $mg ?>]"
                                    class="status-select <?= $valClass ?>"
                                    data-siswa="<?= $s['id'] ?>"
                                    data-minggu="<?= $mg ?>">
                                <option value="">—</option>
                                <option value="hadir" <?= $st === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                                <option value="sakit" <?= $st === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                                <option value="izin"  <?= $st === 'izin'  ? 'selected' : '' ?>>Izin</option>
                                <option value="alpha" <?= $st === 'alpha' ? 'selected' : '' ?>>Alpha</option>
                            </select>
                        </td>
                        <?php endfor; ?>
                        <td>
                            <span class="badge-sia <?= $total_s > 0 ? 'badge-s' : 'badge-zero' ?>">
                                <?= $total_s ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-sia <?= $total_i > 0 ? 'badge-i' : 'badge-zero' ?>">
                                <?= $total_i ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-sia <?= $total_a > 0 ? 'badge-a' : 'badge-zero' ?>">
                                <?= $total_a ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Save bar -->
        <div class="absen-save-bar">
            <div class="save-hint">
                <i class="fas fa-lightbulb" style="color:var(--gold-500);"></i>
                Gunakan dropdown "Isi semua" di baris abu-abu untuk mengisi satu kolom sekaligus.
            </div>
            <button type="submit" name="simpan_absensi" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Simpan Absensi <?= $nama_bulan[$bulan] ?> <?= $tahun_kalender ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($kelas_mapel_id > 0): ?>
<div class="form-container" style="text-align:center; padding:2.5rem 1rem; color:var(--gray-500);">
    <i class="fas fa-users-slash" style="font-size:2rem; opacity:0.4; margin-bottom:0.75rem; display:block;"></i>
    Tidak ada siswa di kelas ini atau Anda tidak memiliki akses.
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Warna select saat nilai berubah ──────────────
    function applySelectColor(sel) {
        sel.className = sel.className.replace(/\bval-\w+/g, '').trim();
        if (sel.value) sel.classList.add('val-' + sel.value);
    }

    document.querySelectorAll('.status-select').forEach(function (sel) {
        applySelectColor(sel);
        sel.addEventListener('change', function () {
            applySelectColor(this);
            updateSIA(this.dataset.siswa);
        });
    });

    // ── Bulk action per kolom minggu ─────────────────
    document.querySelectorAll('.bulk-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const val    = this.value;
            const minggu = this.dataset.minggu;
            if (!val) return;
            document.querySelectorAll(`.status-select[data-minggu="${minggu}"]`).forEach(function (s) {
                s.value = val;
                applySelectColor(s);
                updateSIA(s.dataset.siswa);
            });
            this.value = '';
        });
    });

    // ── Update rekap S/I/A realtime ──────────────────
    function updateSIA(siswaId) {
        const row = document.querySelector(`.status-select[data-siswa="${siswaId}"]`)
                             ?.closest('tr');
        if (!row) return;

        let s = 0, i = 0, a = 0;
        row.querySelectorAll('.status-select').forEach(function (sel) {
            if (sel.value === 'sakit') s++;
            else if (sel.value === 'izin') i++;
            else if (sel.value === 'alpha') a++;
        });

        const badges = row.querySelectorAll('.badge-sia');
        if (badges.length < 3) return;

        setBadge(badges[0], s, 'badge-s');
        setBadge(badges[1], i, 'badge-i');
        setBadge(badges[2], a, 'badge-a');
    }

    function setBadge(el, count, cls) {
        el.textContent = count;
        el.className = 'badge-sia ' + (count > 0 ? cls : 'badge-zero');
    }
});
</script>

<?php include '../includes/footer.php'; ?>