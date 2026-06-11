#!/bin/bash
################################################################################
# RESTORE SCRIPT - LMS MTs Al-Ihsan Batujajar
# 
# Restore database dari backup file
# Usage: ./restore.sh <backup_file>
# 
# EXAMPLES:
#   ./restore.sh lms_backup_20260529_140000.sql.gz
#   ./restore.sh /path/to/backups/database/lms_backup_20260529_140000.sql
# 
# ⚠️  WARNING: RESTORE akan MENGHAPUS database yang ada sebelumnya!
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
# VALIDATION
# ============================================

if [ $# -eq 0 ]; then
    echo "ERROR: Missing backup file argument!"
    echo ""
    echo "Usage: $0 <backup_file>"
    echo ""
    echo "Examples:"
    echo "  $0 lms_backup_20260529_140000.sql.gz"
    echo "  $0 /path/to/backups/database/lms_backup_20260529_140000.sql"
    echo "  $0 --list                              # List available backups"
    echo ""
    exit 1
fi

# Handle --list option
if [ "$1" = "--list" ]; then
    echo "Available backup files:"
    echo "======================="
    ls -lh "$BACKUP_DIR"/${BACKUP_PREFIX}_*.sql* 2>/dev/null || echo "No backups found"
    exit 0
fi

# ============================================
# FUNCTIONS
# ============================================

log_message() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] [$level] $message"
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

# Confirm action
confirm() {
    local prompt="$1"
    local response
    
    read -p "$prompt (yes/no): " response
    
    if [ "$response" = "yes" ] || [ "$response" = "y" ]; then
        return 0
    else
        return 1
    fi
}

# Find backup file
find_backup_file() {
    local search_file=$1
    
    # Check if absolute path
    if [ -f "$search_file" ]; then
        echo "$search_file"
        return 0
    fi
    
    # Check in backup directory
    if [ -f "$BACKUP_DIR/$search_file" ]; then
        echo "$BACKUP_DIR/$search_file"
        return 0
    fi
    
    # Try with .gz extension
    if [ -f "$BACKUP_DIR/$search_file.gz" ]; then
        echo "$BACKUP_DIR/$search_file.gz"
        return 0
    fi
    
    # Try to find by partial name
    local found=$(find "$BACKUP_DIR" -name "*$search_file*" -type f 2>/dev/null | head -1)
    if [ -n "$found" ]; then
        echo "$found"
        return 0
    fi
    
    return 1
}

# Extract if compressed
decompress_if_needed() {
    local backup_file=$1
    local temp_file="${backup_file%.gz}"
    
    if [[ "$backup_file" == *.gz ]]; then
        log_info "Decompressing backup file..."
        if ! gzip -dc "$backup_file" > "$temp_file"; then
            log_error "Failed to decompress backup file!"
            return 1
        fi
        echo "$temp_file"
    else
        echo "$backup_file"
    fi
}

# Verify backup before restore
verify_restore_file() {
    local backup_file=$1
    
    log_info "Verifying backup file..."
    
    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        return 1
    fi
    
    local file_size=$(du -h "$backup_file" | cut -f1)
    log_info "Backup file: $backup_file ($file_size)"
    
    # Check if compressed
    if [[ "$backup_file" == *.gz ]]; then
        log_info "Backup is compressed (gzip)"
        if ! gzip -t "$backup_file" 2>/dev/null; then
            log_error "Backup file is corrupted!"
            return 1
        fi
        log_info "Gzip integrity check: PASSED"
    fi
    
    return 0
}

# Backup current database before restore
backup_before_restore() {
    log_warning "Creating backup of current database before restore..."
    
    local safety_backup="${BACKUP_DIR}/pre_restore_$(date +%Y%m%d_%H%M%S).sql.gz"
    
    if mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" \
        --single-transaction --quick --lock-tables=false "$DB_NAME" | \
        gzip -9 > "$safety_backup"; then
        log_info "Pre-restore backup created: $safety_backup"
        return 0
    else
        log_error "Failed to create pre-restore backup!"
        return 1
    fi
}

# Perform restore
perform_restore() {
    local restore_file=$1
    
    log_info "=========================================="
    log_info "Starting Database Restore"
    log_info "=========================================="
    log_info "Backup file: $restore_file"
    log_info "Database: $DB_NAME@$DB_HOST"
    log_info ""
    
    # Final confirmation
    if ! confirm "⚠️  WARNING: This will DELETE and RESTORE the database. Continue?"; then
        log_warning "Restore cancelled by user"
        return 1
    fi
    
    # Create safety backup
    if ! backup_before_restore; then
        if ! confirm "Pre-restore backup failed. Continue anyway?"; then
            return 1
        fi
    fi
    
    # Drop database
    log_info "Dropping existing database..."
    if mysqladmin -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" \
        --force drop "$DB_NAME" 2>/dev/null; then
        log_info "Database dropped"
    else
        log_error "Failed to drop database!"
        return 1
    fi
    
    # Create empty database
    log_info "Creating new database..."
    if ! mysqladmin -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" \
        create "$DB_NAME"; then
        log_error "Failed to create database!"
        return 1
    fi
    
    # Restore from backup
    log_info "Restoring from backup file..."
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" \
        "$DB_NAME" < "$restore_file" 2>"${BACKUP_LOGS_DIR}/restore_error.log"; then
        log_info "Database restore completed successfully!"
        return 0
    else
        log_error "Restore failed! Check error log: ${BACKUP_LOGS_DIR}/restore_error.log"
        if [ -f "${BACKUP_LOGS_DIR}/restore_error.log" ]; then
            cat "${BACKUP_LOGS_DIR}/restore_error.log"
        fi
        return 1
    fi
}

# Verify restore
verify_restore() {
    log_info "Verifying restored database..."
    
    local table_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" \
        -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null)
    
    if [ -n "$table_count" ] && [ "$table_count" -gt 0 ]; then
        log_info "Restore verification: PASSED ($table_count tables found)"
        return 0
    else
        log_error "Restore verification: FAILED"
        return 1
    fi
}

# Cleanup temp files
cleanup_temp_files() {
    local backup_file=$1
    local temp_file="${backup_file%.gz}"
    
    if [[ "$backup_file" == *.gz ]] && [ "$temp_file" != "$backup_file" ] && [ -f "$temp_file" ]; then
        rm -f "$temp_file"
        log_info "Temporary decompressed file deleted"
    fi
}

# ============================================
# MAIN EXECUTION
# ============================================

main() {
    local backup_arg=$1
    
    # Create logs directory if not exists
    mkdir -p "$BACKUP_LOGS_DIR"
    
    # Find backup file
    backup_file=$(find_backup_file "$backup_arg")
    if [ -z "$backup_file" ]; then
        log_error "Backup file not found: $backup_arg"
        log_info "Available backups:"
        ls -lh "$BACKUP_DIR"/${BACKUP_PREFIX}_*.sql* 2>/dev/null || echo "  (none)"
        exit 1
    fi
    
    # Verify restore file
    if ! verify_restore_file "$backup_file"; then
        exit 1
    fi
    
    # Decompress if needed
    restore_file=$(decompress_if_needed "$backup_file")
    if [ -z "$restore_file" ]; then
        exit 1
    fi
    
    # Perform restore
    if perform_restore "$restore_file"; then
        # Verify restore
        if verify_restore; then
            log_info "=========================================="
            log_info "Database Restore Successful!"
            log_info "=========================================="
            cleanup_temp_files "$backup_file"
            return 0
        else
            log_error "Restore verification failed!"
            cleanup_temp_files "$backup_file"
            return 1
        fi
    else
        log_error "Restore failed!"
        cleanup_temp_files "$backup_file"
        return 1
    fi
}

# Run main
main "$@"
exit $?
