# 📚 BACKUP GUIDE - LMS MTs Al-Ihsan Batujajar

**Last Updated:** May 30, 2026  
**Version:** 1.0  
**Status:** Production Ready

---

## 📋 Daftar Isi

1. [Quick Start](#quick-start)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Backup Operations](#backup-operations)
5. [Restore Operations](#restore-operations)
6. [Cron Setup](#cron-setup)
7. [Monitoring](#monitoring)
8. [Troubleshooting](#troubleshooting)
9. [Recovery Procedures](#recovery-procedures)
10. [Best Practices](#best-practices)

---

## 🚀 Quick Start

### Manual Backup (Linux/Mac)
```bash
# Navigate to project directory
cd /path/to/lms_alihsan_btr

# Make scripts executable
chmod +x backup.sh restore.sh backup_config.sh

# Run backup
./backup.sh

# Verify backup
ls -lh backups/database/
```

### View Recent Backups
```bash
# List all backups
ls -lh backups/database/

# Show last 5 backups
ls -lh backups/database/ | tail -6

# Find backup by date
find backups/database -name "*20260530*"
```

### Restore from Backup (CAREFUL!)
```bash
# List available backups
./restore.sh --list

# Restore from specific backup
./restore.sh lms_backup_20260530_140000.sql.gz
```

---

## 🔧 Installation

### Prerequisites (Linux/Mac)
```bash
# Verify required tools are installed
which mysqldump    # MySQL backup tool
which mysql        # MySQL client
which gzip         # Compression
which mail         # For email notifications (optional)
```

### Step 1: Copy Script Files
```bash
# Scripts are in project root:
# - backup.sh
# - restore.sh
# - backup_config.sh
# - backups/              (directory)
```

### Step 2: Make Scripts Executable
```bash
chmod +x /path/to/lms_alihsan_btr/backup.sh
chmod +x /path/to/lms_alihsan_btr/restore.sh
chmod +x /path/to/lms_alihsan_btr/backup_config.sh

# Verify
ls -l backup.sh restore.sh backup_config.sh
```

### Step 3: Set Proper Permissions
```bash
# Backup directory should only be accessible by owner
chmod 700 backups/
chmod 700 backups/database/
chmod 700 backups/logs/

# Verify
ls -ld backups/ backups/database/ backups/logs/
# Should show: drwx------
```

### Step 4: Test Installation
```bash
# Run test backup (dry-run)
./backup.sh --dry-run

# Run actual test backup
./backup.sh --verbose
```

---

## ⚙️ Configuration

### File: `backup_config.sh`

**Database Connection**
```bash
DB_HOST="localhost"        # Database server
DB_USER="root"            # Database user
DB_PASS="Hash2856@"       # Database password
DB_NAME="lms_alihsan_btr" # Database name
DB_PORT="3306"            # MySQL port
```

**Backup Locations**
```bash
BACKUP_DIR="/path/to/backups/database"     # Backup storage
BACKUP_LOGS_DIR="/path/to/backups/logs"    # Log storage
BACKUP_PREFIX="lms_backup"                 # Filename prefix
```

**Retention Policy**
```bash
LOCAL_RETENTION_DAYS=7     # Keep 7 days of backups locally
```

**Compression**
```bash
ENABLE_COMPRESSION=true    # Compress backups
COMPRESSION_LEVEL=9        # Maximum compression (1-9)
```

**Notifications (Optional)**
```bash
NOTIFICATION_EMAIL=""      # Email for alerts (optional)
LOG_LEVEL="INFO"          # DEBUG, INFO, WARNING, ERROR
```

**Advanced Options**
```bash
LOCK_TABLES=true          # Lock tables during backup
SINGLE_TRANSACTION=true   # Consistent snapshot
QUICK_BACKUP=true         # Fast backup mode
VERBOSE=false             # Detailed logging
BACKUP_UPLOADS=false      # Also backup /uploads/ folder
```

### Customizing Configuration

**Edit Configuration File**
```bash
nano backup_config.sh
```

**Example: Change Retention to 30 Days**
```bash
# Find line:
LOCAL_RETENTION_DAYS=7

# Change to:
LOCAL_RETENTION_DAYS=30
```

**Example: Enable Email Notifications**
```bash
# Find line:
NOTIFICATION_EMAIL=""

# Change to:
NOTIFICATION_EMAIL="admin@mtsalihsan.sch.id"

# Requires: `mail` command installed
sudo apt-get install mailutils
```

**Example: Disable Compression**
```bash
# For faster backups (uses more disk space):
ENABLE_COMPRESSION=false
```

---

## 💾 Backup Operations

### Manual Backup

**Basic Backup**
```bash
./backup.sh
```

Output:
```
[2026-05-30 14:00:00] [INFO] Starting LMS Backup Process
[2026-05-30 14:00:00] [INFO] Database connection verified
[2026-05-30 14:00:01] [INFO] Starting database backup...
[2026-05-30 14:00:05] [INFO] Database backup created successfully: 12M
[2026-05-30 14:00:08] [INFO] Backup compressed: 4.2M (35%)
[2026-05-30 14:00:08] [INFO] Backup process completed successfully!
```

**Verbose Backup (Detailed Output)**
```bash
./backup.sh --verbose
```

**Dry-Run Test (No Actual Backup)**
```bash
./backup.sh --dry-run
```

**Backup Without Compression**
```bash
./backup.sh --no-compress
```

**Backup Without Cleanup**
```bash
./backup.sh --no-cleanup
```

**Backup with Multiple Options**
```bash
./backup.sh --verbose --no-compress --no-cleanup
```

### Backup File Details

**Naming Convention**
```
lms_backup_YYYYMMDD_HHMMSS.sql.gz

Example: lms_backup_20260530_140000.sql.gz
```

**Storage Location**
```
backups/database/lms_backup_*.sql*
backups/logs/backup.log
```

**File Size**
```bash
# Check backup file size
ls -lh backups/database/

# Example output:
# -rw-r--r-- 1 root root 4.2M May 30 14:00 lms_backup_20260530_140000.sql.gz
```

### Viewing Backup Logs

**Live Backup Log**
```bash
tail -f backups/logs/backup.log
```

**Full Log History**
```bash
cat backups/logs/backup.log
```

**Check Last Backup**
```bash
# Find most recent backup
ls -lt backups/database/ | head -1

# Check backup log
grep "SUCCESS\|FAILED" backups/logs/backup.log | tail -1
```

---

## 🔄 Restore Operations

### List Available Backups

**Show All Backups**
```bash
./restore.sh --list
```

Output:
```
Available backup files:
=======================
-rw-r--r-- 1 root root 4.2M May 30 14:00 lms_backup_20260530_140000.sql.gz
-rw-r--r-- 1 root root 4.1M May 29 14:00 lms_backup_20260529_140000.sql.gz
-rw-r--r-- 1 root root 4.0M May 28 14:00 lms_backup_20260528_140000.sql.gz
```

### Restore Procedure

⚠️ **WARNING: RESTORE akan MENGHAPUS database yang ada sebelumnya!**

**Step 1: Verify Backup**
```bash
./restore.sh --list

# Choose the backup you want to restore
```

**Step 2: Start Restore**
```bash
# Full filename with extension
./restore.sh lms_backup_20260530_140000.sql.gz

# Or just filename without directory
./restore.sh lms_backup_20260530_140000

# Or absolute path
./restore.sh /path/to/backups/database/lms_backup_20260530_140000.sql.gz
```

**Step 3: Confirm Operation**
```
Decompressing backup file...
Creating backup of current database before restore...
Pre-restore backup created: backups/database/pre_restore_20260530_150000.sql.gz
==========================================
Starting Database Restore
==========================================
Backup file: lms_backup_20260530_140000.sql.gz
Database: lms_alihsan_btr@localhost

⚠️  WARNING: This will DELETE and RESTORE the database. Continue? (yes/no): yes
```

**Step 4: Wait for Restoration**
```
[INFO] Dropping existing database...
[INFO] Database dropped
[INFO] Creating new database...
[INFO] Restoring from backup file...
[INFO] Database restore completed successfully!
[INFO] Verifying restored database...
[INFO] Restore verification: PASSED (45 tables found)
[INFO] ==========================================
[INFO] Database Restore Successful!
[INFO] ==========================================
```

### Restore Safety Features

**Automatic Pre-Restore Backup**
```bash
# Before restoring, the current database is backed up to:
backups/database/pre_restore_20260530_150000.sql.gz

# This allows you to revert if needed
```

**Verification Steps**
```bash
# After restore completes:
# 1. Database connection verified
# 2. Backup file integrity checked
# 3. Decompression verified (for .gz files)
# 4. Pre-restore safety backup created
# 5. Database structures verified after restore
```

### Restore from Partial Filename

**Flexible Matching**
```bash
# All these work:
./restore.sh lms_backup_20260530_140000.sql.gz    # Full
./restore.sh lms_backup_20260530_140000.sql       # Without .gz
./restore.sh lms_backup_20260530_140000           # Without .sql.gz
./restore.sh 20260530_140000                      # Just timestamp
./restore.sh 20260530                             # Just date
```

---

## ⏰ Cron Setup (Automated Backup)

### Linux Cron Configuration

**Edit Crontab**
```bash
# Open crontab editor
crontab -e

# Choose your editor (nano, vim, etc.)
```

**Backup Every Day at 2 AM**
```bash
0 2 * * * /path/to/lms_alihsan_btr/backup.sh >> /path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

**Backup Twice Daily (2 AM & 2 PM)**
```bash
0 2 * * * /path/to/lms_alihsan_btr/backup.sh >> /path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
0 14 * * * /path/to/lms_alihsan_btr/backup.sh >> /path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

**Backup Every 6 Hours**
```bash
0 */6 * * * /path/to/lms_alihsan_btr/backup.sh >> /path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

**Backup Every Hour**
```bash
0 * * * * /path/to/lms_alihsan_btr/backup.sh >> /path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

### Verify Cron Job

**List Active Cron Jobs**
```bash
crontab -l
```

**Check Cron Logs**
```bash
# View cron execution logs
tail -f /path/to/lms_alihsan_btr/backups/logs/cron.log

# Or system cron logs
grep CRON /var/log/syslog
```

### Remove Cron Job

**Edit Crontab**
```bash
crontab -e
```

**Delete the line with backup.sh and save**

### Cron Troubleshooting

**Cron Job Not Running?**

1. Check if cron service is running
```bash
systemctl status cron
# or
service cron status
```

2. Verify crontab syntax
```bash
# Check for syntax errors
crontab -l
```

3. Check file permissions
```bash
# Scripts must be executable
ls -l backup.sh restore.sh
# Should show: -rwxr-xr-x
```

4. Verify script path
```bash
# Use absolute paths in crontab
which backup.sh  # Get full path
```

---

## 📊 Monitoring

### Check Backup Health

**Backup Statistics**
```bash
# Count backups
ls -1 backups/database/ | wc -l

# Total backup size
du -sh backups/database/

# Largest backups
ls -lh backups/database/ | sort -k5 -h | tail -5
```

**Recent Backup Status**
```bash
# Show last 3 backups with sizes
ls -lh backups/database/ | tail -3

# Check backup age
find backups/database -name "*.sql.gz" -type f -printf '%T+ %p\n' | sort -r | head -1
```

**Backup Log Summary**
```bash
# Count successful backups
grep "completed successfully" backups/logs/backup.log | wc -l

# Count failed backups
grep "FAILED" backups/logs/backup.log | wc -l

# Show last backup status
tail -20 backups/logs/backup.log
```

### Disk Space Monitoring

**Check Available Disk Space**
```bash
# Overall disk usage
df -h /

# Backup directory size
du -sh backups/

# Database size
du -sh /var/lib/mysql/lms_alihsan_btr/
```

**Alert if Low Space**
```bash
# Add to crontab to check daily at 8 AM
0 8 * * * [ $(df / | tail -1 | awk '{print $5}' | cut -d% -f1) -gt 85 ] && \
  echo "WARNING: Disk usage > 85%" | mail -s "Disk Space Alert" admin@mtsalihsan.sch.id
```

### Database Integrity Check

**After Restore**
```bash
# Connect to MySQL
mysql -u root -p

# In MySQL:
USE lms_alihsan_btr;

# Count tables
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'lms_alihsan_btr';

# Expected: 18+ tables

# Check data
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM siswa;
SELECT COUNT(*) FROM log_login;
```

---

## 🔍 Troubleshooting

### Problem: "mysqldump: command not found"

**Cause:** MySQL utilities not installed

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install mysql-client

# macOS
brew install mysql-client

# Verify
mysqldump --version
```

### Problem: "Permission denied" on backup.sh

**Cause:** Script not executable

**Solution:**
```bash
chmod +x backup.sh restore.sh backup_config.sh
ls -l backup.sh  # Should show: -rwxr-xr-x
```

### Problem: "Access denied for user 'root'@'localhost'"

**Cause:** Wrong database credentials in backup_config.sh

**Solution:**
```bash
# Test MySQL connection
mysql -h localhost -u root -pHash2856@ -e "SELECT 1"

# Update backup_config.sh if needed
nano backup_config.sh
# Edit: DB_USER, DB_PASS, DB_HOST
```

### Problem: "Not enough disk space"

**Cause:** Backup directory full

**Solution:**
```bash
# Check disk space
df -h backups/

# Delete old backups manually
rm backups/database/lms_backup_20260520_*.sql.gz

# Or increase retention cleanup
# Edit backup_config.sh and change:
LOCAL_RETENTION_DAYS=3  # Keep only 3 days
```

### Problem: Backup Taking Too Long

**Cause:** Database large or server slow

**Solution:**
```bash
# Run with verbose to see progress
./backup.sh --verbose

# Speed up by disabling compression
./backup.sh --no-compress

# Or reduce retention to cleanup old backups
```

### Problem: "Backup file corrupted"

**Cause:** Incomplete backup or disk write error

**Solution:**
```bash
# Check file integrity
gzip -t backups/database/lms_backup_20260530_140000.sql.gz

# If corrupted, delete and re-run backup
rm backups/database/lms_backup_20260530_140000.sql.gz
./backup.sh

# Restore from older backup
./restore.sh --list
```

---

## 🆘 Recovery Procedures

### Scenario 1: Complete Database Loss

**Symptoms:** Database deleted or completely inaccessible

**Recovery Steps:**
```bash
# 1. Stop application
systemctl stop apache2  # or nginx

# 2. Create new empty database
mysqladmin -u root -p create lms_alihsan_btr

# 3. Restore from latest backup
./restore.sh --list
./restore.sh lms_backup_20260530_140000.sql.gz

# 4. Verify restoration
mysql -u root -p lms_alihsan_btr -e "SELECT COUNT(*) FROM users;"

# 5. Restart application
systemctl start apache2

# 6. Test application
# Open browser and test login
```

### Scenario 2: Data Corruption

**Symptoms:** Incorrect data or referential integrity errors

**Recovery Steps:**
```bash
# 1. Identify when corruption started
# Check last known good backup date

# 2. Stop application to prevent further changes
systemctl stop apache2

# 3. List available backups from before corruption
./restore.sh --list | grep "2026052[0-2]"

# 4. Restore from known good backup
./restore.sh lms_backup_20260522_140000.sql.gz

# 5. Verify data integrity
mysql -u root -p lms_alihsan_btr -e "CHECK TABLE users, siswa, log_login;"

# 6. Restart application
systemctl start apache2
```

### Scenario 3: Accidental Data Modification

**Symptoms:** User accidentally modified important data

**Recovery Steps:**
```bash
# 1. Determine when change happened

# 2. Check if pre-restore backup exists
# (Automatically created before each restore)
ls -lh backups/database/pre_restore_*.sql.gz | tail -1

# 3. If available, restore from pre-restore backup
./restore.sh pre_restore_20260530_150000.sql.gz

# 4. Alternatively, restore from daily backup
./restore.sh lms_backup_20260530_020000.sql.gz

# 5. Verify
mysql -u root -p lms_alihsan_btr -e "SELECT * FROM users WHERE id=1;"
```

### Scenario 4: Disk Failure (Server Recovery)

**Symptoms:** Can't access backups because server failed

**Recovery on New Server:**

```bash
# 1. Restore server from system backup

# 2. Copy backup files from external storage
# (Should have backup copies on USB/external drive)
cp /mnt/external_drive/backups/* /var/www/html/lms_alihsan_btr/backups/

# 3. Verify backup files integrity
gzip -t backups/database/*.sql.gz

# 4. Restore database
./restore.sh lms_backup_20260530_140000.sql.gz

# 5. Restore application files (if lost)
# From version control or external backup
```

### Scenario 5: Ransomware/Security Breach

**Symptoms:** Files encrypted or suspicious activity

**Recovery Steps:**
```bash
# 1. ISOLATE SERVER
# Disconnect from network immediately

# 2. Boot from clean media if needed

# 3. Identify scope of compromise
# Check which files were modified

# 4. If only database affected, restore from backup
./restore.sh lms_backup_20260529_020000.sql.gz  # Before breach

# 5. If files affected, restore application files
# From version control:
git log --oneline | head -10  # Find last good commit
git checkout <commit_hash>

# 6. Change all credentials
# Database: ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
# Server: passwd
# Application: Update config.php

# 7. Full security audit before reconnecting to network
```

---

## ✅ Best Practices

### 1. Regular Testing

**Test Restore Monthly**
```bash
# First Sunday of each month at 10 PM
0 22 1 * 0 /path/to/restore_test.sh

# Test script:
#!/bin/bash
echo "Testing restore procedure..."
/path/to/lms_alihsan_btr/restore.sh lms_backup_$(date -d '7 days ago' +%Y%m%d)_140000.sql.gz
```

### 2. Off-Site Backup

**Copy Backups to External Storage Weekly**
```bash
# Weekly backup to USB drive
0 0 * * 0 cp /path/to/backups/database/*.gz /mnt/external_backup/

# Or to cloud storage (AWS S3)
0 3 * * * aws s3 cp /path/to/backups/database/ s3://lms-backups/ --recursive
```

### 3. Backup Verification

**Automated Integrity Check**
```bash
# Daily backup verification
0 3 * * * gzip -t /path/to/backups/database/*.gz 2>&1 | mail -s "Backup Integrity Report" admin@mtsalihsan.sch.id
```

### 4. Documentation

**Keep Records**
```
Maintain log of:
- Backup schedules
- Restore tests conducted
- Issues encountered and solutions
- Configuration changes
```

### 5. Notifications

**Email Alerts Setup**
```bash
# Edit backup_config.sh
NOTIFICATION_EMAIL="admin@mtsalihsan.sch.id"

# Install mail utility
sudo apt-get install mailutils

# Backups will now email on success/failure
```

### 6. Retention Policy

**Delete Old Backups Automatically**
```bash
# Currently set to 7 days in backup_config.sh
# Adjust LOCAL_RETENTION_DAYS as needed

# View what will be deleted
./backup.sh --dry-run

# Clean up manually if needed
find backups/database -name "*.sql.gz" -mtime +30 -delete
```

### 7. Monitoring Checklist

**Daily**
- [ ] Check backup completed successfully
- [ ] Review backup logs for errors
- [ ] Verify disk space available

**Weekly**
- [ ] Test restore procedure
- [ ] Check backup file integrity
- [ ] Review total backup size

**Monthly**
- [ ] Full restore test to separate database
- [ ] Verify data after restore
- [ ] Update backup documentation
- [ ] Review retention policy

**Quarterly**
- [ ] Security audit of backup location
- [ ] Test disaster recovery plan
- [ ] Review and update recovery procedures

---

## 📞 Support & Escalation

### Common Issues

| Issue | Contact | SLA |
|-------|---------|-----|
| Backup failed | IT Admin | 1 hour |
| Restore needed | Tech Lead + IT Admin | 30 min |
| Disk space critical | DevOps | 1 hour |
| Data corruption | All hands | URGENT |

### Recovery SLA

| Scenario | RPO | RTO |
|----------|-----|-----|
| Single file corruption | 1 day | 1 hour |
| Database corruption | 1 day | 2 hours |
| Complete server failure | 1 day | 4 hours |
| Ransomware attack | 1 week | 8 hours |

---

## 📄 Backup Log Samples

### Successful Backup
```
[2026-05-30 02:00:00] [INFO] ===========================================
[2026-05-30 02:00:00] [INFO] Starting LMS Backup Process
[2026-05-30 02:00:00] [INFO] ===========================================
[2026-05-30 02:00:00] [INFO] Configuration validation passed!
[2026-05-30 02:00:01] [INFO] Database connection verified successfully
[2026-05-30 02:00:02] [INFO] Available disk space: 500000KB
[2026-05-30 02:00:02] [INFO] Starting database backup...
[2026-05-30 02:00:05] [INFO] Database backup created successfully: 12M
[2026-05-30 02:00:08] [INFO] Compressing backup file...
[2026-05-30 02:00:12] [INFO] Backup compressed: 4.2M (35%)
[2026-05-30 02:00:13] [INFO] Verifying backup integrity...
[2026-05-30 02:00:13] [INFO] Backup verified: lms_backup_20260530_020000.sql.gz (4.2M)
[2026-05-30 02:00:13] [INFO] Gzip integrity check: PASSED
[2026-05-30 02:00:13] [INFO] Cleaning up old backups (retention: 7 days)...
[2026-05-30 02:00:13] [INFO] No old backups to delete
[2026-05-30 02:00:13] [INFO] ===========================================
[2026-05-30 02:00:13] [INFO] Backup process completed successfully!
[2026-05-30 02:00:13] [INFO] ===========================================
```

### Failed Backup
```
[2026-05-30 02:00:00] [ERROR] Failed to connect to database!
[2026-05-30 02:00:00] [ERROR] Host: localhost, User: root, Database: lms_alihsan_btr
[2026-05-30 02:00:00] [ERROR] Configuration validation failed with 1 error(s)!
```

---

## 📚 Related Documents

- [PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md) - Full project overview
- [IMMEDIATE_TODOS.md](IMMEDIATE_TODOS.md) - Setup checklist
- [../config.php](../config.php) - Database credentials

---

**Document Status:** Complete & Ready for Production  
**Last Updated:** May 30, 2026  
**Next Review:** June 30, 2026

