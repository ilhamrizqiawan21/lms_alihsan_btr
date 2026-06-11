#!/bin/bash
# Quick Reference - Backup Commands

# ========================================
# QUICK START
# ========================================

# Make scripts executable (one-time)
chmod +x backup.sh restore.sh backup_config.sh

# Test backup
./backup.sh --dry-run

# Run backup
./backup.sh

# Run backup with details
./backup.sh --verbose


# ========================================
# VIEW BACKUPS
# ========================================

# List all backups
./restore.sh --list

# List backups with sizes
ls -lh backups/database/

# Find recent backups
find backups/database -name "*.sql.gz" -mtime -1

# Check total backup size
du -sh backups/

# Find backup by date
find backups/database -name "*20260530*"


# ========================================
# RESTORE (CAREFUL!)
# ========================================

# List available backups
./restore.sh --list

# Restore from backup
./restore.sh lms_backup_20260530_140000.sql.gz

# Restore from just date
./restore.sh 20260530_140000

# Restore from date only
./restore.sh 20260530


# ========================================
# MONITORING
# ========================================

# Check backup logs
tail -f backups/logs/backup.log

# Show last backup status
tail -20 backups/logs/backup.log

# Count successful backups
grep "completed successfully" backups/logs/backup.log | wc -l

# Check disk space
df -h backups/

# Check backup age
find backups/database -name "*.sql.gz" -type f -printf '%T+ %p\n' | sort -r | head -1

# Run health check
./backup_healthcheck.sh

# Send health check email
./backup_healthcheck.sh --email


# ========================================
# CRON SETUP (Linux)
# ========================================

# Edit crontab
crontab -e

# Backup daily at 2 AM
0 2 * * * /path/to/backup.sh >> /path/to/backups/logs/cron.log 2>&1

# Backup twice daily (2 AM & 2 PM)
0 2 * * * /path/to/backup.sh >> /path/to/backups/logs/cron.log 2>&1
0 14 * * * /path/to/backup.sh >> /path/to/backups/logs/cron.log 2>&1

# List cron jobs
crontab -l

# Remove cron job
crontab -e  # Edit and delete the line


# ========================================
# TROUBLESHOOTING
# ========================================

# Test MySQL connection
mysql -h localhost -u root -pHash2856@ -e "SELECT 1"

# Verify mysqldump exists
which mysqldump

# Check file permissions
ls -l backup.sh restore.sh

# Make executable
chmod +x backup.sh restore.sh

# Check backup file integrity
gzip -t backups/database/*.sql.gz

# Verify database after restore
mysql -u root -p lms_alihsan_btr -e "SELECT COUNT(*) FROM users;"


# ========================================
# ADVANCED
# ========================================

# Backup without compression
./backup.sh --no-compress

# Backup without cleanup
./backup.sh --no-cleanup

# Manual database backup
mysqldump -u root -pHash2856@ lms_alihsan_btr > backup_manual.sql

# Manual restore
mysql -u root -pHash2856@ lms_alihsan_btr < backup_manual.sql

# Compress manually
gzip -9 backup_manual.sql

# Decompress manually
gzip -d backup_manual.sql.gz

# Check MySQL status
systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql

# Check latest backup size
ls -lh backups/database/ | tail -1

# Delete specific old backup
rm backups/database/lms_backup_20260520_*.sql.gz

# Copy backup to external drive
cp backups/database/*.gz /mnt/external_backup/


# ========================================
# EMERGENCY RECOVERY
# ========================================

# STOP APPLICATION FIRST
sudo systemctl stop apache2
# or
sudo systemctl stop nginx

# Create new empty database
mysqladmin -u root -p create lms_alihsan_btr

# List available backups
./restore.sh --list

# Restore from backup (choose oldest good backup)
./restore.sh lms_backup_20260522_020000.sql.gz

# Verify restoration
mysql -u root -p lms_alihsan_btr -e "SELECT COUNT(*) FROM siswa;"

# RESTART APPLICATION
sudo systemctl start apache2
# or
sudo systemctl start nginx

# Verify application working
# Open browser and test login


# ========================================
# DATABASE MAINTENANCE
# ========================================

# Connect to MySQL
mysql -u root -p

# In MySQL:
USE lms_alihsan_btr;

# Count users
SELECT COUNT(*) FROM users;

# Count students
SELECT COUNT(*) FROM siswa;

# Check table status
SHOW TABLE STATUS;

# Repair tables (if corrupted)
REPAIR TABLE users, siswa, log_login;

# Check data integrity
CHECK TABLE users, siswa, log_login;

# Exit MySQL
EXIT;


# ========================================
# HELP & INFORMATION
# ========================================

# Show help for backup script
./backup.sh --help

# Show help for restore script
./restore.sh --help

# Read backup guide
cat BACKUP_GUIDE.md

# Open backup guide in editor
nano BACKUP_GUIDE.md

# Check configuration
cat backup_config.sh

# Edit configuration
nano backup_config.sh

