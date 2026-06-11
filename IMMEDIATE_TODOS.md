# 🚀 IMMEDIATE ACTION ITEMS - Next 2 Weeks

**Project:** LMS MTs Al-Ihsan Batujajar  
**Target Release:** Next 2 Weeks  
**Total Tasks:** 7 Priority Items  
**Estimated Total Time:** 40-60 hours

---

## 📋 TODO LIST

### **Task 1: Setup Backup Database Harian** 
**Priority:** 🔴 CRITICAL  
**Status:** ⬜ Not Started  
**Estimated Time:** 4-6 hours  
**Responsible:** DevOps / System Admin

#### Description
Implement automated daily database backups untuk disaster recovery dan data protection.

#### Subtasks
- [ ] Choose backup solution (Option A, B, or C below)
- [ ] Create backup script
- [ ] Test restore procedure
- [ ] Setup backup notification
- [ ] Document backup strategy
- [ ] Create backup storage location

#### Technical Details

**Option A: MySQL Dump (Simple, untuk shared hosting)**
```bash
# File: /home/www/backup.sh
#!/bin/bash
BACKUP_DIR="/home/www/backups/database"
MYSQL_USER="root"
MYSQL_PASS="Hash2856@"
DB_NAME="lms_alihsan_btr"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $MYSQL_USER -p$MYSQL_PASS $DB_NAME > \
    $BACKUP_DIR/lms_backup_$DATE.sql

# Compress
gzip $BACKUP_DIR/lms_backup_$DATE.sql

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Backup completed: lms_backup_$DATE.sql.gz"
```

**Option B: AWS S3 (Recommended untuk cloud)**
```bash
# File: /home/www/backup_s3.sh
#!/bin/bash
BACKUP_DIR="/tmp/db_backup"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
mysqldump -u root -p$MYSQL_PASS lms_alihsan_btr > $BACKUP_DIR/backup_$DATE.sql
gzip $BACKUP_DIR/backup_$DATE.sql

# Upload to S3
aws s3 cp $BACKUP_DIR/backup_$DATE.sql.gz \
    s3://lms-backups/database/backup_$DATE.sql.gz

# Cleanup local
rm -rf $BACKUP_DIR
```

**Option C: Cron Job Automation (Recommended)**
```bash
# Add to crontab
crontab -e

# Run backup every day at 2 AM
0 2 * * * /home/www/backup.sh >> /var/log/backup.log 2>&1

# Run backup every day at 2 PM (secondary)
0 14 * * * /home/www/backup_s3.sh >> /var/log/backup_s3.log 2>&1
```

#### Success Criteria
- ✅ Backup file created daily
- ✅ Can restore from backup without data loss
- ✅ Backup stored in separate location (not same server)
- ✅ Retention policy in place (minimum 7 days)
- ✅ Notification sent on backup completion/failure

#### Resources Needed
- Shell/Bash knowledge
- MySQL/mysqldump access
- S3 account (optional, if using cloud)
- Cron job access

#### Documentation Template
```markdown
## Database Backup Strategy

**Frequency:** Daily at 2 AM & 2 PM
**Storage:** 
  - Local: /home/www/backups/database/
  - Cloud: AWS S3 s3://lms-backups/database/
**Retention:** 30 days (7 days local, rest in S3)
**Recovery Time Objective (RTO):** 1 hour
**Recovery Point Objective (RPO):** 1 day

### Recovery Procedure
1. Download latest backup from S3
2. SSH to server
3. Run: mysql -u root -p < backup_file.sql
4. Verify data integrity
```

---

### **Task 2: Implement Rate Limiting Login**
**Priority:** 🔴 CRITICAL  
**Status:** ⬜ Not Started  
**Estimated Time:** 6-8 hours  
**Responsible:** Backend Developer

#### Description
Implementasikan rate limiting pada login untuk mencegah brute force attacks.

#### Subtasks
- [ ] Create rate_limiting table in database
- [ ] Implement login attempt tracking
- [ ] Add IP-based blocking
- [ ] Create admin bypass mechanism
- [ ] Setup alert system
- [ ] Test with multiple IPs

#### Technical Implementation

**Step 1: Create Database Table**
```sql
CREATE TABLE login_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45),
  username VARCHAR(50),
  attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) DEFAULT 0,
  KEY idx_ip_username_time (ip_address, username, attempt_time)
);

CREATE TABLE blocked_ips (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45) UNIQUE,
  blocked_until DATETIME,
  reason VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Step 2: Add Rate Limiting Function (includes/fungsi.php)**
```php
function check_rate_limit($conn, $ip_address, $username) {
    // Check if IP is blocked
    $stmt = $conn->prepare(
        "SELECT * FROM blocked_ips 
         WHERE ip_address = ? AND blocked_until > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['allowed' => false, 'reason' => 'ip_blocked'];
    }
    
    // Count failed attempts in last 15 minutes
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM login_attempts 
         WHERE ip_address = ? AND success = 0 
         AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    $failed_attempts = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($failed_attempts >= 5) {
        // Block IP for 30 minutes
        $blocked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $conn->prepare(
            "INSERT INTO blocked_ips (ip_address, blocked_until, reason) 
             VALUES (?, ?, 'Too many failed login attempts')"
        );
        $stmt->bind_param("ss", $ip_address, $blocked_until);
        $stmt->execute();
        
        return ['allowed' => false, 'reason' => 'too_many_attempts'];
    }
    
    return ['allowed' => true];
}

function log_login_attempt($conn, $ip_address, $username, $success = false) {
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (ip_address, username, success) 
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("ssi", $ip_address, $username, $success);
    return $stmt->execute();
}
```

**Step 3: Update index.php**
```php
<?php
include 'config.php';

$error = '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check rate limiting FIRST
    $rate_limit = check_rate_limit($conn, $ip_address, $_POST['username'] ?? '');
    
    if (!$rate_limit['allowed']) {
        if ($rate_limit['reason'] == 'ip_blocked') {
            $error = 'Akun Anda terkunci sementara. Coba lagi dalam 30 menit.';
        } else {
            $error = 'Terlalu banyak percobaan login. Silakan coba lagi nanti.';
        }
        log_login_attempt($conn, $ip_address, $_POST['username'] ?? '', 0);
        exit;
    }
    
    $username = trim($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? '';

    // ... existing authentication code ...
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stored_hash = $user['password'];
        $password_valid = false;

        if (substr($stored_hash, 0, 4) === '$2y$') {
            $password_valid = password_verify($password_input, $stored_hash);
        }
        elseif (strlen($stored_hash) == 32 && ctype_xdigit($stored_hash)) {
            if (md5($password_input) === $stored_hash) {
                $password_valid = true;
                $new_hash = password_hash($password_input, PASSWORD_BCRYPT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $new_hash, $user['id']);
                $upd->execute();
            }
        }

        if ($password_valid) {
            // Log successful attempt
            log_login_attempt($conn, $ip_address, $username, 1);
            
            // Clear any previous blocks for this IP
            $conn->query("DELETE FROM blocked_ips WHERE ip_address = '$ip_address'");
            
            // ... existing login flow ...
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            
            // ... rest of login ...
        } else {
            // Log failed attempt
            log_login_attempt($conn, $ip_address, $username, 0);
            $error = 'Password salah!';
        }
    } else {
        log_login_attempt($conn, $ip_address, $_POST['username'] ?? '', 0);
        $error = 'Username atau password tidak valid!';
    }
}
?>
```

#### Alert System (Optional: Email notification)
```php
// Add to admin dashboard
function get_login_alerts($conn) {
    $stmt = $conn->prepare(
        "SELECT ip_address, COUNT(*) as failed_count 
         FROM login_attempts 
         WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
         GROUP BY ip_address 
         HAVING failed_count >= 5"
    );
    $stmt->execute();
    return $stmt->get_result();
}

// Email alert to admin
$alerts = get_login_alerts($conn);
if ($alerts->num_rows > 0) {
    while ($alert = $alerts->fetch_assoc()) {
        mail('admin@school.com', 
             'Security Alert: Login Attempts',
             "IP: {$alert['ip_address']} - Failed: {$alert['failed_count']} attempts");
    }
}
```

#### Success Criteria
- ✅ After 5 failed attempts, IP blocked for 30 minutes
- ✅ Login attempts logged to database
- ✅ Blocked IPs cannot login even with correct password
- ✅ Admin can manually unblock IPs
- ✅ Alert sent on suspicious activity
- ✅ Whitelist for known IPs (school network)

#### Testing Procedure
```bash
# Test 1: Normal login
curl -X POST http://localhost/index.php \
  -d "username=admin&password=correct_password"
# Expected: Login success

# Test 2: 5 failed attempts
for i in {1..5}; do
  curl -X POST http://localhost/index.php \
    -d "username=admin&password=wrong_password"
done
# Expected: All fail, 5th shows "too many attempts"

# Test 3: 6th attempt (blocked)
curl -X POST http://localhost/index.php \
  -d "username=admin&password=correct_password"
# Expected: "IP blocked" message even with correct password
```

---

### **Task 3: Add File Upload Validation**
**Priority:** 🔴 CRITICAL  
**Status:** ⬜ Not Started  
**Estimated Time:** 3-4 hours  
**Responsible:** Backend Developer

#### Description
Implementasikan validasi ketat untuk file uploads (materi & tugas) untuk mencegah malicious files.

#### Subtasks
- [ ] Define allowed file types
- [ ] Add file size limits
- [ ] Implement MIME type checking
- [ ] Add file scanning mechanism
- [ ] Test with various file types
- [ ] Create error messages for invalid uploads

#### Technical Implementation

**Step 1: Create Validation Function (includes/fungsi.php)**
```php
function validate_upload_file($file, $upload_type = 'materi') {
    $errors = [];
    
    // File size limits (in bytes)
    $max_sizes = [
        'materi' => 50 * 1024 * 1024,      // 50 MB for materials
        'tugas'  => 10 * 1024 * 1024       // 10 MB for task submissions
    ];
    
    // Allowed MIME types
    $allowed_mimes = [
        'materi' => ['application/pdf', 'application/msword'],
        'tugas'  => ['application/pdf', 'text/plain', 
                     'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    ];
    
    // Allowed extensions
    $allowed_extensions = [
        'materi' => ['pdf', 'doc', 'docx'],
        'tugas'  => ['pdf', 'txt', 'doc', 'docx', 'jpg', 'jpeg', 'png']
    ];
    
    // Check if file exists
    if (!isset($file) || $file['error'] != UPLOAD_ERR_OK) {
        $errors[] = 'File tidak ditemukan atau error: ' . $file['error'];
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_sizes[$upload_type]) {
        $errors[] = "Ukuran file terlalu besar. Maksimal: " . 
                    ($max_sizes[$upload_type] / 1024 / 1024) . " MB";
    }
    
    // Get file extension
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    // Check extension
    if (!in_array($extension, $allowed_extensions[$upload_type])) {
        $errors[] = "Tipe file tidak diperbolehkan. " .
                    "Gunakan: " . implode(', ', $allowed_extensions[$upload_type]);
    }
    
    // Check MIME type (using finfo)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mimes[$upload_type])) {
        $errors[] = "MIME type tidak diperbolehkan: $mime_type";
    }
    
    // Check for dangerous content (basic check)
    $file_content = file_get_contents($file['tmp_name'], false, null, 0, 512);
    
    // Check for PHP code embedded
    if (preg_match('/<\?php|<\?=|\?>/i', $file_content)) {
        $errors[] = "File contains PHP code. Tidak diperbolehkan!";
    }
    
    // Check for dangerous patterns
    if (preg_match('/exec\(|system\(|passthru\(|shell_exec\(/i', $file_content)) {
        $errors[] = "File contains dangerous code.";
    }
    
    if (count($errors) > 0) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    return ['valid' => true];
}

function generate_safe_filename($original_filename) {
    // Remove path information
    $filename = basename($original_filename);
    
    // Get extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Generate random filename to prevent directory traversal
    $new_filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    
    return $new_filename;
}

function save_uploaded_file($file, $upload_dir, $upload_type = 'materi') {
    // Validate file
    $validation = validate_upload_file($file, $upload_type);
    if (!$validation['valid']) {
        return ['success' => false, 'errors' => $validation['errors']];
    }
    
    // Create upload directory if doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate safe filename
    $safe_filename = generate_safe_filename($file['name']);
    $target_path = $upload_dir . '/' . $safe_filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Set file permissions
        chmod($target_path, 0644);
        
        return [
            'success' => true,
            'filename' => $safe_filename,
            'path' => $target_path
        ];
    } else {
        return [
            'success' => false,
            'errors' => ['Gagal menyimpan file']
        ];
    }
}
```

**Step 2: Update Upload Pages**

For `guru/materi.php`:
```php
<?php
include '../config.php';
cek_login([2]); // Only teachers

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_materi'])) {
    $kelas_mapel_id = (int)$_POST['kelas_mapel_id'];
    $judul = e($_POST['judul']);
    
    // Validate file
    $result = save_uploaded_file($_FILES['file_materi'], 
                                __DIR__ . '/../uploads/materi', 
                                'materi');
    
    if ($result['success']) {
        // Save to database
        $stmt = $conn->prepare(
            "INSERT INTO materi (kelas_mapel_id, judul, file_materi, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("iss", $kelas_mapel_id, $judul, $result['filename']);
        
        if ($stmt->execute()) {
            set_flash('success', 'Materi berhasil diupload!');
            header('Location: materi.php');
            exit;
        }
    } else {
        // Display errors
        foreach ($result['errors'] as $error) {
            echo '<div class="alert alert-danger">' . e($error) . '</div>';
        }
    }
}
?>
```

For `siswa/upload_tugas.php`:
```php
<?php
include '../config.php';
cek_login([3]); // Only students

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_tugas'])) {
    $tugas_id = (int)$_POST['tugas_id'];
    $siswa_id = get_siswa_id($conn, $_SESSION['user_id']);
    
    // Validate file
    $result = save_uploaded_file($_FILES['file_tugas'],
                                __DIR__ . '/../uploads/tugas_siswa',
                                'tugas');
    
    if ($result['success']) {
        // Update or insert pengumpulan_tugas
        $stmt = $conn->prepare(
            "INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, file_upload, tanggal_kumpul, status)
             VALUES (?, ?, ?, NOW(), 'sudah')
             ON DUPLICATE KEY UPDATE file_upload = ?, tanggal_kumpul = NOW()"
        );
        $stmt->bind_param("iiss", $tugas_id, $siswa_id, $result['filename'], $result['filename']);
        
        if ($stmt->execute()) {
            set_flash('success', 'Tugas berhasil diupload!');
            header('Location: tugas_saya.php');
            exit;
        }
    } else {
        foreach ($result['errors'] as $error) {
            echo '<div class="alert alert-danger">' . e($error) . '</div>';
        }
    }
}
?>
```

**Step 3: Create .htaccess for uploads directory**
```apache
# File: /uploads/.htaccess
# Prevent PHP execution in uploads folder
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>

# Prevent direct access to certain files
<FilesMatch "\.(exe|bat|cmd|com)$">
    Deny from all
</FilesMatch>

# Disable directory listing
Options -Indexes
```

#### Success Criteria
- ✅ Only PDF, DOC, DOCX allowed for materi
- ✅ File size validated (50MB max for materi, 10MB for tugas)
- ✅ MIME type checked
- ✅ PHP files rejected
- ✅ Filenames sanitized
- ✅ Clear error messages shown to users
- ✅ All uploads logged to database

#### Testing Procedure
```bash
# Test 1: Valid PDF
curl -F "file_materi=@valid.pdf" \
  -F "judul=Materi Pelajaran" \
  http://localhost/guru/materi.php
# Expected: Upload success

# Test 2: PHP file disguised as PDF
curl -F "file_materi=@malicious.php.pdf" \
  http://localhost/guru/materi.php
# Expected: Rejected (PHP code detected)

# Test 3: File too large
curl -F "file_materi=@large_file.pdf" \
  http://localhost/guru/materi.php
# Expected: "File size too large"

# Test 4: Executable file
curl -F "file_materi=@malware.exe" \
  http://localhost/guru/materi.php
# Expected: "File type not allowed"
```

---

### **Task 4: Setup SSL Certificate**
**Priority:** 🟠 HIGH  
**Status:** ⬜ Not Started  
**Estimated Time:** 2-3 hours  
**Responsible:** DevOps / System Admin

#### Description
Implementasikan HTTPS dengan SSL certificate untuk enkripsi data transmission dan security.

#### Subtasks
- [ ] Obtain SSL certificate (free or paid)
- [ ] Install certificate on server
- [ ] Configure web server (Apache/Nginx)
- [ ] Setup automatic redirect HTTP → HTTPS
- [ ] Test SSL configuration
- [ ] Setup certificate auto-renewal

#### Option 1: Free SSL with Let's Encrypt (Recommended)
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Generate certificate
sudo certbot certonly --apache -d lms.mtsalihsan.sch.id -d www.lms.mtsalihsan.sch.id

# Auto-renew setup (cron job)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Test renewal
sudo certbot renew --dry-run
```

#### Option 2: Configure Apache with SSL
```apache
# File: /etc/apache2/sites-available/lms-ssl.conf
<VirtualHost *:443>
    ServerName lms.mtsalihsan.sch.id
    ServerAlias www.lms.mtsalihsan.sch.id
    
    DocumentRoot /var/www/html/lms_alihsan_btr
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/lms.mtsalihsan.sch.id/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/lms.mtsalihsan.sch.id/privkey.pem
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    
    # PHP Configuration
    <Directory /var/www/html/lms_alihsan_btr>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/lms_error.log
    CustomLog ${APACHE_LOG_DIR}/lms_access.log combined
</VirtualHost>

# HTTP to HTTPS redirect
<VirtualHost *:80>
    ServerName lms.mtsalihsan.sch.id
    ServerAlias www.lms.mtsalihsan.sch.id
    Redirect permanent / https://lms.mtsalihsan.sch.id/
</VirtualHost>
```

#### Option 3: Enable HTTPS in config.php
```php
// File: config.php

// Force HTTPS in production
if (!DEV_MODE && empty($_SERVER['HTTPS'])) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// Update session cookie settings for HTTPS
if (!DEV_MODE) {
    ini_set('session.cookie_secure', 1);   // Only send over HTTPS
    ini_set('session.cookie_samesite', 'Lax');
}

// Update base URL
$base_url = DEV_MODE ? '/lms_alihsan_btr/' : 'https://lms.mtsalihsan.sch.id/';
```

#### Option 4: Test SSL Configuration
```bash
# Test SSL certificate
openssl s_client -connect lms.mtsalihsan.sch.id:443

# Test SSL rating
curl https://www.ssllabs.com/ssltest/analyze.html?d=lms.mtsalihsan.sch.id

# Test HTTP redirect
curl -I http://lms.mtsalihsan.sch.id
# Expected: Redirect to HTTPS
```

#### Success Criteria
- ✅ HTTPS working on main domain
- ✅ All HTTP traffic redirected to HTTPS
- ✅ No mixed content warnings
- ✅ SSL A+ rating on SSL Labs
- ✅ Session cookies marked as Secure
- ✅ Certificate auto-renews before expiration

#### Monitoring (Optional)
```bash
# Monitor certificate expiration
# Add to crontab to send alert 30 days before expiry
0 9 * * * certbot renew --quiet --renew-hook "systemctl reload apache2"
```

---

### **Task 5: Create Monitoring Dashboard**
**Priority:** 🟠 HIGH  
**Status:** ⬜ Not Started  
**Estimated Time:** 8-10 hours  
**Responsible:** Backend Developer / DevOps

#### Description
Buat monitoring dashboard untuk memantau health aplikasi, database, dan performance.

#### Subtasks
- [ ] Create admin monitoring page
- [ ] Add system metrics collection
- [ ] Display real-time statistics
- [ ] Setup alert thresholds
- [ ] Create health check endpoint
- [ ] Setup daily performance report

#### Option 1: Simple PHP-Based Monitoring (DIY)

**Step 1: Create Database Table for Metrics**
```sql
CREATE TABLE system_metrics (
  id INT PRIMARY KEY AUTO_INCREMENT,
  metric_name VARCHAR(100),
  metric_value DECIMAL(10, 2),
  recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_metric_time (metric_name, recorded_at)
);

CREATE TABLE system_health (
  id INT PRIMARY KEY AUTO_INCREMENT,
  status VARCHAR(20),              -- ok, warning, critical
  message TEXT,
  component VARCHAR(100),          -- database, disk, memory, etc
  recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Step 2: Create Monitoring Functions (includes/fungsi.php)**
```php
function get_database_stats($conn) {
    $stats = [
        'connected' => $conn->ping(),
        'tables_count' => 0,
        'database_size' => 0
    ];
    
    // Count tables
    $result = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables 
                          WHERE table_schema = DATABASE()");
    $stats['tables_count'] = $result->fetch_assoc()['count'];
    
    // Database size
    $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size
                          FROM information_schema.TABLES 
                          WHERE table_schema = DATABASE()");
    $stats['database_size'] = $result->fetch_assoc()['size'] . ' MB';
    
    return $stats;
}

function get_user_stats($conn) {
    return [
        'active_now' => $conn->query("SELECT COUNT(*) as count FROM siswa")->fetch_assoc()['count'],
        'logins_today' => $conn->query("SELECT COUNT(*) as count FROM log_login 
                                      WHERE DATE(login_time) = CURDATE()")->fetch_assoc()['count'],
        'failed_logins' => $conn->query("SELECT COUNT(*) as count FROM login_attempts 
                                       WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetch_assoc()['count']
    ];
}

function get_system_health($conn) {
    $health = ['status' => 'ok', 'issues' => []];
    
    // Check database connection
    if (!$conn->ping()) {
        $health['status'] = 'critical';
        $health['issues'][] = 'Database connection failed';
    }
    
    // Check disk space
    $free_space = disk_free_space(__DIR__);
    $total_space = disk_total_space(__DIR__);
    $usage_percent = ($total_space - $free_space) / $total_space * 100;
    
    if ($usage_percent > 90) {
        $health['status'] = 'critical';
        $health['issues'][] = 'Disk space critical: ' . round($usage_percent, 1) . '%';
    } elseif ($usage_percent > 75) {
        if ($health['status'] != 'critical') $health['status'] = 'warning';
        $health['issues'][] = 'Disk space warning: ' . round($usage_percent, 1) . '%';
    }
    
    // Check backup age
    $backup_dir = __DIR__ . '/../backups/database';
    if (is_dir($backup_dir)) {
        $files = array_filter(glob($backup_dir . '/*'), 'is_file');
        if (empty($files)) {
            $health['status'] = 'warning';
            $health['issues'][] = 'No backups found';
        } else {
            $latest_backup = max(array_map('filemtime', $files));
            $hours_since_backup = (time() - $latest_backup) / 3600;
            
            if ($hours_since_backup > 24) {
                $health['status'] = 'warning';
                $health['issues'][] = 'Last backup: ' . round($hours_since_backup, 1) . ' hours ago';
            }
        }
    }
    
    return $health;
}

function log_metric($conn, $metric_name, $metric_value) {
    $stmt = $conn->prepare(
        "INSERT INTO system_metrics (metric_name, metric_value) VALUES (?, ?)"
    );
    $stmt->bind_param("sd", $metric_name, $metric_value);
    return $stmt->execute();
}

function get_recent_metrics($conn, $metric_name, $hours = 24) {
    $stmt = $conn->prepare(
        "SELECT * FROM system_metrics 
         WHERE metric_name = ? AND recorded_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
         ORDER BY recorded_at DESC"
    );
    $stmt->bind_param("si", $metric_name, $hours);
    $stmt->execute();
    return $stmt->get_result();
}
```

**Step 3: Create Monitoring Admin Page**
```php
<?php
// File: admin/monitoring.php
include '../config.php';
cek_login([1]); // Admin only

$title = 'System Monitoring';
include '../includes/header.php';

// Get system status
$db_stats = get_database_stats($conn);
$user_stats = get_user_stats($conn);
$health = get_system_health($conn);

// Get recent metrics
$cpu_metrics = get_recent_metrics($conn, 'cpu_usage', 24);
$memory_metrics = get_recent_metrics($conn, 'memory_usage', 24);
?>

<div class="page-header">
    <h2 class="page-title">System Monitoring</h2>
    <p class="page-subtitle">Real-time system health and performance metrics</p>
</div>

<!-- Health Status Alert -->
<div class="alert alert-<?= $health['status'] == 'ok' ? 'success' : ($health['status'] == 'warning' ? 'warning' : 'danger') ?>">
    <strong>System Status: <?= strtoupper($health['status']) ?></strong>
    <?php if (!empty($health['issues'])): ?>
        <ul>
            <?php foreach ($health['issues'] as $issue): ?>
                <li><?= e($issue) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-database"></i></div>
        <div>
            <h3>Database</h3>
            <div class="stat-number"><?= $db_stats['database_size'] ?></div>
            <small>Tables: <?= $db_stats['tables_count'] ?></small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <h3>Logins Today</h3>
            <div class="stat-number"><?= $user_stats['logins_today'] ?></div>
            <small>Failed: <?= $user_stats['failed_logins'] ?></small>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <h3>Recent Login Activity</h3>
    <table class="modern-table">
        <thead>
            <tr>
                <th>User</th>
                <th>IP Address</th>
                <th>Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $logs = $conn->query("SELECT * FROM log_login ORDER BY login_time DESC LIMIT 10");
            while ($log = $logs->fetch_assoc()):
            ?>
            <tr>
                <td><?= e($log['nama_lengkap']) ?></td>
                <td><?= e($log['ip_address']) ?></td>
                <td><?= date('d M Y H:i', strtotime($log['login_time'])) ?></td>
                <td><span class="badge-hadir">Success</span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
```

#### Option 2: Use Third-Party Monitoring (Professional)

If budget available, consider:
- **DataDog** ($15-20/month)
- **New Relic** ($10-30/month)
- **Sentry** (free + pro plans)

#### Success Criteria
- ✅ Dashboard shows database status
- ✅ System health visible
- ✅ Login activity tracked
- ✅ Alerts on critical issues
- ✅ Performance metrics collected
- ✅ Reports generated daily

---

### **Task 6: Document Deployment Process**
**Priority:** 🟠 HIGH  
**Status:** ⬜ Not Started  
**Estimated Time:** 4-5 hours  
**Responsible:** DevOps / Tech Lead

#### Description
Buat comprehensive documentation untuk deployment, maintenance, dan troubleshooting.

#### Subtasks
- [ ] Create deployment checklist
- [ ] Write installation guide
- [ ] Document backup/restore procedures
- [ ] Create troubleshooting guide
- [ ] Setup runbook for common issues
- [ ] Document escalation procedures

#### Documentation Outline

**File 1: DEPLOYMENT_GUIDE.md**
```markdown
# Deployment Guide - LMS MTs Al-Ihsan

## Pre-Deployment Checklist
- [ ] Database backed up
- [ ] Code tested in staging
- [ ] SSL certificate installed
- [ ] Server resources available
- [ ] Admin team notified

## Deployment Steps
1. Pull latest code
2. Run database migrations
3. Update configuration
4. Clear cache
5. Run tests
6. Go live

## Rollback Procedure
[Steps to revert if something goes wrong]
```

**File 2: MAINTENANCE_GUIDE.md**
```markdown
# Maintenance Guide

## Daily Tasks
- [ ] Check system health dashboard
- [ ] Review login attempts
- [ ] Verify backup completion

## Weekly Tasks
- [ ] Review user reports
- [ ] Check disk space
- [ ] Database optimization

## Monthly Tasks
- [ ] Security audit
- [ ] Performance review
- [ ] User feedback analysis
```

**File 3: TROUBLESHOOTING.md**
```markdown
# Troubleshooting Guide

## Problem: "Database connection failed"
**Symptom:** White screen, database error
**Solution:**
1. SSH to server
2. Check MySQL: `systemctl status mysql`
3. Verify credentials in config.php
4. Check database size

## Problem: "File upload fails"
**Symptom:** Upload button doesn't work
**Solution:**
1. Check /uploads/ directory permissions
2. Verify disk space
3. Check upload_max_filesize in php.ini

[More issues...]
```

#### Store Documentation in Project
```
/documentation/
├── DEPLOYMENT_GUIDE.md
├── MAINTENANCE_GUIDE.md
├── TROUBLESHOOTING.md
├── ARCHITECTURE.md
├── API_REFERENCE.md
└── ADMIN_MANUAL.md
```

#### Success Criteria
- ✅ New admin can deploy without questions
- ✅ All common issues documented
- ✅ Runbooks exist for emergencies
- ✅ Backup/restore procedures clear
- ✅ Escalation paths defined

---

### **Task 7: Train Admin Team**
**Priority:** 🟡 MEDIUM  
**Status:** ⬜ Not Started  
**Estimated Time:** 6-8 hours  
**Responsible:** Project Manager / Tech Lead

#### Description
Memberikan pelatihan kepada admin/IT team tentang operasional dan maintenance sistem.

#### Subtasks
- [ ] Prepare training materials
- [ ] Conduct admin training (4-6 hours)
- [ ] Practice backup/restore
- [ ] Test emergency procedures
- [ ] Create user support documentation
- [ ] Setup support ticket system

#### Training Curriculum

**Module 1: Dashboard Overview (1 hour)**
- Login dan navigation
- Understanding dashboards
- Accessing reports
- Basic troubleshooting

**Module 2: User Management (1.5 hours)**
- Create/edit user accounts
- Reset passwords
- Manage roles and permissions
- Handle user lockouts

**Module 3: System Maintenance (2 hours)**
- Monitoring health
- Backup procedures
- Database maintenance
- Disk space management
- Log files review

**Module 4: Emergency Procedures (1.5 hours)**
- Backup recovery
- Dealing with outages
- Contacting support
- Escalation procedures

#### Training Materials Needed
- [ ] Presentation slides
- [ ] Hands-on lab environment
- [ ] Quick reference cards
- [ ] Video tutorials (optional)
- [ ] FAQ document

#### Success Criteria
- ✅ Admin team can perform daily tasks
- ✅ Can handle basic troubleshooting
- ✅ Know backup/restore procedures
- ✅ Understand escalation process
- ✅ Have reference materials

#### Post-Training Support
```markdown
## Support Contacts
- **Level 1 (Admin):** IT School Staff
- **Level 2 (Developer):** [Dev Contact]
- **Level 3 (Vendor):** [Vendor Contact]
- **Emergencies:** [Emergency Number]
```

---

## 📊 PROGRESS TRACKER

| Task | Priority | Time | Status | Owner | Due |
|------|----------|------|--------|-------|-----|
| 1. Backup DB | 🔴 CRITICAL | 4-6h | ⬜ | DevOps | Day 2 |
| 2. Rate Limiting | 🔴 CRITICAL | 6-8h | ⬜ | Backend | Day 3 |
| 3. File Validation | 🔴 CRITICAL | 3-4h | ⬜ | Backend | Day 4 |
| 4. SSL Certificate | 🟠 HIGH | 2-3h | ⬜ | DevOps | Day 5 |
| 5. Monitoring | 🟠 HIGH | 8-10h | ⬜ | Backend | Day 7 |
| 6. Documentation | 🟠 HIGH | 4-5h | ⬜ | Tech Lead | Day 10 |
| 7. Training | 🟡 MEDIUM | 6-8h | ⬜ | PM | Day 14 |

**Total Effort:** 40-60 hours  
**Timeline:** 2 weeks  
**Team Size:** 3-4 people

---

## 🎯 SUCCESS METRICS

By end of 2 weeks, system should have:

```
✅ Automated daily backups with 7-day retention
✅ Login brute force protection active
✅ File upload security validated
✅ HTTPS/SSL enabled on all pages
✅ Monitoring dashboard operational
✅ Deployment procedures documented
✅ Admin team trained and certified
✅ No critical vulnerabilities
✅ Zero data loss risks
✅ Ready for production launch
```

---

## 📞 ESCALATION MATRIX

| Issue | Owner | Backup | SLA |
|-------|-------|--------|-----|
| Database down | DevOps | Tech Lead | 1 hour |
| Security issue | Backend | Security Expert | 30 min |
| Performance degradation | DevOps | Backend | 2 hours |
| User access issue | Admin | Tech Lead | 1 hour |
| Backup failure | DevOps | Cloud Provider | 4 hours |

---

**Document Created:** May 29, 2026  
**Status:** Ready for Implementation  
**Last Updated:** -  
**Next Review:** Upon completion of all tasks

