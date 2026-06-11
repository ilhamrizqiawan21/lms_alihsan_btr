#!/bin/bash
################################################################################
# BACKUP CONFIGURATION - LMS MTs Al-Ihsan Batujajar
# 
# File konfigurasi untuk automated database backup
# JANGAN UBAH TANPA DOKUMENTASI
################################################################################

# ============================================
# DATABASE CONFIGURATION
# ============================================
# Credentials diambil dari config.php
DB_HOST="localhost"
DB_USER="root"
DB_PASS="Hash2856@"
DB_NAME="lms_alihsan_btr"
DB_PORT="3306"

# ============================================
# BACKUP CONFIGURATION
# ============================================
# Lokasi penyimpanan backup
BACKUP_BASE_DIR="$(dirname "$0")/backups"
BACKUP_DIR="${BACKUP_BASE_DIR}/database"
BACKUP_LOGS_DIR="${BACKUP_BASE_DIR}/logs"

# Naming convention untuk backup files
# Format: lms_backup_YYYYMMDD_HHMMSS.sql.gz
BACKUP_PREFIX="lms_backup"
BACKUP_TIMESTAMP_FORMAT="%Y%m%d_%H%M%S"

# ============================================
# RETENTION POLICY
# ============================================
# Berapa hari backup disimpan di local storage
LOCAL_RETENTION_DAYS=7

# Backup akan di-gzip untuk menghemat space
ENABLE_COMPRESSION=true
COMPRESSION_LEVEL=9  # 1-9, semakin tinggi semakin compress (tapi slower)

# ============================================
# NOTIFICATION SETTINGS
# ============================================
# Email untuk notifikasi backup (optional)
# Kosongkan jika tidak ingin email notification
NOTIFICATION_EMAIL=""

# Log level: DEBUG, INFO, WARNING, ERROR
LOG_LEVEL="INFO"

# ============================================
# ADVANCED OPTIONS
# ============================================
# Backup dengan lock tables
LOCK_TABLES=true

# Backup dengan single transaction (konsisten)
SINGLE_TRANSACTION=true

# Quick options untuk performa
QUICK_BACKUP=true

# Verbose logging
VERBOSE=false

# Backup uploads folder juga? (optional)
BACKUP_UPLOADS=false
UPLOADS_DIR="$(dirname "$0")/uploads"

################################################################################
# FUNCTION: Print configuration
################################################################################
print_config() {
    echo "=========================================="
    echo "BACKUP CONFIGURATION SUMMARY"
    echo "=========================================="
    echo "Database Host: $DB_HOST"
    echo "Database Name: $DB_NAME"
    echo "Backup Directory: $BACKUP_DIR"
    echo "Local Retention: $LOCAL_RETENTION_DAYS days"
    echo "Compression: $ENABLE_COMPRESSION (Level: $COMPRESSION_LEVEL)"
    echo "Log Directory: $BACKUP_LOGS_DIR"
    echo "=========================================="
}

################################################################################
# FUNCTION: Validate configuration
################################################################################
validate_config() {
    local errors=0
    
    if [ -z "$DB_HOST" ] || [ -z "$DB_USER" ] || [ -z "$DB_NAME" ]; then
        echo "[ERROR] Database configuration incomplete!"
        ((errors++))
    fi
    
    if [ -z "$BACKUP_DIR" ]; then
        echo "[ERROR] BACKUP_DIR not set!"
        ((errors++))
    fi
    
    if ! command -v mysqldump &> /dev/null; then
        echo "[ERROR] mysqldump not found. Please install mysql-client!"
        ((errors++))
    fi
    
    if [ $errors -gt 0 ]; then
        echo "[ERROR] Configuration validation failed with $errors error(s)!"
        return 1
    fi
    
    echo "[INFO] Configuration validation passed!"
    return 0
}

################################################################################
# EXPORT configuration untuk digunakan di script lain
################################################################################
# Script ini akan di-source oleh backup.sh dan restore.sh
# jangan ubah bagian ini!
export DB_HOST DB_USER DB_PASS DB_NAME DB_PORT
export BACKUP_DIR BACKUP_LOGS_DIR BACKUP_PREFIX
export LOCAL_RETENTION_DAYS ENABLE_COMPRESSION COMPRESSION_LEVEL
export NOTIFICATION_EMAIL LOG_LEVEL
export LOCK_TABLES SINGLE_TRANSACTION QUICK_BACKUP VERBOSE
export BACKUP_UPLOADS UPLOADS_DIR
