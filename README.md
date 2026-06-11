# LMS Al-Ihsan Batujajar

## Description / Deskripsi

**English:**
LMS Al-Ihsan Batujajar is a Learning Management System specifically designed for MTs. Al-Ihsan Batujajar school. This platform provides comprehensive tools for online teaching and learning, course management, student progress tracking, and interactive educational content delivery.

**Indonesia:**
LMS Al-Ihsan Batujajar adalah Learning Management System yang dirancang khusus untuk sekolah MTs. Al-Ihsan Batujajar. Platform ini menyediakan alat komprehensif untuk pengajaran dan pembelajaran online, manajemen kursus, pelacakan progres siswa, dan penyampaian konten pendidikan interaktif.

---

## Installation / Instalasi

### Requirements / Persyaratan:
- PHP (v7.4 or higher / v7.4 atau lebih tinggi)
- Composer
- MySQL atau PostgreSQL
- Laravel Framework
- Node.js (untuk asset compilation / kompilasi aset)

### Steps / Langkah-langkah:

**English:**
1. Clone the repository:
   ```bash
   git clone https://github.com/ilhamrizqiawan21/lms_alihsan_btr.git
   cd lms_alihsan_btr
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Setup environment file:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database and run migrations:
   ```bash
   php artisan migrate
   ```

5. Compile front-end assets:
   ```bash
   npm run dev
   ```

6. Start the application:
   ```bash
   php artisan serve
   ```

**Indonesia:**
1. Clone repository:
   ```bash
   git clone https://github.com/ilhamrizqiawan21/lms_alihsan_btr.git
   cd lms_alihsan_btr
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Setup file environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Konfigurasi database dan jalankan migrasi:
   ```bash
   php artisan migrate
   ```

5. Kompilasi aset front-end:
   ```bash
   npm run dev
   ```

6. Jalankan aplikasi:
   ```bash
   php artisan serve
   ```

---

## Usage / Penggunaan

### English:
1. Access the platform at `http://localhost:8000`
2. Login with your teacher or student account
3. Browse available courses
4. Enroll in courses you want to take
5. Access course materials and lessons
6. Submit assignments and participate in discussions
7. View grades and learning progress
8. Download certificates upon course completion

### Indonesia:
1. Akses platform di `http://localhost:8000`
2. Login dengan akun guru atau siswa Anda
3. Jelajahi kursus yang tersedia
4. Daftar di kursus yang ingin Anda ikuti
5. Akses materi kursus dan pelajaran
6. Kirim tugas dan ikuti diskusi
7. Lihat nilai dan progres pembelajaran
8. Download sertifikat setelah menyelesaikan kursus

---

## Author / Penulis
Ilham Rizqiawan

## License / Lisensi
This project is licensed under the MIT License - see the LICENSE file for details.
