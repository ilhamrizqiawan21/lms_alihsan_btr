<?php
include '../config.php';
cek_login([2]);

$guru_id        = $_SESSION['user_id'];
$tahun_aktif    = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

// Ambil daftar mapel yang diampu guru
$mapel_list = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);
$distinct_mapel = [];
if ($mapel_list) {
    $mapel_list->data_seek(0);
    while ($row = $mapel_list->fetch_assoc()) {
        if (!isset($distinct_mapel[$row['mapel_id']])) {
            $distinct_mapel[$row['mapel_id']] = $row['nama_mapel'];
        }
    }
    $mapel_list->data_seek(0);
}

// ========== PROSES TAMBAH TUGAS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_tugas'])) {
    csrf_verify();
    $judul       = trim($_POST['judul'] ?? '');
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $batas_waktu = !empty($_POST['batas_waktu']) ? $_POST['batas_waktu'] : null;
    $km_ids      = isset($_POST['kelas_mapel_ids']) && is_array($_POST['kelas_mapel_ids'])
                   ? array_map('intval', $_POST['kelas_mapel_ids']) : [];

    if (empty($km_ids)) {
        set_flash('warning', 'Pilih minimal satu kelas!');
        header('Location: tugas');
        exit;
    }

    // Validasi
    $ph = implode(',', array_fill(0, count($km_ids), '?'));
    $types = str_repeat('i', count($km_ids)) . 'i';
    $params = array_merge($km_ids, [$guru_id]);
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM kelas_mapel WHERE id IN ($ph) AND guru_id=?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc()['c'];
    if ($valid != count($km_ids)) {
        set_flash('warning', 'Akses tidak diizinkan untuk beberapa kelas.');
        header('Location: tugas');
        exit;
    }

    $sukses = 0;
    foreach ($km_ids as $km_id) {
        $stmt = $conn->prepare("
            INSERT INTO tugas (kelas_mapel_id, judul, deskripsi, kategori_nilai, batas_waktu)
            VALUES (?, ?, ?, 'NH', ?)
        ");
        $stmt->bind_param("isss", $km_id, $judul, $deskripsi, $batas_waktu);
        if ($stmt->execute()) $sukses++;

        // Ambil semua siswa di kelas ini
        $stmt_siswa = $conn->prepare("SELECT user_id FROM siswa WHERE kelas_id = (SELECT kelas_id FROM kelas_mapel WHERE id = ?)");
        $stmt_siswa->bind_param("i", $km_id);
        $stmt_siswa->execute();
        $res_siswa = $stmt_siswa->get_result();
        while ($siswa = $res_siswa->fetch_assoc()) {
        tambah_notifikasi($conn, $siswa['user_id'], 'tugas_baru', 'Tugas Baru', "Tugas '$judul' telah diberikan.", "../siswa/tugas_saya.php");
        }

    }
    set_flash('success', "Tugas berhasil dibuat untuk $sukses kelas.");
    header('Location: tugas');
    exit;
}

// ========== PROSES HAPUS TUGAS ==========
if (isset($_GET['hapus_tugas'])) {
    $id = (int)$_GET['hapus_tugas'];
    $stmt = $conn->prepare("
        SELECT t.id FROM tugas t
        JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
        WHERE t.id = ? AND km.guru_id = ?
    ");
    $stmt->bind_param("ii", $id, $guru_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $del = $conn->prepare("DELETE FROM pengumpulan_tugas WHERE tugas_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del2 = $conn->prepare("DELETE FROM tugas WHERE id = ?");
        $del2->bind_param("i", $id);
        $del2->execute();
        set_flash('success', 'Tugas dihapus.');
    } else {
        set_flash('warning', 'Tugas tidak ditemukan atau akses ditolak.');
    }
    header('Location: tugas');
    exit;
}

// ========== PROSES SIMPAN NILAI ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nilai'])) {
    csrf_verify();
    $tugas_id = (int)$_POST['tugas_id'];

    // Validasi
    $stmt = $conn->prepare("
        SELECT t.id FROM tugas t
        JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
        WHERE t.id = ? AND km.guru_id = ?
    ");
    $stmt->bind_param("ii", $tugas_id, $guru_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        set_flash('warning', 'Akses tidak diizinkan.');
        header('Location: tugas');
        exit;
    }

    if (isset($_POST['nilai']) && is_array($_POST['nilai'])) {
        $updated = 0;
        foreach ($_POST['nilai'] as $siswa_id => $nilai) {
            $siswa_id = (int)$siswa_id;
            if ($nilai === '') continue;
            $nilai = max(0, min(100, (float)$nilai));
            $catatan = trim($_POST['catatan'][$siswa_id] ?? '');

            // Cek apakah sudah ada pengumpulan
            $cek = $conn->prepare("SELECT id FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?");
            $cek->bind_param("ii", $tugas_id, $siswa_id);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                $upd = $conn->prepare("UPDATE pengumpulan_tugas SET nilai = ?, catatan = ? WHERE tugas_id = ? AND siswa_id = ?");
                $upd->bind_param("dsii", $nilai, $catatan, $tugas_id, $siswa_id);
                $upd->execute();
                $updated++;
            } else {
                // Insert jika belum ada
                $ins = $conn->prepare("INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, status, nilai, catatan, tanggal_kumpul) VALUES (?, ?, 'sudah', ?, ?, NOW())");
                $ins->bind_param("iids", $tugas_id, $siswa_id, $nilai, $catatan);
                $ins->execute();
                $updated++;
            }

            // Dapatkan user_id siswa
            $stmt_user = $conn->prepare("SELECT user_id FROM siswa WHERE id = ?");
            $stmt_user->bind_param("i", $siswa_id);
            $stmt_user->execute();
            $user_id_siswa = $stmt_user->get_result()->fetch_assoc()['user_id'];
            tambah_notifikasi($conn, $user_id_siswa, 'nilai_baru', 'Nilai Tugas', "Anda mendapat nilai $nilai untuk tugas '$judul_tugas'.", "../siswa/tugas_saya.php");

            // Di dalam foreach ($_POST['nilai'] as $siswa_id => $nilai) setelah update nilai
            // Dapatkan user_id siswa
            $stmt_user = $conn->prepare("SELECT user_id FROM siswa WHERE id = ?");
            $stmt_user->bind_param("i", $siswa_id);
            $stmt_user->execute();
            $siswa_data = $stmt_user->get_result()->fetch_assoc();
            if ($siswa_data) {
                // Notifikasi nilai baru
                tambah_notifikasi($conn, $siswa_data['user_id'], 'nilai_baru', 'Nilai Tugas', "Anda mendapat nilai $nilai untuk tugas '$judul_tugas'.", "../siswa/tugas_saya.php");
                
                // Jika ada komentar (catatan) dan tidak kosong
                $catatan = trim($_POST['catatan'][$siswa_id] ?? '');
                if (!empty($catatan)) {
                    tambah_notifikasi($conn, $siswa_data['user_id'], 'komentar_tugas', 'Komentar dari guru', "Guru memberi komentar pada tugas '$judul_tugas': " . substr($catatan, 0, 100), "../siswa/tugas_saya.php");
                }
            }

                    }
                    set_flash('success', "$updated nilai berhasil disimpan.");
                } else {
                    set_flash('warning', 'Tidak ada data nilai yang dikirim.');
                }
                header('Location: tugas');
                exit;
            }

$title = 'Kelola Tugas (Nilai Harian)';
include '../includes/header.php';

// Daftar semua tugas guru
$tugas_list = null;
$stmt = $conn->prepare("
    SELECT t.*, km.id as km_id, k.nama_kelas, mp.nama_mapel
    FROM tugas t
    JOIN kelas_mapel km ON t.kelas_mapel_id = km.id
    JOIN kelas k ON km.kelas_id = k.id
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id
    WHERE km.guru_id = ?
    ORDER BY t.created_at DESC
");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$tugas_list = $stmt->get_result();
?>

<style>
.kelas-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}
.kelas-checkbox-group label {
    background: #f3f4f6;
    padding: 6px 14px;
    border-radius: 20px;
    cursor: pointer;
}
.tugas-card {
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    padding: 1rem;
    margin-bottom: 1rem;
    background: white;
}
.tugas-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
}
.nilai-input {
    width: 80px;
}
.btn-sm {
    padding: 4px 10px;
    font-size: 0.75rem;
}
.table-wrapper {
    overflow-x: auto;
}
@media (max-width: 768px) {
    .nilai-input { width: 70px; }
}
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-tasks"></i> Kelola Tugas (Nilai Harian)</h2>
    <p class="page-subtitle">Buat tugas untuk satu atau banyak kelas. Nilai tugas akan menjadi komponen Nilai Harian siswa.</p>
</div>

<?= show_flash(); ?>

<!-- Form Buat Tugas -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-plus-circle"></i> Buat Tugas Baru</div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Pilih Mata Pelajaran</label>
            <select id="mapel_select" class="form-select" required>
                <option value="">-- Pilih Mata Pelajaran --</option>
                <?php foreach ($distinct_mapel as $mapel_id => $nama_mapel): ?>
                    <option value="<?= $mapel_id ?>"><?= e($nama_mapel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="kelas_container" style="display:none;">
            <label>Pilih Kelas Tujuan (centang kelas yang dituju)</label>
            <div id="kelas_checkbox_group" class="kelas-checkbox-group">
                <small>Silakan pilih mata pelajaran terlebih dahulu</small>
            </div>
        </div>
        <div class="form-group">
            <label>Judul Tugas</label>
            <input type="text" name="judul" class="form-input" required>
        </div>
        <div class="form-group">
            <label>Deskripsi / Soal</label>
            <textarea name="deskripsi" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>Batas Waktu (opsional)</label>
            <input type="datetime-local" name="batas_waktu" class="form-input">
        </div>
        <button type="submit" name="simpan_tugas" class="btn btn-primary">
            <i class="fas fa-save"></i> Buat Tugas (Nilai Harian)
        </button>
    </form>
</div>

<!-- Daftar Tugas -->
<div class="form-container">
    <div class="form-title"><i class="fas fa-list"></i> Daftar Tugas & Nilai Harian</div>
    <?php if ($tugas_list->num_rows > 0):
        while ($t = $tugas_list->fetch_assoc()): ?>
        <div class="tugas-card">
            <div class="tugas-card-header">
                <div>
                    <strong><?= e($t['judul']) ?></strong>
                    <div style="font-size:0.8rem; color:var(--gray-500);">
                        <?= e($t['nama_mapel']) ?> &mdash; <?= e($t['nama_kelas']) ?>
                        <?php if ($t['batas_waktu']): ?>
                            &nbsp;|&nbsp; <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($t['batas_waktu'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($t['deskripsi']): ?>
                        <div style="font-size:0.82rem; margin-top:6px;"><?= nl2br(e($t['deskripsi'])) ?></div>
                    <?php endif; ?>
                </div>
                <a href="?hapus_tugas=<?= $t['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Hapus tugas ini beserta semua pengumpulan siswa?')">
                    <i class="fas fa-trash"></i> Hapus
                </a>
            </div>
            <div style="margin-top:0.75rem;">
                <button class="btn btn-sm btn-primary btn-load-siswa"
                        data-tugas-id="<?= $t['id'] ?>" data-km-id="<?= $t['km_id'] ?>">
                    <i class="fas fa-users"></i> Tampilkan Siswa & Nilai
                </button>
            </div>
            <div id="siswa-container-<?= $t['id'] ?>" style="margin-top:1rem;"></div>
        </div>
    <?php endwhile;
    else: ?>
        <p style="color:var(--gray-500);">Belum ada tugas. Buat tugas baru di atas.</p>
    <?php endif; ?>
</div>

<script>
document.getElementById('mapel_select').addEventListener('change', function() {
    const mapelId = this.value;
    const container = document.getElementById('kelas_container');
    const cbGroup = document.getElementById('kelas_checkbox_group');
    if (!mapelId) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'block';
    cbGroup.innerHTML = '<small><i class="fas fa-spinner fa-spin"></i> Memuat kelas...</small>';
    fetch(`../ajax/get_kelas_by_mapel.php?mapel_id=${encodeURIComponent(mapelId)}`)
        .then(res => res.json())
        .then(data => {
            cbGroup.innerHTML = '';
            if (!Array.isArray(data) || data.length === 0) {
                cbGroup.innerHTML = '<small>Tidak ada kelas tersedia.</small>';
                return;
            }
            data.forEach(k => {
                const label = document.createElement('label');
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'kelas_mapel_ids[]';
                cb.value = k.kelas_mapel_id;
                label.appendChild(cb);
                label.appendChild(document.createTextNode(' ' + k.nama_kelas));
                cbGroup.appendChild(label);
            });
        })
        .catch(() => {
            cbGroup.innerHTML = '<small style="color:#dc2626">Gagal memuat kelas.</small>';
        });
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-load-siswa');
    if (!btn) return;
    const tugasId = btn.dataset.tugasId;
    const kmId = btn.dataset.kmId;
    const container = document.getElementById(`siswa-container-${tugasId}`);
    container.innerHTML = '<div style="padding:1rem;"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';

    fetch(`../ajax/get_siswa_by_tugas.php?tugas_id=${tugasId}&kelas_mapel_id=${kmId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = `<p style="color:#dc2626">Gagal: ${escapeHtml(data.message)}</p>`;
                return;
            }
            if (!data.siswa || data.siswa.length === 0) {
                container.innerHTML = '<p>Tidak ada siswa.</p>';
                return;
            }
            let html = `<form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="tugas_id" value="${tugasId}">
                <div class="table-wrapper">
                <table class="modern-table">
                <thead><tr>
                    <th>No</th><th>NIS</th><th>Nama</th><th>Status</th>
                    <th>File/Jawaban</th><th>Nilai (0-100)</th><th>Komentar</th>
                </tr></thead><tbody>`;
            data.siswa.forEach((s, i) => {
                const badge = s.status === 'sudah' ? '<span class="badge-hadir">Sudah kumpul</span>' : '<span class="badge-alpha">Belum</span>';
                let fileCol = '-';
                
                if (s.files && s.files.length > 0) {
                    fileCol = '<div style="display:flex; flex-direction:column; gap:4px;">';
                    s.files.forEach(f => {
                        fileCol += `<a href="../uploads/tugas_siswa/${escapeHtml(f.file_path)}" target="_blank" class="btn btn-sm btn-primary" title="${escapeHtml(f.file_name)}">
                                        <i class="fas fa-file"></i> ${escapeHtml(f.file_name)}
                                    </a>`;
                    });
                    fileCol += '</div>';
                } else if (s.teks_jawaban) {
                    fileCol = `<button type="button" class="btn btn-sm btn-outline" onclick="alert(this.dataset.jawaban)" data-jawaban="${escapeHtml(s.teks_jawaban)}">Lihat Jawaban</button>`;
                }

                html += `<tr>
                    <td style="text-align:center">${i+1}</td>
                    <td>${escapeHtml(s.nis)}</td>
                    <td><strong>${escapeHtml(s.nama)}</strong></td>
                    <td>${badge}</td>
                    <td>${fileCol}</td>
                    <td><input type="number" name="nilai[${s.siswa_id}]" class="form-input nilai-input" value="${escapeHtml(s.nilai)}" step="0.01" min="0" max="100"></td>
                    <td><textarea name="catatan[${s.siswa_id}]" class="form-textarea" style="width:100%; height:55px;">${escapeHtml(s.catatan)}</textarea></td>
                </tr>`;
            });
            html += `</tbody></table></div>
                <div style="margin-top:1rem; display:flex; justify-content:flex-end;">
                    <button type="submit" name="update_nilai" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Nilai</button>
                </div></form>`;
            container.innerHTML = html;
        })
        .catch(() => {
            container.innerHTML = '<p style="color:#dc2626">Terjadi kesalahan.</p>';
        });
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}
</script>

<?php include '../includes/footer.php'; ?>