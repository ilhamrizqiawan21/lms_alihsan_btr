#!/bin/bash
################################################################################
# BACKUP HEALTH CHECK - LMS MTs Al-Ihsan Batujajar
# 
# Automated health check for backup system
# Usage: ./backup_healthcheck.sh [--email]
################################################################################

set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${SCRIPT_DIR}/backup_config.sh"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "[ERROR] Configuration file not found: $CONFIG_FILE"
    exit 1
fi
source "$CONFIG_FILE"

# Parse arguments
SEND_EMAIL=false
if [ "$1" = "--email" ]; then
    SEND_EMAIL=true
fi

# ============================================
# HEALTH CHECK FUNCTIONS
# ============================================

check_backup_directory() {
    local status="OK"
    local message=""
    
    # Check if directory exists
    if [ ! -d "$BACKUP_DIR" ]; then
        status="ERROR"
        message="Backup directory not found: $BACKUP_DIR"
    else
        # Check permissions
        if [ ! -w "$BACKUP_DIR" ]; then
            status="ERROR"
            message="Backup directory not writable: $BACKUP_DIR"
        fi
    fi
    
    echo "$status|Backup Directory|$message"
}

check_backup_files() {
    local backup_count=$(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" -type f 2>/dev/null | wc -l)
    local status="OK"
    local message=""
    
    if [ $backup_count -eq 0 ]; then
        status="ERROR"
        message="No backup files found!"
    elif [ $backup_count -lt 3 ]; then
        status="WARNING"
        message="Only $backup_count backup files found (expected at least 3)"
    else
        message="$backup_count backup files found"
    fi
    
    echo "$status|Backup Files Count|$message"
}

check_recent_backup() {
    local latest_backup=$(find "$BACKUP_DIR" -name "${BACKUP_PREFIX}_*.sql*" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
    local status="OK"
    local message=""
    
    if [ -z "$latest_backup" ]; then
        status="ERROR"
        message="No backup files found"
    else
        local backup_age_seconds=$(($(date +%s) - $(stat -c %Y "$latest_backup" 2>/dev/null || stat -f %m "$latest_backup")))
        local backup_age_hours=$((backup_age_seconds / 3600))
        local backup_age_days=$((backup_age_hours / 24))
        
        if [ $backup_age_days -gt 3 ]; then
            status="ERROR"
            message="Last backup is $(printf '%d days, %d hours' $backup_age_days $((backup_age_hours % 24))) old (too old!)"
        elif [ $backup_age_days -gt 1 ]; then
            status="WARNING"
            message="Last backup is $(printf '%d days, %d hours' $backup_age_days $((backup_age_hours % 24))) old"
        else
            message="Last backup is $(printf '%d hours, %d minutes' $backup_age_hours $((backup_age_seconds % 3600 / 60))) old"
        fi
    fi
    
    echo "$status|Recent Backup|$message"
}

check_backup_size() {
    local total_size=$(du -sb "$BACKUP_DIR" 2>/dev/null | cut -f1)
    local status="OK"
    local message=""
    
    if [ -z "$total_size" ] || [ "$total_size" -eq 0 ]; then
        status="ERROR"
        message="Cannot calculate backup size"
    else
        local size_mb=$((total_size / 1024 / 1024))
        local size_gb=$((size_mb / 1024))
        
        if [ $size_gb -gt 100 ]; then
            status="WARNING"
            message="Large backup directory: ${size_gb}GB (consider cleanup)"
        else
            if [ $size_gb -gt 0 ]; then
                message="Total size: ${size_gb}GB"
            else
                message="Total size: ${size_mb}MB"
            fi
        fi
    fi
    
    echo "$status|Backup Size|$message"
}

check_disk_space() {
    local available_kb=$(df "$BACKUP_DIR" 2>/dev/null | tail -1 | awk '{print $4}')
    local total_kb=$(df "$BACKUP_DIR" 2>/dev/null | tail -1 | awk '{print $2}')
    local status="OK"
    local message=""
    
    if [ -z "$available_kb" ]; then
        status="ERROR"
        message="Cannot check disk space"
    else
        local percent_used=$(((total_kb - available_kb) * 100 / total_kb))
        local available_gb=$((available_kb / 1024 / 1024))
        
        if [ $percent_used -gt 90 ]; then
            status="ERROR"
            message="Disk ${percent_used}% full (${available_gb}GB available)"
        elif [ $percent_used -gt 75 ]; then
            status="WARNING"
            message="Disk ${percent_used}% full (${available_gb}GB available)"
        else
            message="Disk ${percent_used}% used (${available_gb}GB available)"
        fi
    fi
    
    echo "$status|Disk Space|$message"
}

check_database_connection() {
    local status="OK"
    local message=""
    
    if mysqladmin -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -P "$DB_PORT" ping &>/dev/null; then
        message="Database connection OK"
    else
        status="ERROR"
        message="Cannot connect to database"
    fi
    
    echo "$status|Database Connection|$message"
}

check_backup_logs() {
    local log_file="${BACKUP_LOGS_DIR}/backup.log"
    local status="OK"
    local message=""
    
    if [ ! -f "$log_file" ]; then
        status="WARNING"
        message="Backup log not found"
    else
        local last_error=$(grep "ERROR\|FAILED" "$log_file" 2>/dev/null | tail -1)
        
        if [ -n "$last_error" ]; then
            status="WARNING"
            message="Last error: $(echo "$last_error" | cut -d' ' -f4-)"
        else
            local last_success=$(grep "successfully" "$log_file" 2>/dev/null | tail -1)
            if [ -n "$last_success" ]; then
                message="Last successful backup logged"
            else
                message="No successful backups logged"
            fi
        fi
    fi
    
    echo "$status|Backup Logs|$message"
}

check_cron_job() {
    local status="WARNING"
    local message="Cron job not configured"
    
    if crontab -l 2>/dev/null | grep -q "backup.sh"; then
        status="OK"
        local cron_line=$(crontab -l 2>/dev/null | grep "backup.sh" | head -1)
        message="Cron job configured: $cron_line"
    fi
    
    echo "$status|Cron Job|$message"
}

# ============================================
# REPORT GENERATION
# ============================================

generate_report() {
    echo "=================================="
    echo "LMS Backup System Health Check"
    echo "Generated: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "=================================="
    echo ""
    
    local error_count=0
    local warning_count=0
    
    while IFS='|' read status component message; do
        case $status in
            ERROR)
                echo "❌ $component: $message"
                ((error_count++))
                ;;
            WARNING)
                echo "⚠️  $component: $message"
                ((warning_count++))
                ;;
            OK)
                echo "✅ $component: $message"
                ;;
        esac
    done < <(
        check_backup_directory
        check_backup_files
        check_recent_backup
        check_backup_size
        check_disk_space
        check_database_connection
        check_backup_logs
        check_cron_job
    )
    
    echo ""
    echo "=================================="
    echo "Summary:"
    echo "  Errors: $error_count"
    echo "  Warnings: $warning_count"
    echo "=================================="
    
    if [ $error_count -eq 0 ] && [ $warning_count -eq 0 ]; then
        echo "✅ All checks passed!"
        return 0
    else
        echo "⚠️  Please review issues above"
        return 1
    fi
}

# ============================================
# MAIN EXECUTION
# ============================================

REPORT=$(generate_report)
EXIT_CODE=$?

echo "$REPORT"

if [ "$SEND_EMAIL" = true ] && [ -n "$NOTIFICATION_EMAIL" ]; then
    if command -v mail &>/dev/null; then
        echo "$REPORT" | mail -s "LMS Backup Health Check - $(date '+%Y-%m-%d')" "$NOTIFICATION_EMAIL"
        echo ""
        echo "Health check report sent to: $NOTIFICATION_EMAIL"
    fi
fi

exit $EXIT_CODE
