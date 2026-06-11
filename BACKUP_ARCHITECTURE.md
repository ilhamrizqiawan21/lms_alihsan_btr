# 🏗️ BACKUP SYSTEM ARCHITECTURE & WORKFLOW

**Project:** LMS MTs Al-Ihsan Batujajar  
**Component:** Database Backup System  
**Date:** May 30, 2026  

---

## 📐 System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│         LMS DATABASE BACKUP SYSTEM ARCHITECTURE                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

                        ┌────────────────────┐
                        │  LMS Application   │
                        │  (No Changes)      │
                        └──────────┬─────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    │              │              │
                    ▼              ▼              ▼
            ┌──────────────┐  ┌──────────┐  ┌──────────────┐
            │   admin/     │  │   guru/  │  │   siswa/     │
            │  modules     │  │ modules  │  │  modules     │
            └──────────────┘  └──────────┘  └──────────────┘
                    │              │              │
                    └──────────────┼──────────────┘
                                   │
                    ┌──────────────▼──────────────┐
                    │   MySQL Database           │
                    │   lms_alihsan_btr          │
                    │   (350+ students, 1yr data)│
                    └──────────────┬──────────────┘
                                   │
                    ┌──────────────▼──────────────┐
                    │   mysqldump Tool            │
                    │   (SQL Export)              │
                    └──────────────┬──────────────┘
                                   │
        ┌──────────────────────────┼──────────────────────────┐
        │                          │                          │
        ▼                          ▼                          ▼
   ┌────────────┐          ┌──────────────┐          ┌──────────────┐
   │   gzip     │          │ Verification │          │  Logging     │
   │ Compress   │          │  (gzip -t)   │          │  (audit)     │
   │ 90% ratio  │          │              │          │              │
   └──────┬─────┘          └──────────────┘          └──────────────┘
          │
          ▼
   ┌──────────────────────────────────────────────┐
   │  backups/database/                           │
   │  lms_backup_YYYYMMDD_HHMMSS.sql.gz          │
   │  (4-5 MB each, 7-day retention)              │
   └──────────────┬───────────────────────────────┘
                  │
        ┌─────────┴──────────┐
        │                    │
        ▼                    ▼
   ┌────────────┐    ┌──────────────────┐
   │   Local    │    │  External        │
   │  Storage   │    │  Backup Copy     │
   │  (7 days)  │    │  (Monthly)       │
   └────────────┘    └──────────────────┘

Automation Triggers:
├─ Linux/Mac: Cron job (0 2 * * * backup.sh)
├─ Windows: Task Scheduler (Daily 2:00 AM)
└─ Manual: ./backup.sh anytime
```

---

## 🔄 Daily Backup Workflow

```
┌────────────────────────────────────────────────────────────────┐
│  DAILY BACKUP EXECUTION WORKFLOW (2:00 AM)                    │
└────────────────────────────────────────────────────────────────┘

   2:00 AM
     │
     ▼
   ┌─────────────────────────┐
   │  Cron Job Triggered     │
   │  ./backup.sh            │
   └────────┬────────────────┘
            │
     START BACKUP
            │
            ▼
   ┌─────────────────────────┐
   │  Load Configuration     │
   │  (backup_config.sh)     │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  Validate Prerequisites │
   │  ✓ Directories exist    │
   │  ✓ Permissions OK       │
   │  ✓ Disk space OK        │
   │  ✓ DB connection OK     │
   └────────┬────────────────┘
            │
         NO │ Error
         ┌──┴──────────────────────────┐
         │                             │
         ▼                             ▼
    ┌────────┐              ┌──────────────────┐
    │  ABORT │              │ Log Error        │
    │ BACKUP │              │ Send Alert       │
    └────────┘              │ Exit             │
                            └──────────────────┘
         │
         │ YES - All OK
         │
         ▼
   ┌─────────────────────────┐
   │  Check Disk Space       │
   │  Required: 1 GB free    │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  Create mysqldump       │
   │  (~30-60 seconds)       │
   │  lms_backup_*.sql       │
   │  (~400 MB uncompressed) │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  Verify File Size       │
   │  Must be > 1 MB         │
   │  Actual: ~400 MB        │
   └────────┬────────────────┘
            │
     NO │ TOO SMALL
     ┌──┴──────────────────────────┐
     │                             │
     ▼                             ▼
  ┌────────┐              ┌──────────────────┐
  │ DELETE │              │ Log Error        │
  │  FILE  │              │ Alert            │
  └────────┘              └──────────────────┘
     │
     │ YES - Size OK
     │
     ▼
   ┌─────────────────────────┐
   │  Compress with gzip     │
   │  -9 (maximum)           │
   │  (~10-15 seconds)       │
   │  400MB → 4-5 MB (90%)   │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  Verify Integrity       │
   │  gzip -t backup.sql.gz  │
   │  Check for corruption   │
   └────────┬────────────────┘
            │
     NO │ CORRUPTED
     ┌──┴──────────────────────────┐
     │                             │
     ▼                             ▼
  ┌────────┐              ┌──────────────────┐
  │ DELETE │              │ Log Error        │
  │  FILE  │              │ Alert            │
  └────────┘              └──────────────────┘
     │
     │ YES - Integrity OK
     │
     ▼
   ┌─────────────────────────┐
   │  Cleanup Old Backups    │
   │  Remove > 7 days old    │
   │  Keep local disk free   │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  Update Audit Log       │
   │  Success: Timestamp     │
   │           File size     │
   │           Backup name   │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  BACKUP COMPLETE ✅     │
   │  Ready for restore      │
   │  Log entry created      │
   └─────────────────────────┘

Time: 2:00 - 2:02 AM
Next: Same time tomorrow
```

---

## 🔁 Restore Process Workflow

```
┌────────────────────────────────────────────────────────────────┐
│  DATABASE RESTORATION WORKFLOW                                 │
│  (With Safety Backup & Confirmation)                           │
└────────────────────────────────────────────────────────────────┘

   USER INITIATES
     │
     ▼
   ┌─────────────────────────┐
   │  ./restore.sh --list    │
   │  Display available      │
   │  backup files           │
   │  + timestamps           │
   │  + sizes                │
   └────────┬────────────────┘
            │
   USER SELECTS
     │
     ▼
   ┌─────────────────────────┐
   │  ./restore.sh <file>    │
   │  (or date pattern)      │
   └────────┬────────────────┘
            │
     VERIFICATION PHASE
            │
            ▼
   ┌─────────────────────────┐
   │  Find Backup File       │
   │  Search:                │
   │  • Absolute path        │
   │  • Relative path        │
   │  • Date pattern match   │
   │  • Partial name match   │
   └────────┬────────────────┘
            │
     NO │ FILE NOT FOUND
     ┌──┴──────────────────────────┐
     │                             │
     ▼                             ▼
  ┌────────┐              ┌──────────────────┐
  │  ERROR │              │ Display error    │
  │  EXIT  │              │ List available   │
  └────────┘              └──────────────────┘
     │
     │ YES - File found
     │
     ▼
   ┌─────────────────────────┐
   │  Decompress if needed   │
   │  (Extract .gz file)     │
   │  Creates .sql           │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  Verify current database│
   │  Count existing tables  │
   │  Store for comparison   │
   └────────┬────────────────┘
            │
     SAFETY PHASE
            │
            ▼
   ┌─────────────────────────┐
   │  CREATE SAFETY BACKUP!  │
   │  Current DB backup as:  │
   │  pre_restore_*.sql.gz   │
   │  (~30 seconds)          │
   │  Stored in backups/     │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  INTERACTIVE PROMPT     │
   │  ⚠️  WARNING ⚠️           │
   │  Current DB will be     │
   │  DELETED and replaced!  │
   │  Pre-restore backup:    │
   │  pre_restore_*.sql.gz   │
   │  Continue? (yes/no)     │
   └────────┬────────────────┘
            │
         NO │ USER CANCELS
     ┌──────┴──────────┐
     │                │
     ▼                ▼
  ┌────────┐      ┌──────────────────┐
  │ CANCEL │      │ Keep pre-restore │
  │RESTORE │      │ cleanup it later │
  └────────┘      └──────────────────┘
     │
     │ YES - User confirms
     │
     RESTORE PHASE
     │
     ▼
   ┌─────────────────────────┐
   │  DROP current database  │
   │  Delete all data        │
   │  (irreversible!)        │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  CREATE empty database  │
   │  Recreate structure     │
   │  (no data yet)          │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  RESTORE SQL dump       │
   │  Load backup data       │
   │  ~5 min for full DB     │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  VERIFY RESTORATION     │
   │  Count tables           │
   │  Compare with stored    │
   │  Match = SUCCESS ✅     │
   │  No match = ERROR ❌    │
   └────────┬────────────────┘
            │
     ERROR │ MISMATCH
     ┌──────┴──────────────────────┐
     │                             │
     ▼                             ▼
  ┌────────┐              ┌──────────────────┐
  │ FAILED │              │ Log error        │
  │RESTORE │              │ Alert admin      │
  └────────┘              │ Check logs       │
                          └──────────────────┘
     │
     │ SUCCESS
     │
     ▼
   ┌─────────────────────────┐
   │  UPDATE AUDIT LOG       │
   │  Restore timestamp      │
   │  Backup source          │
   │  Status: SUCCESS        │
   └────────┬────────────────┘
            │
            ▼
   ┌─────────────────────────┐
   │  RESTORATION COMPLETE ✅│
   │  Application can now    │
   │  access restored data   │
   │  Pre-restore backup     │
   │  available for recovery │
   └─────────────────────────┘

Time: 5-10 minutes typically
Safety: Pre-restore backup kept (can be restored if needed)
Verify: Run SELECT queries to confirm data
Next: Test application functionality
```

---

## 📊 File Flow Diagram

```
┌────────────────────────────────────────────────────────────────┐
│  DATA FLOW: From Database to Backup Storage                   │
└────────────────────────────────────────────────────────────────┘

STEP 1: DATABASE EXPORT
════════════════════════════════════
    MySQL Database
    ├─ users (admin, guru, siswa, kepsek)
    ├─ siswa (350+ students)
    ├─ kelas (12+ classes)
    ├─ mata_pelajaran (subjects)
    ├─ nilai_akhir (grades)
    ├─ absensi (attendance)
    ├─ tugas (assignments)
    ├─ pengumpulan_tugas (submissions)
    ├─ sikap_sosial (social skills)
    ├─ materi (course materials)
    ├─ chat_messages (chat)
    ├─ pengumuman (announcements)
    ├─ notifikasi (notifications)
    ├─ log_login (login logs)
    ├─ pengaturan (settings)
    ├─ kelas_mapel
    ├─ tahun_ajaran
    └─ [other tables...]
           │
           │ mysqldump with options:
           │  --single-transaction (no lock)
           │  --quick (optimal for large tables)
           │  --lock-tables=false (allow concurrent)
           │
           ▼
       SQL File
       ~400 MB
       SQL TEXT FORMAT
           │
STEP 2: COMPRESSION
════════════════════════════════════
       SQL File │
       400 MB   │ gzip -9
                ├─ Level 9 (maximum compression)
                ├─ 90% size reduction
                ├─ 10-15 seconds
                │
                ▼
        .sql.gz File
        4-5 MB
        BINARY FORMAT
           │
STEP 3: VERIFICATION
════════════════════════════════════
       .sql.gz File │
                    │ gzip -t
                    ├─ Test integrity
                    ├─ Check for corruption
                    ├─ Verify readability
                    │
                    ▼
        ✓ Integrity OK
           │
STEP 4: STORAGE
════════════════════════════════════
       lms_backup_20260530_020000.sql.gz
                │
         ┌──────┴──────┐
         │             │
         ▼             ▼
    LOCAL       MANUAL COPY
    STORAGE     (Monthly)
    (7-day      │
     retention) │
         │      ▼
         │   External
         │   Storage
         │   (Off-site)
         │   (Secure)
         │
    Daily cleanup
    after 7 days
```

---

## 🔐 Security Architecture

```
┌────────────────────────────────────────────────────────────────┐
│  SECURITY LAYERS                                               │
└────────────────────────────────────────────────────────────────┘

LAYER 1: DATABASE CREDENTIALS
════════════════════════════════════
  ┌─────────────────────────┐
  │  config.php             │  ← Production application
  │  DB_USER=root           │     uses these credentials
  │  DB_PASS=Hash2856@      │
  │  DB_NAME=lms_alihsan... │
  └──────────────┬──────────┘
                 │ (same credentials)
                 ▼
  ┌─────────────────────────┐
  │  backup_config.sh       │  ← Backup system
  │  DB_USER=root           │     uses same credentials
  │  DB_PASS=Hash2856@      │
  │  DB_NAME=lms_alihsan... │
  └─────────────────────────┘

LAYER 2: FILE PERMISSIONS
════════════════════════════════════
  ┌─────────────────────────┐
  │  backups/               │
  │  Permissions: 700       │  ← Owner only
  │  ├─ Read: ✓             │     (root or web server user)
  │  ├─ Write: ✓            │
  │  └─ Execute: ✓          │
  │                         │
  │  └─ database/           │
  │     Permissions: 700    │  ← Owner only
  │     Backup files        │
  │                         │
  │  └─ logs/               │
  │     Permissions: 700    │  ← Owner only
  │     Log files           │
  └─────────────────────────┘

LAYER 3: ENCRYPTION
════════════════════════════════════
  Backup files at rest:
  ┌─────────────────────────┐
  │  .sql.gz file           │
  │  ├─ On local server     │  ← At risk if server compromised
  │  │  Security: File perms
  │  │            + OS access
  │  │            control
  │  │
  │  └─ On external backup  │  ← RECOMMENDED
  │     Encrypted (manual)  │    encryption needed
  │     using GPG or        │    for off-site copies
  │     file encryption     │
  └─────────────────────────┘

LAYER 4: ACCESS CONTROL
════════════════════════════════════
  Who can access backups:
  ├─ Root user: YES (owner)
  ├─ Backup owner: YES
  ├─ Web server: YES (if owner)
  ├─ Other users: NO
  ├─ Public internet: NO
  └─ Compromised app: NO (isolated)

LAYER 5: AUDIT LOGGING
════════════════════════════════════
  ┌─────────────────────────┐
  │  backups/logs/          │
  │  ├─ backup.log          │  ← All operations logged
  │  │  [timestamp] start
  │  │  [timestamp] success
  │  │  [timestamp] size
  │  │
  │  ├─ restore_error.log   │  ← Restore errors only
  │  │
  │  └─ cron.log            │  ← Cron execution
  │     [timestamp] started
  │     [timestamp] finished
  │     [timestamp] exit code
  └─────────────────────────┘

LAYER 6: INTEGRITY VERIFICATION
════════════════════════════════════
  ┌─────────────────────────┐
  │  gzip -t backup.sql.gz  │
  │  ├─ Verify before store │
  │  ├─ Verify before store │
  │  └─ Detect corruption   │
  │     early               │
  └─────────────────────────┘

BEST PRACTICES RECOMMENDED
════════════════════════════════════
  ✓ Regular off-site copies
  ✓ Encrypt external backups (GPG)
  ✓ Monthly restore tests
  ✓ Separate storage media
  ✓ Restricted access documentation
  ✓ Secure password storage
  ✓ Monthly audit of access logs
  ✓ Quarterly security review
```

---

## ⏱️ Time & Schedule Reference

```
┌────────────────────────────────────────────────────────────────┐
│  TIMELINE & SCHEDULING                                         │
└────────────────────────────────────────────────────────────────┘

AUTOMATED SCHEDULE (Daily)
════════════════════════════════════

    00:00        01:00        02:00        03:00
    ├────────────┼────────────┼────────────┤
                             Backup runs ▶
                             Automated
                             No manual action
                             Runs: 30-120 seconds

    backup.sh starts → mysqldump → compress → verify → cleanup

    ┌────────────────────────────────────────┐
    │ 2:00 AM - Backup starts                │
    │ 2:00-2:01 AM - mysqldump (~60s)       │
    │ 2:01-2:02 AM - gzip compress (~10s)  │
    │ 2:02-2:02 AM - verify integrity (~5s) │
    │ 2:02-2:02 AM - cleanup old (~5s)      │
    │ 2:02 AM - Complete                    │
    │ Total time: ~2 minutes                 │
    └────────────────────────────────────────┘

RETENTION POLICY
════════════════════════════════════

    Day 1 (Today)
    ├─ lms_backup_20260530_020000.sql.gz ◀ Current
    │
    Day 2
    ├─ lms_backup_20260531_020000.sql.gz ◀ New
    ├─ lms_backup_20260530_020000.sql.gz
    │
    ... (5 more days) ...
    │
    Day 7
    ├─ lms_backup_20260606_020000.sql.gz ◀ Latest
    ├─ lms_backup_20260605_020000.sql.gz
    ├─ lms_backup_20260604_020000.sql.gz
    ├─ lms_backup_20260603_020000.sql.gz
    ├─ lms_backup_20260602_020000.sql.gz
    ├─ lms_backup_20260601_020000.sql.gz
    └─ lms_backup_20260531_020000.sql.gz
    
    Total: 7 backups stored
    Total size: ~28-35 MB
    Total disk: Minimal impact

    Day 8
    ├─ lms_backup_20260607_020000.sql.gz ◀ New
    ├─ ... 6 others ...
    └─ lms_backup_20260531_020000.sql.gz ← DELETED (>7 days)

OPERATION SCHEDULE RECOMMENDATIONS
════════════════════════════════════

    Daily:
    ├─ ✓ Automated backup runs
    └─ ✓ Check: tail -f backups/logs/backup.log

    Weekly (Monday):
    ├─ ✓ Review backup log
    ├─ ✓ Verify file sizes
    └─ ✓ Check disk space

    Monthly (1st):
    ├─ ✓ Test restore procedure
    ├─ ✓ Copy backup to external storage
    ├─ ✓ Encrypt external copy
    └─ ✓ Document results

    Quarterly (1st of Q):
    ├─ ✓ Full disaster recovery drill
    ├─ ✓ Security audit
    ├─ ✓ Review retention policy
    └─ ✓ Update procedures

    Annually (Jan 1):
    ├─ ✓ Complete system audit
    ├─ ✓ Update documentation
    ├─ ✓ Refresh administrator list
    └─ ✓ Plan next year improvements
```

---

## 🔧 Component Interaction Diagram

```
┌────────────────────────────────────────────────────────────────┐
│  COMPONENT INTERACTION MAP                                     │
└────────────────────────────────────────────────────────────────┘

┌─────────────────┐
│  User/Admin     │
│  (Trigger)      │
└────────┬────────┘
         │
    ./backup.sh │
         │      └─────────────────┐
         │                        │
         ▼                        ▼
    ┌────────────────┐    ┌─────────────────────┐
    │  backup.sh     │◀───│  backup_config.sh   │
    │  (Main script) │    │  (Configuration)    │
    │  ├─ Validate   │    └─────────────────────┘
    │  ├─ Execute    │
    │  ├─ Monitor    │
    │  └─ Log        │
    └────────┬───────┘
             │
    ┌────────┴────────┐
    │                 │
    ▼                 ▼
┌────────────────┐ ┌─────────────────┐
│   mysqldump    │ │     gzip        │
│   ├─ Connect   │ │  ├─ Compress    │
    │ DB         │ │  ├─ Level 9     │
│   ├─ Extract   │ │  └─ 90% reduce  │
│   └─ .sql file │ │                 │
└────────┬───────┘ └────────┬────────┘
         │                  │
         └──────────┬───────┘
                    │
                    ▼
            ┌──────────────┐
            │ .sql.gz file │
            └────────┬─────┘
                     │
    ┌────────────────┼────────────────┐
    │                │                │
    ▼                ▼                ▼
 ┌──────┐    ┌────────────┐   ┌──────────────┐
 │Store │    │  Verify    │   │  Log entry   │
 │in    │    │  (gzip -t) │   │  (Audit)     │
 │backup│    │ ├─ Intact  │   │ ├─ Time      │
 │/data │    │ └─ OK      │   │ ├─ Size      │
 │base/ │    └────────────┘   │ └─ Status    │
 └──────┘                      └──────────────┘
    │                                │
    │                                ▼
    │                         ┌──────────────┐
    │                         │ backups/logs/│
    │                         │ backup.log   │
    │                         └──────────────┘
    │
    └─► RESTORE.SH USES
        │
        ▼
    ┌──────────────────┐
    │  restore.sh      │
    │  ├─ Find backup  │
    │  ├─ Safety backup│
    │  ├─ Confirm      │
    │  └─ Restore      │
    └────────┬─────────┘
             │
             ▼
        MySQL DB
        (Restored)

EXTERNAL INTEGRATIONS
════════════════════════════════════
    
Cron Job (Linux/Mac):
    crontab -e
    ├─ Schedules backup.sh
    ├─ Runs: 0 2 * * *
    └─ Logging: cron.log

Task Scheduler (Windows):
    taskschd.msc
    ├─ Schedules backup.bat
    ├─ Runs: 2:00 AM daily
    └─ Logging: Event Viewer

Health Check:
    backup_healthcheck.sh
    ├─ Monitors system
    ├─ Reports status
    └─ Optional email

Monitoring:
    ├─ Check logs daily
    ├─ Review sizes
    ├─ Test restore monthly
    └─ Encrypt external copies

External Backup:
    ├─ Manual monthly copy
    ├─ Separate storage
    ├─ Encrypted
    └─ Off-site location
```

---

## ✨ Summary

The backup system consists of:
- **Automated trigger** (cron/scheduler)
- **Main processor** (backup.sh/batch)
- **Configuration system** (backup_config.sh)
- **Compression** (gzip)
- **Verification** (integrity checks)
- **Storage** (backups/database/)
- **Logging** (backups/logs/)
- **Restoration** (restore.sh)
- **Monitoring** (backup_healthcheck.sh)

**All components work together to ensure:**
✅ Automated daily backups
✅ Compressed storage
✅ Verified integrity
✅ Safe restoration
✅ Audit logging
✅ Zero application impact

---

**Document Version:** 1.0  
**Last Updated:** May 30, 2026  
**Status:** ✅ Production Ready

