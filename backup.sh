#!/bin/bash
################################################################################
# BACKUP SCRIPT - LMS MTs Al-Ihsan Batujajar
# 
# Automated database backup dengan retention policy
# Usage: ./backup.sh [OPTIONS]
# 
# OPTIONS:
#   --dry-run      : Show what would be backed up without actually doing it
#   --verbose      : Show detailed output
#   --no-compress  : Don't compress the backup file
#   --no-cleanup   : Don't delete old backups
#   --help         : Show this help message
################################################################################

set -o pipefail

# ============================================
# SETUP
# ============================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${SCRIPT_DIR}/backup_config.sh"

# Load configuration
if [ ! -f "$CONFIG_FILE" ]; then
    echo "[ERROR] Configuration file not found: $CONFIG_FILE"
    exit 1
fi
source "$CONFIG_FILE"

# ============================================
# PARSE ARGUMENTS
# ============================================
DRY_RUN=false
VERBOSE_MODE=false
COMPRESS=true
CLEANUP=true

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --verbose)
            VERBOSE_MODE=true
            shift
            ;;
        --no-compress)
            COMPRESS=false
            shift
            ;;
        --no-cleanup)
            CLEANUP=false
            shift
            ;;
        --help)
            cat << 'EOF'
Backup Script untuk LMS MTs Al-Ihsan

Usage: ./backup.sh [OPTIONS]

OPTIONS:
  --dry-run      Show what would be backed up without doing it
  --verbose      Show detailed output
  --no-compress  Don't compress backup file
  --no-cleanup   Don't delete old backups
  --help         Show this message

EXAMPLES:
  ./backup.sh                    # Normal backup
  ./backup.sh --dry-run          # Test backup
  ./backup.sh --verbose          # Backup with details
  ./backup.sh --no-compress      # Uncompressed backup

CRON SETUP:
  0 2 * * * /path/to/backup.sh >> /path/to/backup.log 2>&1
  0 14 * * * /path/to/backup.sh >> /path/to/backup.log 2>&1

EOF
            exit 0
            ;;
        *)
            echo "[ERROR] Unknown option: $1"
            exit 1
            ;;
    esac
done

# ============================================
# FUNCTIONS
# ============================================

log_message() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] [$level] $message"
    echo "[$timestamp] [$level] $message" >> "${BACKUP_LOGS_DIR}/backup.log"
}

log_debug() {
    if [ "$VERBOSE_MODE" = true ]; then
        log_message "DEBUG" "$@"
    fi
}

log_info() {
    log_message "INFO" "$@"
}

log_warning() {
    log_message "WARNING" "$@"
}

log_error() {
    log_message "ERROR" "$@"
}

# Setup directories
setup_directories() {
    if [ ! -d "$BACKUP_DIR" ]; then
        log_info "Creating backup directory: $BACKUP_DIR"
        if [ "$DRY_RUN" = false ]; then
            mkdir -p "$BACKUP_DIR"
            chmod 700 "$BACKUP_DIR"
        fi
    fi
    
    if [ ! -d "$BACKUP_LOGS_DIR" ]; then
        log_info "Creating logs directory: $BACKUP_LOGS_DIR"
        if [ "$DRY_RUN" = false ]; then
            mkdir -p "$BACKUP_LOGS_DIR"
            chmod 700 "$BACKUP_LOGS_DIR"
        fi
    fi
}

# Get disk space
check_disk_space() {
    local available_space=$(df "$BACKUP_DIR" | awk 'NR==2 {print $4}')
    local db_size=$(du -b /var/lib/mysql/$DB_NAME 2>/dev/null | awk '{print $1}')
    
    if [ -z "$db_size" ]; then
        # Estimate if not available
        db_size=10485760  # 10MB default estimate
    fi
    
    local estimated_backup_size=$((db_size / 3))  # Rough estimate after compression
    
    log_debug "Available disk space: ${available_space}KB"
    log_debug "Database size: ${db_size} bytes"
    log_debug "Estimated backup size: ${estimated_backup_size} bytes"
    
    if [ "$available_space" -lt $((estimated_backup_size / 1024)) ]; then
        log_error "Not enough disk space! Available: ${available_space}KB, Needed: $((estimated_backup_size / 1024))KB"
        return 1
    fi
    
    return 0
}

# Verify database connection
verify_connection() {
    log_debug "Verifying database connection..."
    
    if mysqladmin -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" ping &>/dev/null; then
        log_info "Database connection verified successfully"
        return 0
    else
        log_error "Failed to connect to database!"
        log_error "Host: $DB_HOST, User: $DB_USER, Database: $DB_NAME"
        return 1
    fi
}

# Create backup
create_backup() {
    local backup_filename="${BACKUP_PREFIX}_$(date +$BACKUP_TIMESTAMP_FORMAT).sql"
    local backup_filepath="${BACKUP_DIR}/${backup_filename}"
    
    log_info "Starting database backup..."
    log_debug "Backup file: $backup_filepath"
    
    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY-RUN] Would create backup: $backup_filepath"
        return 0
    fi
    
    # Build mysqldump command
    local dump_options="-h $DB_HOST -u $DB_USER -p$DB_PASS -P $DB_PORT"
    
    if [ "$SINGLE_TRANSACTION" = true ]; then
        dump_options="$dump_options --single-transaction"
    fi
    
    if [ "$QUICK_BACKUP" = true ]; then
        dump_options="$dump_options --quick"
    fi
    
    if [ "$LOCK_TABLES" = true ]; then
        dump_options="$dump_options --lock-tables=false"
    fi
    
    # Execute backup
    if mysqldump $dump_options "$DB_NAME" > "$backup_filepath" 2>"${BACKUP_LOGS_DIR}/backup_error.log"; then
        local file_size=$(du -h "$backup_filepath" | cut -f1)
        log_info "Database backup created successfully: $file_size"
        
        # Compress if enabled
        if [ "$COMPRESS" = true ] && [ "$ENABLE_COMPRESSION" = true ]; then
            compress_backup "$backup_filepath"
        fi
        
        return 0
    else
        log_error "Backup failed! Check error log: ${BACKUP_LOGS_DIR}/backup_error.log"
        cat "${BACKUP_LOGS_DIR}/backup_error.log" | log_error
        rm -f "$backup_filepath"
        return 1
    fi
}

# Compress backup
compress_backup() {
    local backup_file=$1
    
    log_info "Compressing backup file..."
    log_debug "Using compression level: $COMPRESSION_LEVEL"
    
    if gzip -$COMPRESSION_LEVEL "$backup_file"; then
        local compressed_file="${backup_file}.gz"
        local original_size=$(stat --format=%s "$backup_file" 2>/dev/null || stat -f%z "$backup_file")
        local compressed_size=$(stat --format=%s "$compressed_file" 2>/dev/null || stat -f%z "$compressed_file")
        local ratio=$(echo "scale=2; $compressed_size * 100 / $original_size" | bc)
        
        log_info "Backup compressed: $(numfmt --to=iec-i --suffix=B $compressed_size 2>/dev/null || echo $compressed_size bytes) ($ratio%)"
        return 0
    else
        log_error "Backup compression failed!"
        return 1
    fi
}

# Cleanup old backups
cleanup_old_backups() {
    log_info "Cleaning up old backups (retention: $LOCAL_RETENTION_DAYS days)..."
    
    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY-RUN] Would delete backups older than $LOCAL_RETENTION_DAYS days"
        find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" -mtime +$LOCAL_RETENTION_DAYS -type f
        return 0
    fi
    
    local deleted_count=0
    while IFS= read -r file; do
        if rm -f "$file"; then
            log_debug "Deleted old backup: $(basename "$file")"
            ((deleted_count++))
        else
            log_warning "Failed to delete: $file"
        fi
    done < <(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" -mtime +$LOCAL_RETENTION_DAYS -type f)
    
    if [ $deleted_count -gt 0 ]; then
        log_info "Deleted $deleted_count old backup(s)"
    else
        log_info "No old backups to delete"
    fi
}

# Verify backup integrity
verify_backup() {
    log_info "Verifying backup integrity..."
    
    local latest_backup=$(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" -type f -printf '%T@ %p\n' | sort -rn | head -1 | cut -d' ' -f2-)
    
    if [ -z "$latest_backup" ]; then
        log_error "No backup file found!"
        return 1
    fi
    
    log_debug "Latest backup: $latest_backup"
    
    # Check if file exists and has content
    if [ -f "$latest_backup" ] && [ -s "$latest_backup" ]; then
        local file_size=$(du -h "$latest_backup" | cut -f1)
        log_info "Backup verified: $latest_backup ($file_size)"
        
        # If compressed, check gzip integrity
        if [[ "$latest_backup" == *.gz ]]; then
            if gzip -t "$latest_backup" 2>/dev/null; then
                log_info "Gzip integrity check: PASSED"
                return 0
            else
                log_error "Gzip integrity check: FAILED"
                return 1
            fi
        fi
        
        return 0
    else
        log_error "Backup file not found or empty!"
        return 1
    fi
}

# Send notification (if email configured)
send_notification() {
    local status=$1
    local message=$2
    
    if [ -z "$NOTIFICATION_EMAIL" ]; then
        return 0
    fi
    
    local subject="LMS Backup $status - $(date '+%Y-%m-%d %H:%M:%S')"
    
    if command -v mail &>/dev/null; then
        echo "$message" | mail -s "$subject" "$NOTIFICATION_EMAIL"
        log_debug "Notification sent to: $NOTIFICATION_EMAIL"
    fi
}

# ============================================
# MAIN EXECUTION
# ============================================

main() {
    log_info "=========================================="
    log_info "Starting LMS Backup Process"
    log_info "=========================================="
    
    # Validate configuration
    if ! validate_config; then
        log_error "Configuration validation failed!"
        exit 1
    fi
    
    # Setup
    setup_directories
    
    # Verify database connection
    if ! verify_connection; then
        log_error "Cannot proceed without database connection"
        send_notification "FAILED" "Database connection error"
        exit 1
    fi
    
    # Check disk space
    if ! check_disk_space; then
        log_error "Insufficient disk space"
        send_notification "FAILED" "Insufficient disk space"
        exit 1
    fi
    
    # Create backup
    if ! create_backup; then
        log_error "Backup creation failed!"
        send_notification "FAILED" "Backup creation failed"
        exit 1
    fi
    
    # Verify backup
    if ! verify_backup; then
        log_error "Backup verification failed!"
        send_notification "FAILED" "Backup verification failed"
        exit 1
    fi
    
    # Cleanup old backups
    if [ "$CLEANUP" = true ]; then
        cleanup_old_backups
    fi
    
    log_info "=========================================="
    log_info "Backup process completed successfully!"
    log_info "=========================================="
    
    send_notification "SUCCESS" "Database backup completed successfully"
    
    return 0
}

# Run main
main
exit $?
