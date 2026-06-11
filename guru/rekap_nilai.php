<?php
include '../config.php';
cek_login([2]);
$title = 'Rekap & Olah Nilai';
include '../includes/header.php';

$guru_id = $_SESSION['user_id'];
$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;

// Validasi
$km_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT km.*, k.nama_kelas, mp.nama_mapel FROM kelas_mapel km 
    JOIN kelas k ON km.kelas_id = k.id 
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id 
    WHERE km.id = $kelas_mapel_id AND km.guru_id = $guru_id"));
if (!$km_check && $kelas_mapel_id) die("Akses ditolak");

// Proses simpan nilai (hanya STS, SAS, SAT)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_nilai'])) {
    $tahun_ajaran_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM tahun_ajaran WHERE tahun='$tahun_aktif' AND is_active=1"))['id'];
    foreach ($_POST['sts'] as $siswa_id => $sts) {
        $sts = $sts !== '' ? (float)$sts : 'NULL';
        $sas = $_POST['sas'][$siswa_id] !== '' ? (float)$_POST['sas'][$siswa_id] : 'NULL';
        $sat = $_POST['sat'][$siswa_id] !== '' ? (float)$_POST['sat'][$siswa_id] : 'NULL';
        
        $cek = mysqli_query($conn, "SELECT id FROM nilai_akhir WHERE siswa_id=$siswa_id AND kelas_mapel_id=$kelas_mapel_id AND tahun_ajaran_id=$tahun_ajaran_id AND semester='$semester_aktif'");
        if (mysqli_num_rows($cek)) {
            $query = "UPDATE nilai_akhir SET sts=$sts, sas=$sas, sat=$sat WHERE siswa_id=$siswa_id AND kelas_mapel_id=$kelas_mapel_id AND tahun_ajaran_id=$tahun_ajaran_id AND semester='$semester_aktif'";
        } else {
            $query = "INSERT INTO nilai_akhir (siswa_id, kelas_mapel_id, tahun_ajaran_id, semester, sts, sas, sat) 
                      VALUES ($siswa_id, $kelas_mapel_id, $tahun_ajaran_id, '$semester_aktif', $sts, $sas, $sat)";
        }
        mysqli_query($conn, $query);
    }
    echo "<script>alert('Nilai STS, SAS, SAT berhasil disimpan'); window.location.href='rekap_nilai.php?kelas_mapel_id=$kelas_mapel_id';</script>";
}

// Ambil data siswa dan nilai yang sudah ada
$siswa_nilai = [];
if ($kelas_mapel_id && $km_check) {
    $kelas_id = $km_check['kelas_id'];
    $tahun_ajaran_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM tahun_ajaran WHERE tahun='$tahun_aktif' AND is_active=1"))['id'];
    $query = "SELECT s.id, s.nis, u.nama_lengkap as nama, na.* 
              FROM siswa s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN nilai_akhir na ON na.siswa_id = s.id AND na.kelas_mapel_id = $kelas_mapel_id AND na.tahun_ajaran_id = $tahun_ajaran_id AND na.semester = '$semester_aktif'
              WHERE s.kelas_id = $kelas_id
              ORDER BY u.nama_lengkap";
    $siswa_nilai = mysqli_query($conn, $query);
}

$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);
$kelas_id_for_export = $km_check ? $km_check['kelas_id'] : 0;
?>
<div class="page-header"><h2><i class="fas fa-chart-line"></i> Rekap & Olah Nilai (STS, SAS, SAT)</h2></div>
<div class="form-container">
    <form method="GET" class="form-row" id="filterForm">
        <div class="form-group"><label>Pilih Kelas & Mapel</label>
            <select name="kelas_mapel_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                <option value="">-- Pilih --</option>
                <?php while($km = mysqli_fetch_assoc($kelas_mapel_options)): ?>
                <option value="<?= $km['id'] ?>" <?= $kelas_mapel_id==$km['id']?'selected':'' ?>><?= $km['nama_kelas'] ?> - <?= $km['nama_mapel'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php if($kelas_mapel_id && $km_check && mysqli_num_rows($siswa_nilai) > 0): ?>
        <div class="form-group">
            <a href="../admin/export_nilai_excel.php?kelas_id=<?= $kelas_id_for_export ?>&semester=<?= $semester_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="../admin/cetak_nilai.php?kelas_id=<?= $kelas_id_for_export ?>&semester=<?= $semester_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php if($kelas_mapel_id && $km_check && mysqli_num_rows($siswa_nilai) > 0): ?>
<form method="POST">
    <div class="form-container">
        <div class="form-title">Input Nilai - <?= $km_check['nama_kelas'] ?> (<?= $km_check['nama_mapel'] ?>) - Semester <?= $semester_aktif == 1 ? 'Ganjil' : 'Genap' ?> <?= $tahun_aktif ?></div>
        <div class="table-wrapper">
            <table class="modern-table" id="nilaiTable">
                <thead>
                    <tr>
                        <th>NIS</th><th>Nama</th>
                        <th>SUM1</th><th>SUM2</th><th>SUM3</th><th>SUM4</th>
                        <th>STS</th><th>SAS</th><th>SAT</th>
                        <th>Rata Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($siswa_nilai)): 
                        $rata = hitung_rata_akhir($row['sum1'], $row['sum2'], $row['sum3'], $row['sum4'], $row['sts'], $row['sas'], $row['sat']);
                    ?>
                    <tr>
                        <td><?= $row['nis'] ?></td>
                        <td><?= $row['nama'] ?></td>
                        <td><input type="text" readonly value="<?= $row['sum1'] ?? '-' ?>" style="width:70px; background:#f0f0f0; border:1px solid #ddd; text-align:center;"></td>
                        <td><input type="text" readonly value="<?= $row['sum2'] ?? '-' ?>" style="width:70px; background:#f0f0f0; border:1px solid #ddd; text-align:center;"></td>
                        <td><input type="text" readonly value="<?= $row['sum3'] ?? '-' ?>" style="width:70px; background:#f0f0f0; border:1px solid #ddd; text-align:center;"></td>
                        <td><input type="text" readonly value="<?= $row['sum4'] ?? '-' ?>" style="width:70px; background:#f0f0f0; border:1px solid #ddd; text-align:center;"></td>
                        <td><input type="number" name="sts[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sts'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sas[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sas'] ?>" class="form-input" style="width:80px"></td>
                        <td><input type="number" name="sat[<?= $row['id'] ?>]" step="0.01" min="0" max="100" value="<?= $row['sat'] ?>" class="form-input" style="width:80px"></td>
                        <td><strong><?= $rata ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="simpan_nilai" class="btn btn-primary"><i class="fas fa-save"></i> Simpan STS, SAS, SAT</button>
    </div>
</form>
<?php elseif($kelas_mapel_id && $km_check): ?>
    <div class="form-container"><p>Tidak ada siswa di kelas ini.</p></div>
<?php endif; ?>
<?php include '../includes/footer.php'; 