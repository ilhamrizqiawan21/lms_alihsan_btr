# 📚 LMS MTs AL-IHSAN BATUJAJAR - PROJECT DOCUMENTATION

## 📋 Overview

| Aspek | Detail |
|-------|--------|
| **Nama Project** | Learning Management System (LMS) MTs Al-Ihsan Batujajar |
| **Bahasa** | PHP Native (Procedural) |
| **Database** | MySQL (InnoDB, utf8mb4) |
| **Architecture** | MVC-ish dengan role-based access control |
| **Status** | Production-ready (DEV_MODE untuk development) |
| **Tanggal Aktif** | 2025/2026, Semester 2 |

---

## 🏗️ ARSITEKTUR BACKEND

### Technology Stack

```
Backend:
├── PHP 7.4+ (Native, no framework)
├── MySQLi (Object-oriented)
├── PHPOffice PHPSpreadsheet (Excel export)
├── Composer (dependency manager)
└── Session-based authentication

Frontend:
├── HTML5
├── CSS3 (Custom + CSS Variables)
├── JavaScript (Vanilla)
├── Chart.js (Dashboard graphs)
└── Font Awesome 6
```

### Core Files

#### [config.php](config.php) - Konfigurasi Utama
```php
// Database Configuration
$host = 'localhost';
$user = 'root';
$pass = 'Hash2856@';
$db = 'lms_alihsan_btr';

// Security & Session
define('DEV_MODE', true);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Base URL
$base_url = DEV_MODE ? '/lms_alihsan_btr/' : '/';
```

**Features:**
- MySQLi connection dengan charset utf8mb4
- DEV_MODE untuk error handling
- Session security flags
- Timezone: Asia/Jakarta

---

#### [index.php](index.php) - Login Page
**Flow:**
1. Check session → redirect if logged in
2. POST: Prepared statement query ke users + roles
3. Password verification: bcrypt atau MD5 (auto-upgrade)
4. Session regeneration (CSRF prevention)
5. Login logging (IP + User-Agent)
6. Role-based redirect

**Security:**
- ✅ Prepared statements (no SQL injection)
- ✅ Session regenerate on login
- ✅ Generic error message (no username disclosure)
- ✅ Password auto-upgrade MD5 → bcrypt

---

#### [includes/fungsi.php](includes/fungsi.php) - Core Functions

**Authentication & Authorization**
```php
cek_login($allowed_roles = [])
// Multi-role access check, redirects to login if unauthorized
```

**Settings Management**
```php
get_pengaturan($conn, $key)      // Retrieve app setting
set_pengaturan($conn, $key, $value) // Save app setting
get_tahun_ajaran_aktif($conn)    // Get active academic year
get_semester_aktif($conn)        // Get active semester
```

**Security & XSS Protection**
```php
e($str)                          // htmlspecialchars() wrapper
csrf_token()                      // Generate/retrieve CSRF token
csrf_verify()                     // Validate CSRF token
```

**Data Formatting**
```php
tgl_indonesia($tanggal)          // Format: "1 Januari 2025"
status_badge($status)             // Attendance badge (hadir/sakit/izin/alpha)
predikat_sikap($nilai)            // Convert 1-5 to SB/B/C/KB/TB
```

**Data Retrieval**
```php
get_kelas_mapel_guru($conn, $guru_id, $tahun_ajaran, $semester)
// Get teacher's assigned classes with subject info
```

**Calculations**
```php
hitung_rata_akhir($sum1, $sum2, $sum3, $sum4, $sts, $sas, $sat, $nilai_harian)
// Calculate final grade average

get_rata_nilai_harian($conn, $siswa_id, $kelas_mapel_id)
// Get student's daily value average from tasks
```

**Utility**
```php
semester_options($selected)      // Generate semester dropdown
tahun_ajaran_options($selected)  // Generate academic year dropdown
set_flash($type, $message)       // Set flash message
show_flash()                      // Display flash message
```

---

#### [includes/header.php](includes/header.php) - Layout Header
- Responsive navigation
- Dynamic theme color via CSS variables
- Sidebar + Topbar layout
- Mobile overlay support
- Font: Plus Jakarta Sans + Noto Sans

#### [includes/footer.php](includes/footer.php) - Layout Footer
- Close body/html tags
- Global JavaScript initialization

---

### Frontend Styling

#### [style.css](style.css) - Main Stylesheet

**Color System:**
```css
:root {
  /* Primary: Islamic Green */
  --primary-500: #22c55e;
  --primary-600: #16a34a;
  --primary-700: #15803d;
  --primary-800: #166534;
  
  /* Accent: Gold */
  --gold-500: #f59e0b;
  
  /* Status Colors */
  --success: #22c55e;    /* Hadir */
  --warning: #fbbf24;    /* Sakit */
  --info:    #3b82f6;    /* Izin */
  --danger:  #ef4444;    /* Alpha */
}
```

**Component Classes:**
- `.sidebar` - Left navigation
- `.topbar` - Top header
- `.page-header` - Page title section
- `.stats-grid` - Dashboard statistics
- `.badge-hadir`, `.badge-sakit`, `.badge-izin`, `.badge-alpha`
- `.modern-table` - Data table styling

**Responsive:**
- Breakpoint: 768px
- Mobile-first approach
- Flexible grid layouts

---

#### [assets/css/notifikasi.css](assets/css/notifikasi.css)
- Notification styles
- Toast/alert components

#### [assets/images/](assets/images/)
- Favicon dan other images

---

## 🗄️ DATABASE SCHEMA

### Core Tables (20+ tables)

#### 1. **users** - User Accounts
```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,        -- bcrypt hash
  role_id TINYINT NOT NULL,
  nama_lengkap VARCHAR(100),
  nip_nis VARCHAR(20) UNIQUE,            -- NIP for guru, NIS for siswa
  jenis_kelamin ENUM('L','P'),
  foto VARCHAR(255),
  is_active TINYINT DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);
```

**Current Users:**
- 1 Admin (admin/password_hash)
- ~50 Teachers (guru)
- ~350 Students (siswa)
- 1 Headmaster (kepala_sekolah)

---

#### 2. **roles** - Role Definitions
```sql
CREATE TABLE roles (
  id TINYINT PRIMARY KEY AUTO_INCREMENT,
  nama_role VARCHAR(20)
);
```

**Roles:**
| ID | Role | Module |
|----|----|--------|
| 1 | admin | Admin panel |
| 2 | guru | Teacher dashboard |
| 3 | siswa | Student dashboard |
| 4 | kepala_sekolah | Principal dashboard |

---

#### 3. **siswa** - Student Records
```sql
CREATE TABLE siswa (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  nis VARCHAR(20) UNIQUE NOT NULL,
  kelas_id INT NOT NULL,
  angkatan VARCHAR(10),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id)
);
```

**Example Data:**
- NIS: 7101-7131 (VII-A)
- NIS: 7201-7231 (VII-B)
- Similar for VIII & IX

---

#### 4. **kelas** - Classes
```sql
CREATE TABLE kelas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tingkat ENUM('VII','VIII','IX'),
  nama_kelas VARCHAR(20) UNIQUE
);
```

**Classes:**
- **Tingkat VII:** VII-A, VII-B, VII-C, VII-D (4 classes)
- **Tingkat VIII:** VIII-A, VIII-B, VIII-C, VIII-D, VIII-E (5 classes)
- **Tingkat IX:** IX-A, IX-B, IX-C, IX-D, IX-E (5 classes)

---

#### 5. **mata_pelajaran** - Subjects
```sql
CREATE TABLE mata_pelajaran (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kode VARCHAR(10) UNIQUE,
  nama_mapel VARCHAR(100),
  urutan INT
);
```

**22 Subjects:**
1. Bahasa Indonesia
2. Bahasa Arab
3. Bahasa Inggris
4. Bahasa Sunda
5. Matematika
6. IPA (Science)
7. IPS (Social Studies)
8. PJOK (Physical Education)
9. Informatika (IT)
10. Akidah Akhlak (Islamic Ethics)
11. SKI (Islamic History)
12. Fiqih (Islamic Law)
13. Al-Qur'an Hadis
14. Seni Budaya (Arts)
15. Tahfidz Qur'an
16. Praktik Ibadah (Worship Practice)
17. English Conversation
18. Pendidikan Pancasila
+ more...

---

#### 6. **kelas_mapel** - Class-Subject Assignment
```sql
CREATE TABLE kelas_mapel (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kelas_id INT NOT NULL,
  mapel_id INT NOT NULL,
  guru_id INT NOT NULL,              -- Teacher assignment
  tahun_ajaran_id INT NOT NULL,
  semester ENUM('1','2'),
  UNIQUE KEY (kelas_id, mapel_id, tahun_ajaran_id, semester),
  FOREIGN KEY (kelas_id) REFERENCES kelas(id),
  FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id),
  FOREIGN KEY (guru_id) REFERENCES users(id),
  FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id)
);
```

**Purpose:** Maps which teacher teaches which subject to which class in which semester

---

#### 7. **tahun_ajaran** - Academic Year
```sql
CREATE TABLE tahun_ajaran (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tahun VARCHAR(10) UNIQUE,           -- e.g., "2025/2026"
  is_active TINYINT DEFAULT 0
);
```

**Active Year:** 2025/2026

---

#### 8. **tugas** - Assignments
```sql
CREATE TABLE tugas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kelas_mapel_id INT NOT NULL,
  judul VARCHAR(200),
  deskripsi TEXT,
  kategori_nilai ENUM('SUM1','SUM2','SUM3','SUM4','NH'),  -- Grade category
  batas_waktu DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kelas_mapel_id) REFERENCES kelas_mapel(id)
);
```

**Kategori Nilai:**
- **SUM1-4:** Sumatif assessment (4 periods)
- **NH:** Nilai Harian (Daily grades)

---

#### 9. **pengumpulan_tugas** - Task Submission
```sql
CREATE TABLE pengumpulan_tugas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tugas_id INT NOT NULL,
  siswa_id INT NOT NULL,
  status ENUM('belum','sudah') DEFAULT 'belum',
  nilai DECIMAL(5,2),
  file_upload VARCHAR(255),          -- PDF/DOC file
  teks_jawaban TEXT,                 -- Text answer
  catatan TEXT,                      -- Teacher feedback
  tanggal_kumpul DATETIME,
  UNIQUE KEY (tugas_id, siswa_id),
  FOREIGN KEY (tugas_id) REFERENCES tugas(id),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id)
);
```

---

#### 10. **absensi** - Attendance
```sql
CREATE TABLE absensi (
  id INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id INT NOT NULL,
  kelas_mapel_id INT NOT NULL,
  tanggal DATE,
  status ENUM('hadir','sakit','izin','alpha'),
  keterangan TEXT,
  UNIQUE KEY (siswa_id, kelas_mapel_id, tanggal),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  FOREIGN KEY (kelas_mapel_id) REFERENCES kelas_mapel(id)
);
```

**Status:**
- **hadir:** Present (present)
- **sakit:** Sick (with reason)
- **izin:** Permission/Excused (with reason)
- **alpha:** Absent (without permission)

---

#### 11. **nilai_akhir** - Final Grades
```sql
CREATE TABLE nilai_akhir (
  id INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id INT,
  kelas_mapel_id INT,
  tahun_ajaran_id INT,
  semester ENUM('1','2'),
  sum1 DECIMAL(5,2),                 -- Sumatif 1
  sum2 DECIMAL(5,2),                 -- Sumatif 2
  sum3 DECIMAL(5,2),                 -- Sumatif 3
  sum4 DECIMAL(5,2),                 -- Sumatif 4
  nilai_harian DECIMAL(5,2),         -- Daily grades
  sts DECIMAL(5,2),                  -- Mid semester
  sas DECIMAL(5,2),                  -- Semester mid-assessment
  sat DECIMAL(5,2),                  -- Semester final assessment
  rata_akhir DECIMAL(5,2) GENERATED AS ((sum1+sum2+sum3+sum4+sts+sas+sat)/7),
  UNIQUE KEY (siswa_id, kelas_mapel_id, tahun_ajaran_id, semester)
);
```

**Grading Formula:**
```
rata_akhir = (SUM1 + SUM2 + SUM3 + SUM4 + STS + SAS + SAT) / 7
```

---

#### 12. **sikap_sosial** - Social Attitude Scores
```sql
CREATE TABLE sikap_sosial (
  id INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id INT,
  kelas_mapel_id INT,
  tahun_ajaran_id INT,
  semester ENUM('1','2'),
  empati INT DEFAULT 3,              -- 1-5 scale
  kerjasama INT DEFAULT 3,
  toleransi INT DEFAULT 3,
  percaya_diri INT DEFAULT 3,
  komunikasi INT DEFAULT 3,
  UNIQUE KEY (siswa_id, kelas_mapel_id, tahun_ajaran_id, semester)
);
```

**Scale Conversion:**
- 5 → SB (Sangat Baik / Very Good)
- 4 → B (Baik / Good)
- 3 → C (Cukup / Sufficient)
- 2 → KB (Kurang Baik / Less Good)
- 1 → TB (Tidak Baik / Not Good)

---

#### 13. **materi** - Course Materials
```sql
CREATE TABLE materi (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kelas_mapel_id INT,
  judul VARCHAR(200),
  deskripsi TEXT,
  file_materi VARCHAR(255),          -- PDF file path
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kelas_mapel_id) REFERENCES kelas_mapel(id)
);
```

**Upload Location:** `/uploads/materi/`

---

#### 14. **chat_messages** - Chat System
```sql
CREATE TABLE chat_messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  kelas_mapel_id INT,
  message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (kelas_mapel_id) REFERENCES kelas_mapel(id)
);
```

---

#### 15. **pengumuman** - Announcements
```sql
CREATE TABLE pengumuman (
  id INT PRIMARY KEY AUTO_INCREMENT,
  judul VARCHAR(200),
  isi TEXT,
  target ENUM('semua','guru','siswa','kelas_mapel') DEFAULT 'semua',
  target_kelas TEXT,
  kelas_mapel_id INT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (kelas_mapel_id) REFERENCES kelas_mapel(id)
);
```

**Target Types:**
- **semua:** All users
- **guru:** Teachers only
- **siswa:** Students only
- **kelas_mapel:** Specific class-subject

---

#### 16. **notifikasi** - Notifications
```sql
CREATE TABLE notifikasi (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  tipe ENUM('tugas_baru','nilai_baru','chat_baru','komentar_tugas','kumpul_tugas'),
  judul VARCHAR(255),
  pesan TEXT,
  link VARCHAR(255),
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Notification Types:**
- tugas_baru (New assignment)
- nilai_baru (New grade)
- chat_baru (New chat)
- kumpul_tugas (Task submission)

---

#### 17. **log_login** - Login Tracking
```sql
CREATE TABLE log_login (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  username VARCHAR(50),
  nama_lengkap VARCHAR(100),
  role VARCHAR(20),
  ip_address VARCHAR(45),
  user_agent TEXT,
  login_time DATETIME NOT NULL,
  KEY (user_id, login_time)
);
```

**Purpose:** Audit trail for all logins

---

#### 18. **pengaturan** - Application Settings
```sql
CREATE TABLE pengaturan (
  id INT PRIMARY KEY AUTO_INCREMENT,
  `key` VARCHAR(50) UNIQUE,
  value VARCHAR(255)
);
```

**Current Settings:**
| Key | Value |
|-----|-------|
| warna_tema | biru-azure |
| nama_sekolah | MTs. Al-Ihsan Batujajar |
| semester_aktif | 2 |
| tahun_ajaran_aktif | 2025/2026 |

---

## 🎨 FRONTEND STRUCTURE

### Directory Layout
```
root/
├── index.php                 # Login page
├── logout.php               # Logout handler
├── config.php               # Database config
├── style.css                # Main stylesheet
├── includes/
│   ├── header.php          # HTML header + navbar
│   ├── footer.php          # HTML footer
│   └── fungsi.php          # Core functions
├── assets/
│   ├── css/
│   │   └── notifikasi.css   # Notification styles
│   └── images/
│       └── favicon.ico
├── admin/                   # Admin dashboard
│   ├── dashboard.php
│   ├── user_management.php
│   ├── mata_pelajaran.php
│   ├── kelas_siswa.php
│   ├── kelas_mapel.php
│   ├── tahun_ajaran.php
│   ├── pengaturan.php
│   ├── pengumuman.php
│   ├── absensi.php
│   ├── export_absensi_excel.php
│   ├── rekap_absensi.php
│   ├── cetak_absensi.php
│   ├── export_nilai_excel.php
│   ├── rekap_nilai.php
│   ├── cetak_nilai.php
│   ├── export_sikap_excel.php
│   ├── rekap_sikap.php
│   └── cetak_sikap.php
├── guru/                    # Teacher dashboard
│   ├── dashboard.php
│   ├── absensi.php
│   ├── tugas.php
│   ├── olah_nilai.php
│   ├── sikap.php
│   ├── materi.php
│   ├── chat.php
│   ├── profil.php
│   └── rekap_nilai.php
├── siswa/                   # Student dashboard
│   ├── dashboard.php
│   ├── tugas_saya.php
│   ├── upload_tugas.php
│   ├── edit_pengumpulan.php
│   ├── nilai_saya.php
│   ├── materi_saya.php
│   ├── chat.php
│   └── profil.php
├── kepsek/                  # Principal dashboard
│   ├── dashboard.php
│   ├── laporan.php
│   ├── rekap_absensi.php
│   ├── cetak_absensi.php
│   ├── export_absensi_excel.php
│   ├── rekap_nilai.php
│   ├── cetak_nilai.php
│   ├── export_nilai_excel.php
│   ├── rekap_sikap.php
│   ├── cetak_sikap.php
│   ├── export_sikap_excel.php
│   ├── rekap_tugas.php
│   └── laporan.php
├── ajax/                    # AJAX endpoints
│   ├── get_kelas_by_mapel.php
│   ├── get_messages.php
│   ├── get_notifikasi.php
│   ├── get_siswa_by_tugas.php
│   ├── mark_read.php
│   └── send_message.php
├── uploads/                 # File uploads
│   ├── materi/             # Course materials (PDF)
│   └── tugas_siswa/        # Student submissions
└── vendor/                  # Composer dependencies
    └── phpoffice/phpspreadsheet/
```

---

## 👥 ROLE-BASED MODULES

### 1. ADMIN MODULE (`admin/`)

**Dashboard Features:**
- 📊 Statistics: Total siswa, guru, kelas, mapel
- 📈 30-day attendance chart
- 🔐 Recent login logs
- 📰 Latest announcements

**Management Pages:**
- **user_management.php** - Create/edit/delete users (guru, siswa, admin)
- **mata_pelajaran.php** - Manage subjects
- **kelas_siswa.php** - Assign students to classes
- **kelas_mapel.php** - Assign teachers to class-subject
- **tahun_ajaran.php** - Create academic years
- **pengaturan.php** - App settings (theme color, active semester)

**Reporting:**
- **rekap_absensi.php** - Attendance summary
- **rekap_nilai.php** - Grade summary
- **rekap_sikap.php** - Attitude summary
- **cetak_absensi.php** - Print attendance
- **cetak_nilai.php** - Print grades
- **cetak_sikap.php** - Print attitudes

**Export:**
- **export_absensi_excel.php** - Excel export for attendance
- **export_nilai_excel.php** - Excel export for grades
- **export_sikap_excel.php** - Excel export for attitudes

---

### 2. GURU (TEACHER) MODULE (`guru/`)

**Dashboard Features:**
- 📚 Assigned classes & subjects
- 👥 Total students taught
- ✏️ Ungraded tasks counter
- 📅 Monthly attendance stats

**Main Pages:**
- **absensi.php** - Record attendance per class per date
- **tugas.php** - Create assignments, grade submissions, view notifications
- **olah_nilai.php** - Enter final grades (SUM1-4, STS, SAS, SAT)
- **sikap.php** - Score social attitudes (empati, kerjasama, toleransi, etc)
- **materi.php** - Upload course materials (PDF)
- **chat.php** - Chat with students per class-subject
- **rekap_nilai.php** - View grade summary
- **profil.php** - Edit profile (name, photo, contact)

**Key Features:**
- ✅ Can only access own classes
- ✅ Auto-calculate average grades
- ✅ Bulk grading interface
- ✅ Student list per class

---

### 3. SISWA (STUDENT) MODULE (`siswa/`)

**Dashboard Features:**
- 📋 Incomplete tasks count
- 📊 Monthly attendance percentage
- 📰 Relevant announcements
- 📜 Recent assignments

**Main Pages:**
- **tugas_saya.php** - View assignments with submission status
- **upload_tugas.php** - Submit assignment (file or text)
- **edit_pengumpulan.php** - Modify existing submission
- **nilai_saya.php** - View grades per subject
- **materi_saya.php** - Access course materials
- **chat.php** - Chat with teachers
- **profil.php** - Edit profile

**Restrictions:**
- ❌ Cannot see other students' data
- ❌ Cannot modify grades
- ❌ Can only submit before deadline

---

### 4. KEPALA SEKOLAH (PRINCIPAL) MODULE (`kepsek/`)

**Dashboard Features:**
- 📊 Overall statistics (students, teachers, classes)
- 📈 30-day attendance chart
- 🏆 Top absent students (last 30 days)
- 📍 Students with incomplete tasks
- 📰 Latest announcements

**Reporting Pages:**
- **laporan.php** - Main report hub
- **rekap_absensi.php** - Attendance detailed report
- **rekap_nilai.php** - Grade detailed report
- **rekap_sikap.php** - Attitude detailed report
- **rekap_tugas.php** - Task completion report

**Export & Print:**
- **export_absensi_excel.php** - Excel download
- **export_nilai_excel.php** - Excel download
- **export_sikap_excel.php** - Excel download
- **cetak_absensi.php** - Print-friendly view
- **cetak_nilai.php** - Print-friendly view
- **cetak_sikap.php** - Print-friendly view

---

## 🔒 SECURITY FEATURES

### Implemented Security

✅ **SQL Injection Prevention**
```php
// All user input uses prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
```

✅ **XSS Prevention**
```php
// Output escaping via htmlspecialchars()
echo e($user_input);  // Safely escaped
```

✅ **Authentication & Session**
```php
// Session regeneration on login
session_regenerate_id(true);
// Secure session flags
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
```

✅ **Password Security**
```php
// bcrypt hashing with automatic MD5 → bcrypt migration
$password_hash = password_hash($password_input, PASSWORD_BCRYPT);
password_verify($password_input, $stored_hash);
```

✅ **CSRF Protection**
```php
// Generate & validate CSRF tokens
csrf_token()     // Generate or retrieve
csrf_verify()    // Validate form submission
```

✅ **Role-Based Access Control**
```php
// Every page starts with role check
cek_login([2]);  // Only role_id 2 (guru) can access
```

✅ **Login Logging**
```sql
-- Every login tracked with IP and User-Agent
INSERT INTO log_login (user_id, ip_address, user_agent, login_time)
VALUES (?, ?, ?, NOW())
```

### Development vs Production

**DEV_MODE = true:**
- All errors displayed
- Error logging enabled

**DEV_MODE = false:**
- Errors hidden from users
- Log to `/logs/error.log`
- HTTPS session cookie enforced

---

## 📡 AJAX ENDPOINTS

### [ajax/get_kelas_by_mapel.php](ajax/get_kelas_by_mapel.php)
Get classes assigned to a specific subject
```
GET /ajax/get_kelas_by_mapel.php?mapel_id=5
```

### [ajax/get_siswa_by_tugas.php](ajax/get_siswa_by_tugas.php)
Get students in a class for task assignment
```
GET /ajax/get_siswa_by_tugas.php?kelas_mapel_id=10
```

### [ajax/send_message.php](ajax/send_message.php)
Send chat message
```
POST /ajax/send_message.php
{
  kelas_mapel_id: 10,
  message: "Hello class"
}
```

### [ajax/get_messages.php](ajax/get_messages.php)
Fetch chat messages for a class
```
GET /ajax/get_messages.php?kelas_mapel_id=10
```

### [ajax/get_notifikasi.php](ajax/get_notifikasi.php)
Fetch user notifications
```
GET /ajax/get_notifikasi.php
```

### [ajax/mark_read.php](ajax/mark_read.php)
Mark notification as read
```
POST /ajax/mark_read.php
{
  notifikasi_id: 5
}
```

---

## 📤 FILE UPLOADS

### Directory Structure
```
uploads/
├── materi/
│   ├── {random_filename}.pdf          # Course materials
│   └── 1778808738_aaef24ce.pdf
└── tugas_siswa/
    ├── {random_filename}.pdf          # Student submissions
    ├── {random_filename}.doc
    └── {random_filename}.txt
```

### Upload Configuration
- **Max File Size:** (PHP default: 2MB, configurable in php.ini)
- **Allowed Types:** PDF, DOC, DOCX, JPG, PNG, TXT
- **Filename:** Random hash + original extension

---

## 🔄 KEY WORKFLOWS

### Login Flow
```
1. User submit username/password
   ↓
2. Query: SELECT * FROM users WHERE username = ?
   ↓
3. Verify password (bcrypt or MD5)
   ↓
4. MD5 detected? → Auto-upgrade to bcrypt
   ↓
5. Insert login log (IP, User-Agent)
   ↓
6. Session regenerate + set $_SESSION variables
   ↓
7. Redirect to role dashboard
```

---

### Assignment Submission Flow
```
1. Teacher create tugas → INSERT into tugas table
   ↓
2. System auto-create pengumpulan_tugas rows for all class students
   ↓
3. Student view tugas in siswa/tugas_saya.php
   ↓
4. Student upload file or text answer
   ↓
5. UPDATE pengumpulan_tugas: status='sudah', tanggal_kumpul=NOW()
   ↓
6. Teacher grade submission
   ↓
7. UPDATE nilai = X.XX
   ↓
8. Student can view grade in siswa/nilai_saya.php
```

---

### Attendance Recording Flow
```
1. Teacher access guru/absensi.php
   ↓
2. Select kelas_mapel and date
   ↓
3. List all students with attendance form
   ↓
4. Select status for each student (hadir/sakit/izin/alpha)
   ↓
5. Submit form
   ↓
6. INSERT/UPDATE into absensi table
   ↓
7. Dashboard auto-calculate stats
```

---

### Grade Calculation Flow
```
1. Teacher enter nilai_akhir components:
   - SUM1, SUM2, SUM3, SUM4 (sumatif)
   - STS, SAS, SAT (assessments)
   - nilai_harian (daily grades from tasks)
   ↓
2. System auto-calculates rata_akhir via GENERATED column:
   rata_akhir = (SUM1+SUM2+SUM3+SUM4+STS+SAS+SAT)/7
   ↓
3. Student can view final grade in siswa/nilai_saya.php
```

---

## 📊 CURRENT SYSTEM DATA

### Active Configuration
```
Tahun Ajaran Aktif: 2025/2026
Semester Aktif: 2 (even semester / second semester)
Warna Tema: biru-azure
```

### User Statistics
| Role | Count | Example |
|------|-------|---------|
| Admin | 1 | admin |
| Guru | ~50 | 022 (ILHAM RIZQIAWAN, S.Pd.) |
| Siswa | ~350 | 7101-7131 (VII-A), 7201-7231 (VII-B), etc |
| Kepala Sekolah | 1 | 001 (Dra. Hj. LINA NURHASANAH) |

### Class Distribution
```
Tingkat VII:
├── VII-A: ~30 students
├── VII-B: ~30 students
├── VII-C: ~30 students
└── VII-D: ~30 students

Tingkat VIII:
├── VIII-A: ~30 students
├── VIII-B: ~30 students
├── VIII-C: ~30 students
├── VIII-D: ~30 students
└── VIII-E: ~30 students

Tingkat IX:
├── IX-A: ~30 students
├── IX-B: ~30 students
├── IX-C: ~30 students
├── IX-D: ~30 students
└── IX-E: ~30 students
```

### Sample Subjects per Class
- **Fiqih** (Islamic Law) - All classes
- **IPA** (Science) - All classes
- **Matematika** (Math) - All classes
- **Bahasa Indonesia** (Indonesian) - All classes
+ 18 more subjects

---

## 🎯 DESIGN PATTERNS

### Architecture Pattern
- **Procedural with OOP Database:** Native PHP procedural code + MySQLi OOP
- **Separation of Concerns:** 
  - config.php (configuration)
  - fungsi.php (business logic)
  - header.php & footer.php (presentation)
  - role-specific modules (feature modules)

### Security Pattern
```
Every page:
1. include 'config.php'          # Load config & DB
2. cek_login([allowed_roles])   # Verify authentication
3. Prepared statements for queries
4. e() for output escaping
5. Optional CSRF token check
```

### Data Access Pattern
```php
// Get → Process → Update
$result = $conn->prepare("SELECT ...");
$result->bind_param(...);
$result->execute();
$data = $result->get_result();

// Process safely
$processed = some_function($data);

// Update safely
$stmt = $conn->prepare("UPDATE ...");
$stmt->bind_param(...);
$stmt->execute();
```

### UI Pattern
- **Consistent Header/Footer:** Include header.php at top, footer.php at bottom
- **Dynamic Theming:** CSS variables for color switching
- **Responsive Design:** Mobile-first CSS with 768px breakpoint
- **Accessibility:** Font Awesome icons + semantic HTML

---

## 🔧 CONFIGURATION & DEPLOYMENT

### Environment Variables (in config.php)
```php
define('DEV_MODE', true);              // Change to false for production

// Database
$host = 'localhost';
$user = 'root';
$pass = 'Hash2856@';
$db = 'lms_alihsan_btr';

// Base URL
$base_url = DEV_MODE ? '/lms_alihsan_btr/' : '/';
```

### Dependencies (composer.json)
```json
{
  "require": {
    "phpoffice/phpspreadsheet": "^5.7"
  }
}
```

### Installation
```bash
# Clone/setup project
cd /path/to/lms_alihsan_btr

# Install dependencies
composer install

# Create database
mysql -u root -p < lms_alihsan_btr_\(4\).sql

# Configure (update config.php)
# Set database credentials
# Set DEV_MODE appropriately

# Run
php -S localhost:8000
```

---

## 📝 NOTES FOR DEVELOPERS

### Code Style
- Function names: snake_case (e.g., `cek_login()`)
- Variable names: snake_case (e.g., `$tahun_aktif`)
- Class names: N/A (no OOP classes used)
- Indentation: 4 spaces

### Best Practices Used
✅ Prepared statements for all user input
✅ Function helpers in includes/fungsi.php
✅ Consistent error handling
✅ Role-based access control
✅ Session management
✅ Password hashing with bcrypt
✅ Login logging for audit trail
✅ Generated columns for calculations

### Common Issues & Solutions
| Issue | Solution |
|-------|----------|
| "Koneksi database gagal" | Check $host, $user, $pass in config.php |
| Blank page | Check DEV_MODE=true in config.php to see errors |
| Session not persisting | Verify session.save_path is writable |
| Images not loading | Check assets/images/ exists and image paths are correct |
| Download Excel fails | Verify /uploads/ directory is writable |

---

**Documentation Last Updated:** May 29, 2026  
**Project Status:** ✅ Production-Ready  
**Version:** 1.0 (2025/2026 Academic Year, Semester 2)
