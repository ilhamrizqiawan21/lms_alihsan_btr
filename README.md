# 📚 LMS MTs AL-IHSAN BATUJAJAR

Learning Management System (LMS) khusus dikembangkan untuk **MTs Al-Ihsan Batujajar**. Platform ini dirancang untuk memfasilitasi kegiatan belajar mengajar secara digital, mulai dari pengelolaan data akademik, absensi, tugas, hingga pelaporan nilai secara otomatis.

---

## 🚀 Fitur Utama

### 👥 Multi-Role Access Control (RBAC)
Sistem memiliki 4 level akses dengan dashboard dan fitur yang dipersonalisasi:
*   **Administrator**: Manajemen user, mata pelajaran, kelas, tahun ajaran, pengumuman, dan pengaturan sistem.
*   **Guru**: Mengelola absensi siswa, materi pembelajaran, tugas/kuis, penilaian (Sumatif, STS, SAS, SAT), serta chat dengan siswa.
*   **Siswa**: Mengakses materi, mengumpulkan tugas, melihat nilai secara real-time, dan berkomunikasi dengan guru.
*   **Kepala Sekolah**: Memantau laporan akademik, rekap absensi, dan statistik perkembangan sekolah secara keseluruhan.

### 📊 Manajemen Akademik & Penilaian
*   **Olah Nilai Otomatis**: Perhitungan nilai rata-rata akhir secara otomatis berdasarkan bobot Sumatif dan Asesmen Semester.
*   **Absensi Digital**: Pencatatan kehadiran siswa per mata pelajaran dengan status Hadir, Sakit, Izin, dan Alpha.
*   **Sikap Sosial**: Penilaian karakter siswa (Empati, Kerjasama, Toleransi, dll) dengan skala 1-5.
*   **Materi & Tugas**: Upload materi (PDF) dan pengumpulan tugas secara online dengan fitur deadline.

### 📈 Pelaporan & Export
*   **Cetak Laporan**: Fitur cetak (print-friendly) untuk absensi, nilai, dan sikap.
*   **Export Excel**: Integrasi dengan `PHPSpreadsheet` untuk mendownload data dalam format `.xlsx`.

### 🛡️ Keamanan & Integritas Data
*   **Prepared Statements**: Melindungi dari SQL Injection.
*   **Password Hashing**: Menggunakan `bcrypt` (dengan fitur auto-upgrade dari MD5).
*   **CSRF Protection**: Token keamanan pada setiap form input.
*   **Audit Log**: Pencatatan aktivitas login pengguna (IP & User-Agent).

---

## 🛠️ Tech Stack

*   **Backend**: PHP 7.4+ (Native Procedural)
*   **Database**: MySQL (MariaDB)
*   **Frontend**: HTML5, CSS3 (Custom Variables), JavaScript (Vanilla)
*   **Dependencies**: Composer, PHPOffice/PHPSpreadsheet
*   **Library Lain**: Chart.js (Grafik Dashboard), Font Awesome 6 (Ikon)

---

## 📋 Persyaratan Sistem

*   PHP >= 7.4
*   MySQL/MariaDB
*   Composer (untuk dependensi)
*   Web Server (Apache/Nginx/XAMPP)

---

## ⚙️ Instalasi

1.  **Clone Repository**
    ```bash
    git clone https://github.com/username/lms_alihsan_btr.git
    cd lms_alihsan_btr
    ```

2.  **Install Dependensi**
    ```bash
    composer install
    ```

3.  **Konfigurasi Database**
    *   Buat database baru di MySQL (misal: `lms_alihsan_btr`).
    *   Import file database terbaru (cari file `.sql` di root directory).

4.  **Konfigurasi Aplikasi**
    *   Buka file `config.php`.
    *   Sesuaikan kredensial database (`$host`, `$user`, `$pass`, `$db`).
    *   Atur `DEV_MODE` ke `true` jika dalam tahap pengembangan.

5.  **Jalankan Server**
    *   Gunakan XAMPP atau jalankan server lokal:
    ```bash
    php -S localhost:8000
    ```

---

## 📂 Struktur Direktori

```text
├── admin/          # Modul Administrator
├── guru/           # Modul Guru
├── siswa/          # Modul Siswa
├── kepsek/         # Modul Kepala Sekolah
├── ajax/           # Endpoint untuk request asinkron
├── assets/         # Resource statis (CSS, Images, JS)
├── includes/       # Core logic (fungsi.php, config.php, header, footer)
├── migrations/     # File migrasi database SQL
├── uploads/        # Penyimpanan file materi & tugas siswa
└── vendor/         # Dependensi Composer
```

---

## 📝 Catatan Pengembangan

*   Aplikasi ini menggunakan sistem **Generated Columns** pada MySQL untuk perhitungan nilai akhir, pastikan versi MySQL mendukung fitur ini.
*   Folder `uploads/` harus memiliki izin akses (write permission) agar fitur upload file berfungsi.
*   Gunakan `DEV_MODE = false` pada file `config.php` saat deployment ke server produksi untuk mengaktifkan logging error ke file dan menyembunyikan detail teknis dari pengguna.

---

**LMS MTs Al-Ihsan Batujajar** - *Meningkatkan Kualitas Pendidikan melalui Teknologi.*
