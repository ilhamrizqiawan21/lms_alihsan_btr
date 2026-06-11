# 🚀 BACKUP SETUP GUIDE - Installation & Configuration

**Project:** LMS MTs Al-Ihsan Batujajar  
**Date:** May 30, 2026  
**Estimated Time:** 15-30 minutes

---

## 📋 Checklist

- [ ] **Step 1:** Verify Prerequisites
- [ ] **Step 2:** Set File Permissions
- [ ] **Step 3:** Review Configuration
- [ ] **Step 4:** Test Backup
- [ ] **Step 5:** Setup Cron Jobs (Linux/Mac)
- [ ] **Step 6:** Setup Task Scheduler (Windows)
- [ ] **Step 7:** Verify Automation
- [ ] **Step 8:** Document Setup

---

## 🔍 Step 1: Verify Prerequisites

### Linux/Mac Users

**Check for required tools:**
```bash
# Navigate to project
cd /path/to/lms_alihsan_btr

# Check MySQL tools
which mysqldump     # Should return: /usr/bin/mysqldump
which mysql         # Should return: /usr/bin/mysql
which gzip          # Should return: /bin/gzip
which bash          # Should return: /bin/bash

# If any are missing, install:
# Ubuntu/Debian:
sudo apt-get update
sudo apt-get install mysql-client gzip

# macOS:
brew install mysql@5.7
brew install gzip
```

**Verify MySQL connection:**
```bash
# Test connection to database
mysql -h localhost -u root -pHash2856@ -e "SELECT 1;"

# If successful, output: 1

# If failed, check:
# 1. MySQL service is running: systemctl status mysql
# 2. Credentials in config.php match backup_config.sh
# 3. Database exists: mysql -u root -p -e "SHOW DATABASES;"
```

### Windows Users

**Check for required tools:**
```batch
# Open Command Prompt or PowerShell

# Check MySQL
where mysqldump     # Should show MySQL path

# If not found, MySQL bin directory not in PATH:
# 1. Find MySQL installation (usually C:\Program Files\MySQL\...)
# 2. Add to system PATH (see Step 3 below)
```

**Add MySQL to PATH (Windows):**
```
1. Press Win+X, select "System"
2. Click "Advanced system settings"
3. Click "Environment Variables"
4. Under System Variables, select "Path" and click Edit
5. Click "New" and add: C:\Program Files\MySQL\MySQL Server 8.0\bin
6. Click OK, OK, OK
7. Restart Command Prompt
8. Verify: where mysqldump
```

---

## 🔐 Step 2: Set File Permissions

### Linux/Mac

**Make scripts executable:**
```bash
cd /path/to/lms_alihsan_btr

# Make all backup scripts executable
chmod +x backup.sh
chmod +x restore.sh
chmod +x backup_config.sh
chmod +x backup_healthcheck.sh

# Verify permissions
ls -l backup*.sh restore.sh
# Should show: -rwxr-xr-x (755 permissions)
```

**Set backup directory permissions:**
```bash
# Make backup directory secure
chmod 700 backups/
chmod 700 backups/database/
chmod 700 backups/logs/

# Verify
ls -ld backups/ backups/database/ backups/logs/
# Should show: drwx------ (700 permissions - owner only)
```

**Optional: Set script ownership to web server user**
```bash
# If running via web server (e.g., Apache)
sudo chown www-data:www-data backup*.sh restore.sh
sudo chown www-data:www-data backups/ -R

# Verify
ls -l backup*.sh
# Should show: www-data www-data
```

### Windows

File permissions usually handled automatically. Just ensure:
- Scripts are in project directory
- backups/ directory is writable
- User running backup has write access

---

## ⚙️ Step 3: Review Configuration

### Edit backup_config.sh (or backup.bat for Windows)

**Open configuration file:**
```bash
nano backup_config.sh
# or
vim backup_config.sh
# or
gedit backup_config.sh
```

**Review and verify settings:**

```bash
# Database credentials (should match config.php)
DB_HOST="localhost"
DB_USER="root"
DB_PASS="Hash2856@"
DB_NAME="lms_alihsan_btr"

# Backup location (should exist)
BACKUP_DIR="/full/path/to/lms_alihsan_btr/backups/database"

# Retention days (how long to keep backups)
LOCAL_RETENTION_DAYS=7

# Compression (recommended: true)
ENABLE_COMPRESSION=true

# Email notifications (optional)
NOTIFICATION_EMAIL=""
```

**For Windows (backup.bat):**
```batch
REM Edit configuration section at top of backup.bat:

set "DB_HOST=localhost"
set "DB_USER=root"
set "DB_PASS=Hash2856@"
set "DB_NAME=lms_alihsan_btr"

REM Backup directory
set "BACKUP_DIR=%SCRIPT_DIR%backups\database"
```

**Common Changes:**

Change retention to 30 days:
```bash
LOCAL_RETENTION_DAYS=30
```

Enable email notifications:
```bash
NOTIFICATION_EMAIL="admin@mtsalihsan.sch.id"

# Requires mail utility installed:
sudo apt-get install mailutils
```

Disable compression (faster, uses more disk):
```bash
ENABLE_COMPRESSION=false
```

---

## ✅ Step 4: Test Backup

### Dry-Run Test (No Actual Backup)

**Linux/Mac:**
```bash
./backup.sh --dry-run
```

Expected output:
```
[INFO] Starting LMS Backup Process
[INFO] Configuration validation passed!
[INFO] Database connection verified
[INFO] Available disk space: 500000KB
[INFO] [DRY-RUN] Would create backup: backups/database/lms_backup_20260530_020000.sql.gz
[INFO] Backup process completed successfully!
```

**Windows:**
```batch
backup.bat --test
```

### Actual Test Backup

**Linux/Mac:**
```bash
# Run actual backup with verbose output
./backup.sh --verbose

# Watch the process
tail -f backups/logs/backup.log
```

**Windows:**
```batch
backup.bat --verbose
```

### Verify Backup Was Created

**Check backup file:**
```bash
# List backup files
ls -lh backups/database/

# Should show:
# -rw-r--r-- 1 root root 4.2M May 30 02:00 lms_backup_20260530_020000.sql.gz

# Check file size (should be > 1MB for production database)
du -h backups/database/lms_backup_*.sql.gz
```

**Verify backup integrity:**
```bash
# For compressed backups
gzip -t backups/database/*.sql.gz

# Should complete without errors
```

### Check Backup Log

```bash
# View backup log
cat backups/logs/backup.log

# Or watch in real-time
tail -f backups/logs/backup.log
```

---

## ⏰ Step 5: Setup Automated Backups (Linux/Mac)

### Configure Cron Jobs

**Edit crontab:**
```bash
crontab -e

# Choose editor (usually nano)
```

**Add backup schedules:**

**Option A: Daily at 2 AM (Recommended)**
```bash
0 2 * * * /full/path/to/lms_alihsan_btr/backup.sh >> /full/path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

**Option B: Twice Daily (2 AM & 2 PM)**
```bash
0 2 * * * /full/path/to/lms_alihsan_btr/backup.sh >> /full/path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
0 14 * * * /full/path/to/lms_alihsan_btr/backup.sh >> /full/path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

**Option C: Every 6 Hours**
```bash
0 */6 * * * /full/path/to/lms_alihsan_btr/backup.sh >> /full/path/to/lms_alihsan_btr/backups/logs/cron.log 2>&1
```

**Get full path if unsure:**
```bash
cd /path/to/lms_alihsan_btr
pwd

# Output: /home/user/public_html/lms_alihsan_btr
# So use: /home/user/public_html/lms_alihsan_btr/backup.sh
```

### Verify Cron Job

**List cron jobs:**
```bash
crontab -l

# Should show the backup job you added
```

**Check if cron is enabled:**
```bash
systemctl status cron
# or
service cron status

# Should show: active (running)
```

**Monitor cron execution:**
```bash
# Watch for cron messages
tail -f /var/log/syslog | grep CRON

# or view cron logs
sudo journalctl -u cron
```

---

## 📅 Step 6: Setup Automated Backups (Windows)

### Configure Task Scheduler

**Method 1: GUI (Graphical Interface)**

1. Open Task Scheduler (Win+R, type `taskschd.msc`, press Enter)
2. Click "Create Basic Task" in right panel
3. Name: "LMS Database Backup"
4. Description: "Automated daily backup of LMS database"
5. Click Next, select "Daily"
6. Set time: 2:00 AM
7. Click Next, select "Start a program"
8. Program: `C:\path\to\backup.bat`
9. Start in: `C:\path\to\lms_alihsan_btr\`
10. Click Next, Finish

**Method 2: Command Line (PowerShell as Administrator)**

```powershell
# Run PowerShell as Administrator

# Create scheduled task
$action = New-ScheduledTaskAction -Execute "C:\path\to\backup.bat" -WorkingDirectory "C:\path\to\lms_alihsan_btr\"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "LMS Database Backup" -Description "Daily LMS backup"

# List tasks
Get-ScheduledTask | findstr LMS

# Run task manually to test
Start-ScheduledTask -TaskName "LMS Database Backup"

# View task details
Get-ScheduledTask -TaskName "LMS Database Backup" | Get-ScheduledTaskInfo
```

### Verify Task Scheduler

1. Open Task Scheduler
2. Navigate to: Task Scheduler Library > Scheduled Tasks
3. Find "LMS Database Backup"
4. Check "Last Run Result" (should be 0 = success)
5. Check "Last Run Time"

---

## 🧪 Step 7: Verify Automation

### Test Automated Backup

**Linux/Mac:**
```bash
# Check that backup runs automatically

# If scheduled for 2 AM, wait until then, then check:
ls -lh backups/database/

# Or manually trigger cron
sudo run-parts /etc/cron.daily

# Check logs
tail backups/logs/cron.log
```

**Windows:**
```batch
# In Task Scheduler, right-click job > Run
# Watch for backup completion

# Check backups folder
dir backups\database\

# Verify backup log
type backups\logs\backup.log
```

### Monitor Ongoing Backups

**Check backup health:**
```bash
# Run health check
./backup_healthcheck.sh

# Or with email
./backup_healthcheck.sh --email
```

**View recent backups:**
```bash
# List last 5 backups
ls -lt backups/database/ | head -6

# Show backup age
find backups/database -name "*.sql.gz" -type f -printf '%T@ %p\n' | sort -r | head -1
```

---

## 📝 Step 8: Document Setup

### Create Setup Record

Create file: `BACKUP_SETUP_RECORD.txt`

```
LMS BACKUP SYSTEM SETUP RECORD
================================

Date Setup: May 30, 2026
Administrator: [Your Name]
Server: [Server Name/IP]

CONFIGURATION
=============
Database Host: localhost
Database Name: lms_alihsan_btr
Database User: root
Backup Location: /path/to/backups/database
Retention Days: 7
Compression: Enabled

AUTOMATION
==========
Cron Job: 0 2 * * * /path/to/backup.sh >> /path/to/backups/logs/cron.log 2>&1
Scheduled Time: Daily at 2:00 AM
Windows Task: LMS Database Backup

TESTING
=======
Test Backup Date: May 30, 2026
Test Backup Size: 4.2MB
Restore Test Date: [To be scheduled monthly]
Restore Test Result: [Pending]

CONTACTS
========
Primary Admin: [Email/Phone]
Backup Owner: [Email/Phone]
Escalation: [Senior Admin]

NOTES
=====
- Backups retained for 7 days locally
- Off-site backup copies stored on: [Location]
- Monthly restore test scheduled for: [Date]
```

### Create Operation Checklist

Create file: `BACKUP_OPERATIONS_CHECKLIST.md`

```markdown
# LMS Backup Operations Checklist

## Daily
- [ ] Check backup completed successfully
- [ ] Verify backup file size > 1MB
- [ ] No errors in logs

## Weekly
- [ ] Test restore procedure
- [ ] Verify backup file integrity
- [ ] Check total backup size

## Monthly
- [ ] Full restore test to separate database
- [ ] Verify data after restore
- [ ] Update documentation
- [ ] Off-site backup copy made

## Quarterly
- [ ] Security audit of backup location
- [ ] Review retention policy
- [ ] Update disaster recovery plan
- [ ] Review contact information
```

---

## 📞 Quick Support

### Verify Installation

**Run all checks at once:**
```bash
./backup_healthcheck.sh
```

This will verify:
- ✅ Backup directory exists and is writable
- ✅ Backup files present
- ✅ Recent backup age
- ✅ Disk space available
- ✅ Database connection
- ✅ Backup logs
- ✅ Cron job configured

### Common Setup Issues

**Issue: "mysqldump: command not found"**
- Solution: Install MySQL client tools (see Step 1)

**Issue: "Permission denied" running backup.sh**
- Solution: `chmod +x backup.sh` (see Step 2)

**Issue: "Database connection failed"**
- Solution: Verify credentials in backup_config.sh match config.php

**Issue: "Not enough disk space"**
- Solution: Check disk space and adjust retention days

**Issue: Cron job not running**
- Solution: Verify cron is enabled: `systemctl status cron`

---

## ✨ Setup Complete!

After completing all steps, your backup system is ready:

```
✅ Scripts installed and tested
✅ Configuration verified
✅ Manual backup working
✅ Automated backups scheduled
✅ Health checks passing
✅ Documentation created
```

**Next Steps:**
1. Add to your runbook procedures
2. Train IT staff on restore procedures
3. Schedule monthly restore tests
4. Monitor logs regularly

---

**Setup Date:** May 30, 2026  
**Status:** ✅ Ready for Production  
**Review Date:** June 30, 2026

