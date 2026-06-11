# LMS Database Backups

This directory contains automated database backups for LMS MTs Al-Ihsan Batujajar.

## Directory Structure

```
backups/
├── database/          # Backup files (.sql.gz)
│   ├── lms_backup_20260530_020000.sql.gz
│   ├── lms_backup_20260529_020000.sql.gz
│   └── pre_restore_*.sql.gz              (pre-restore safety backups)
└── logs/              # Log files
    ├── backup.log     # Backup execution log
    ├── cron.log       # Cron job execution log
    └── restore_error.log
```

## Quick Start

### Create Backup
```bash
cd ..  # Go to project root
./backup.sh
```

### List Backups
```bash
./restore.sh --list
```

### Restore Database
```bash
./restore.sh lms_backup_20260530_020000.sql.gz
```

## File Information

### Backup Files
- **Name Format:** `lms_backup_YYYYMMDD_HHMMSS.sql.gz`
- **Compressed:** Yes (using gzip)
- **Typical Size:** 4-5 MB (depending on database size)
- **Retention:** 7 days (configurable)

### Log Files
- **backup.log:** Full execution history of backups
- **restore_error.log:** Errors during restore operations
- **cron.log:** Output from cron job execution

## Important Notes

⚠️ **WARNING:** These are database backups only
- Does NOT include uploaded files (/uploads/)
- Does NOT include application code
- For complete disaster recovery, also back up those items

📋 **Restoration:**
- Restore WILL DELETE the current database
- Automatic safety backup created before restore
- Always test restore from backup monthly

🔒 **Security:**
- Backup directory permissions: 700 (owner only)
- Store backup copies in separate physical location
- Encrypt backups for off-site storage

## Support

For issues or questions:
1. Check [../BACKUP_GUIDE.md](../BACKUP_GUIDE.md) for detailed documentation
2. Review backup logs: `tail -f logs/backup.log`
3. Run health check: `../backup_healthcheck.sh`

## Related Files

- `../backup.sh` - Main backup script
- `../restore.sh` - Database restore script
- `../backup_config.sh` - Configuration file
- `../BACKUP_GUIDE.md` - Comprehensive documentation
- `../BACKUP_CHEATSHEET.sh` - Quick command reference

---

**Last Backup:** (Check backup logs)  
**Next Backup:** (Check cron configuration)  
**Total Backups:** (Count with: `ls -1 database/ | wc -l`)

