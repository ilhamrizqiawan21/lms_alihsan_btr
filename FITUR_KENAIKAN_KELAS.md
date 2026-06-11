# Fitur: Otomatis Kenaikan Kelas & Kelulusan Siswa

## Deskripsi

Ketika **admin mengubah tahun ajaran** di halaman **Pengaturan Sistem**, sistem akan **otomatis**:

1. **Siswa kelas IX** → Di-archive dengan status `lulus` (tidak ditampilkan di list siswa)
2. **Siswa kelas VII** → Naik ke kelas VIII (VII-A → VIII-A, VII-B → VIII-B, dst)
3. **Siswa kelas VIII** → Naik ke kelas IX (VIII-A → IX-A, VIII-B → IX-B, dst)
4. **Data akademik** untuk tahun/semester baru dihapus (nilai, tugas, absensi, sikap, dll)

Admin kemudian dapat **mengatur ulang penempatan siswa** di kelas-kelas spesifik melalui halaman **Admin → Kelola Siswa**.

---

## Setup & Instalasi

### 1. Jalankan Migration SQL

Sebelum menggunakan fitur ini, perlu menambahkan kolom `status` ke tabel `siswa`.

**Opsi A: Melalui phpMyAdmin**

1. Buka **phpMyAdmin** di browser (http://localhost/phpmyadmin)
2. Pilih database `lms_alihsan_btr`
3. Klik tab **SQL**
4. Copy-paste isi file `migrations/001_add_siswa_status.sql`:
   ```sql
   ALTER TABLE `siswa` 
   ADD COLUMN `status` ENUM('aktif', 'lulus', 'keluar') 
   COLLATE utf8mb4_general_ci 
   DEFAULT 'aktif' 
   AFTER `angkatan`;
   
   UPDATE `siswa` SET `status` = 'aktif' WHERE `status` IS NULL OR `status` = '';
   
   ALTER TABLE `siswa` ADD INDEX `idx_status` (`status`);
   ```
5. Klik **Execute**

**Opsi B: Melalui MySQL Client (Terminal)**

```bash
mysql -u root -p lms_alihsan_btr < migrations/001_add_siswa_status.sql
```

### 2. Verifikasi Kolom

Pastikan kolom `status` sudah ada:
```sql
DESCRIBE siswa;
```

Output harus menampilkan kolom:
```
| status | enum('aktif','lulus','keluar') | YES  | MUL | aktif   |
```

---

## Cara Menggunakan

### Flow Rollover Tahun Ajaran

**Sebelum**:
- Tahun Ajaran Aktif: `2025/2026`
- Siswa Budi di kelas **VII-A** (status: aktif)
- Siswa Citra di kelas **VIII-B** (status: aktif)  
- Siswa Doni di kelas **IX-C** (status: aktif)

**Admin melakukan:**

1. Buka **Admin → Pengaturan Sistem**
2. Ubah **Tahun Ajaran Aktif** dari `2025/2026` → `2026/2027`
3. (Opsional) Ubah **Semester Aktif** jika perlu
4. Klik **Simpan Pengaturan**

**Hasil Otomatis**:
- ✅ Siswa Budi: Naik ke kelas **VIII-A** (kelas_id berubah)
- ✅ Siswa Citra: Naik ke kelas **IX-B** (kelas_id berubah)
- ✅ Siswa Doni: Status menjadi **lulus** (tidak tampil di list siswa)
- ✅ Data akademik dikosongkan (nilai, tugas, absensi, sikap, materi, dll)

**Setelah rollover**, admin dapat:

1. Buka **Admin → Kelola Siswa**
2. Hanya menampilkan siswa dengan status `aktif` (siswa lulus tidak tampil)
3. Update penempatan siswa ke kelas-kelas baru sesuai kebutuhan

---

## Validasi & Catatan Penting

### ✅ Apa yang Otomatis Berubah

- `siswa.kelas_id` → Diupdate ke kelas level lebih tinggi (VII→VIII, VIII→IX)
- `siswa.status` → Diubah ke `lulus` untuk siswa kelas IX
- Data akademik semester aktif baru → Dihapus

### ⚠️ Apa yang TIDAK Berubah

- Data `users` (akun login siswa tetap ada)
- Data `kelas` (master kelas tidak berubah)
- Data `kelas_mapel` (penugasan guru, admin harus update manual)
- Siswa dengan status `lulus` tetap tersimpan di database (archive)

### ⚠️ Fitur Limitation

Saat ini sistem mengasumsikan kelas dengan pattern `{TINGKAT}-{HURUF}`:
- VII-A, VII-B, VII-C, VII-D
- VIII-A, VIII-B, VIII-C, VIII-D, VIII-E
- IX-A, IX-B, IX-C, IX-D, IX-E

Jika ada kelas dengan pattern berbeda, proses promosi mungkin gagal.

---

## Troubleshooting

### Siswa tidak naik kelas setelah rollover?

**Penyebab:**
- Kolom `status` belum ditambahkan
- Nama kelas target tidak ada (misal tidak ada VIII-A padahal ada VII-A)
- Status siswa bukan `aktif` (sudah `lulus` atau `keluar`)

**Solusi:**
1. Verifikasi kolom `status` ada di tabel `siswa`
2. Cek nama kelas di database: `SELECT * FROM kelas ORDER BY nama_kelas;`
3. Cek status siswa: `SELECT id, nis, status FROM siswa;`
4. Manual update jika perlu: `UPDATE siswa SET kelas_id = 6 WHERE id = 10;`

### Data siswa lulus masih tampil di list?

**Penyebab:**
- Versi kelas_siswa.php lama yang tidak filter `status='aktif'`

**Solusi:**
- Update file `admin/kelas_siswa.php` ke versi terbaru yang include WHERE `s.status = 'aktif'`

---

## Database Schema

```sql
-- Kolom yang ditambahkan
ALTER TABLE siswa ADD COLUMN status ENUM('aktif', 'lulus', 'keluar') DEFAULT 'aktif';

-- Nilai yang mungkin
-- 'aktif'  : Siswa aktif (tampil di list)
-- 'lulus'  : Siswa tamat/lulus (di-archive, tidak tampil)
-- 'keluar' : Siswa keluar/pindah sekolah (reserved untuk update nanti)
```

---

## Developer Notes

### Fungsi Terkait

**File:** `includes/fungsi.php`

```php
// Fungsi utama untuk promosi siswa
function promote_students_on_year_change($conn)
```

Dipanggil dari `admin/pengaturan.php` saat:
```php
if ($old_tahun !== $tahun) {
    promote_students_on_year_change($conn);
    // ... clear academic data
}
```

---

## Update History

| Versi | Tanggal | Perubahan |
|-------|---------|----------|
| 1.0   | 30-May-2026 | Implementasi fitur kenaikan kelas otomatis |

---

## Kontak & Support

Jika ada pertanyaan atau issue, hubungi developer: **Ilham Rizqiawan, S.Pd.**
