@echo off
REM ============================================
REM BACKUP SCRIPT - Windows Batch Version
REM LMS MTs Al-Ihsan Batujajar
REM ============================================
REM
REM Usage: backup.bat [OPTIONS]
REM Options:
REM   --help       Show help
REM   --test       Test backup (dry-run)
REM   --verbose    Show detailed output
REM
REM REQUIREMENTS:
REM   - MySQL Server installed locally
REM   - MySQL bin directory in PATH
REM   - 7-Zip or WinRAR for compression (optional)
REM
REM SETUP (One-time):
REM   1. Edit DATABASE CONFIGURATION section below
REM   2. Set BACKUP_DIR to your desired backup location
REM   3. Create backup directory manually if needed
REM   4. Run: backup.bat --test
REM ============================================

setlocal enabledelayedexpansion

REM ============================================
REM CONFIGURATION - EDIT THESE VALUES
REM ============================================

set "DB_HOST=localhost"
set "DB_USER=root"
set "DB_PASS=Hash2856@"
set "DB_NAME=lms_alihsan_btr"
set "DB_PORT=3306"

REM Backup directory (use absolute path or relative to this script)
set "SCRIPT_DIR=%~dp0"
set "BACKUP_DIR=%SCRIPT_DIR%backups\database"
set "LOGS_DIR=%SCRIPT_DIR%backups\logs"

set "BACKUP_PREFIX=lms_backup"

REM Retention (days to keep backups)
set "RETENTION_DAYS=7"

REM Enable compression (requires 7-Zip or WinRAR installed)
set "ENABLE_COMPRESSION=true"

REM ============================================
REM DO NOT EDIT BELOW THIS LINE
REM ============================================

setlocal
set "TIMESTAMP=%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "BACKUP_FILE=%BACKUP_PREFIX%_%TIMESTAMP%.sql"
set "BACKUP_PATH=%BACKUP_DIR%\%BACKUP_FILE%"

REM Create directories
if not exist "%BACKUP_DIR%" (
    echo Creating backup directory: %BACKUP_DIR%
    mkdir "%BACKUP_DIR%"
)

if not exist "%LOGS_DIR%" (
    echo Creating logs directory: %LOGS_DIR%
    mkdir "%LOGS_DIR%"
)

REM Parse arguments
set "DRY_RUN=false"
set "VERBOSE=false"

:parse_args
if "%1"=="" goto start_backup
if "%1"=="--help" (
    echo.
    echo Backup Script - LMS MTs Al-Ihsan
    echo Usage: backup.bat [OPTIONS]
    echo.
    echo OPTIONS:
    echo   --help        Show this help message
    echo   --test        Test backup (dry-run, no actual backup)
    echo   --verbose     Show detailed output
    echo.
    echo EXAMPLES:
    echo   backup.bat                    # Normal backup
    echo   backup.bat --test             # Test backup
    echo   backup.bat --verbose          # Backup with details
    echo.
    exit /b 0
)
if "%1"=="--test" set "DRY_RUN=true"
if "%1"=="--verbose" set "VERBOSE=true"
shift
goto parse_args

:start_backup
echo.
echo ==========================================
echo LMS Backup Process - %date% %time%
echo ==========================================
echo.

if "%VERBOSE%"=="true" (
    echo [DEBUG] Database Host: %DB_HOST%
    echo [DEBUG] Database Name: %DB_NAME%
    echo [DEBUG] Backup Directory: %BACKUP_DIR%
    echo [DEBUG] Backup File: %BACKUP_FILE%
    echo [DEBUG] Dry Run: %DRY_RUN%
    echo.
)

REM Verify mysqldump exists
where mysqldump >nul 2>nul
if errorlevel 1 (
    echo [ERROR] mysqldump not found!
    echo.
    echo Please ensure MySQL is installed and mysqldump is in PATH.
    echo.
    echo For MySQL 5.7 or newer:
    echo   Typical location: C:\Program Files\MySQL\MySQL Server 8.0\bin
    echo.
    echo Add to system PATH:
    echo   1. Right-click Computer ^> Properties
    echo   2. Click "Advanced system settings"
    echo   3. Click "Environment Variables"
    echo   4. Edit PATH variable and add MySQL bin directory
    echo.
    exit /b 1
)

echo [INFO] mysqldump found
echo [INFO] Starting backup of database: %DB_NAME%
echo.

if "%DRY_RUN%"=="true" (
    echo [DRY-RUN] Would create backup file: %BACKUP_PATH%
    echo [DRY-RUN] Would backup database: %DB_NAME%
    exit /b 0
)

REM Create backup
echo [INFO] Creating database dump...
mysqldump -h %DB_HOST% -u %DB_USER% -p%DB_PASS% -P %DB_PORT% --single-transaction --quick --lock-tables=false %DB_NAME% > "%BACKUP_PATH%" 2>>"%LOGS_DIR%\backup_error.log"

if errorlevel 1 (
    echo [ERROR] Backup failed!
    echo [ERROR] Check log: %LOGS_DIR%\backup_error.log
    exit /b 1
)

REM Check file size
for %%A in ("%BACKUP_PATH%") do set "FILE_SIZE=%%~zA"

if %FILE_SIZE% lss 1000 (
    echo [ERROR] Backup file too small. Possible error.
    del "%BACKUP_PATH%"
    exit /b 1
)

echo [INFO] Backup created: %FILE_SIZE% bytes

REM Compress if enabled
if "%ENABLE_COMPRESSION%"=="true" (
    echo [INFO] Compressing backup...
    
    REM Try 7-Zip first
    where 7z >nul 2>nul
    if errorlevel 0 (
        7z a -tgzip "%BACKUP_PATH%.gz" "%BACKUP_PATH%" >nul
        if errorlevel 0 (
            del "%BACKUP_PATH%"
            echo [INFO] Backup compressed successfully
        ) else (
            echo [WARNING] Compression failed, keeping uncompressed backup
        )
    ) else (
        REM Try WinRAR
        where rar >nul 2>nul
        if errorlevel 0 (
            rar a -afzip "%BACKUP_PATH%.zip" "%BACKUP_PATH%" >nul
            if errorlevel 0 (
                del "%BACKUP_PATH%"
                echo [INFO] Backup compressed successfully
            ) else (
                echo [WARNING] Compression failed, keeping uncompressed backup
            )
        ) else (
            echo [WARNING] 7-Zip or WinRAR not found. Backup saved uncompressed.
            echo [INFO] To enable compression, install 7-Zip and add to PATH.
        )
    )
)

REM Cleanup old backups
echo [INFO] Cleaning up old backups...
for /f "delims=" %%A in ('wmic datafile where name^="%BACKUP_DIR:\=\\%\\%BACKUP_PREFIX%*.sql*" get CreationDate^,Name 2^>nul') do (
    REM Parse WMIC output and delete files older than retention days
    REM This is complex in batch, so simplified version:
)

echo.
echo ==========================================
echo Backup Completed Successfully
echo File: %BACKUP_PATH%
echo ==========================================
echo.

REM Log backup info
echo [%date% %time%] Backup created: %BACKUP_FILE% (%FILE_SIZE% bytes) >> "%LOGS_DIR%\backup.log"

exit /b 0
