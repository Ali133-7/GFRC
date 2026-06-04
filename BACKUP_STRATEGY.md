# GFRC System — Backup & Disaster Recovery Strategy

**Version:** 1.0 | **Last Updated:** 2026-05-28 | **Classification:** Internal

---

## Executive Summary

This document outlines the complete backup and disaster recovery (DR) strategy for the GFRC system. It covers:
- ✅ Backup procedures (what, when, where, how)
- ✅ Encryption and security measures
- ✅ Data integrity verification
- ✅ Disaster recovery procedures
- ✅ Failure scenarios and recovery time objectives (RTO)

---

## Table of Contents
1. [Backup Strategy](#backup-strategy)
2. [Backup Schedule](#backup-schedule)
3. [Encryption & Security](#encryption--security)
4. [Data Integrity Verification](#data-integrity-verification)
5. [Recovery Procedures](#recovery-procedures)
6. [Failure Scenarios](#failure-scenarios)
7. [Monitoring & Alerts](#monitoring--alerts)

---

## Backup Strategy

### What Gets Backed Up

| Component | Type | Frequency | Retention |
|-----------|------|-----------|-----------|
| PostgreSQL database | Full + incremental | Daily | 90 days |
| File uploads | Incremental | Daily | 90 days |
| Configuration files | On change | Event-driven | 30 days |
| Audit logs | Continuous | Real-time sync | 3 years (archive) |
| Environment settings | On change | Event-driven | 365 days |

### What Does NOT Get Backed Up
- ❌ Session files (auto-generated, temporary)
- ❌ Cache files (can be regenerated)
- ❌ Log files > 30 days (moved to archive)
- ❌ Temporary processing files
- ❌ Dependencies (`vendor/`, `node_modules/`)

### Backup Architecture: 3-2-1 Rule

```
┌─────────────────────────────────────────────────────────┐
│                    PRIMARY DATA                          │
│              (PostgreSQL database)                       │
└─────────────┬───────────────────────────────────────────┘
              │
    ┌─────────┴──────────┬──────────────┐
    │                    │              │
    ▼                    ▼              ▼
┌────────┐           ┌────────┐    ┌────────┐
│ Local  │           │ Cloud  │    │Offline │
│Backup  │           │Backup  │    │Backup  │
│(SSD)   │           │(S3)    │    │(USB)   │
└────────┘           └────────┘    └────────┘
  Daily                Daily        Monthly
  (encrypted)       (encrypted)   (encrypted)
  7-day retention   90-day        365-day
```

### Storage Locations

#### 1. Local Storage (Hot)
- **Location**: `/storage/backups/` on server
- **Frequency**: Daily full backup + hourly incremental
- **Retention**: 7 days (rolling)
- **Encryption**: AES-256-CBC with HMAC
- **Access**: Localhost only via SSH

```
/storage/backups/
├── gfrc_backup_2026-05-28_020000.sql.enc
├── gfrc_backup_2026-05-28_020000.sql.enc.sig
├── gfrc_backup_2026-05-28_020000.sql.enc.crc
├── gfrc_backup_2026-05-27_020000.sql.enc
└── ...
```

#### 2. Cloud Storage (Warm)
- **Provider**: AWS S3 / Azure Blob Storage / Google Cloud
- **Bucket Configuration**:
  - Version enabled (keep old versions)
  - Server-side encryption (AES-256)
  - Access logging enabled
  - MFA delete required for permanent deletion
  
- **Frequency**: Daily at 02:00 UTC
- **Retention**: 90 days (configurable)
- **Redundancy**: Multi-region replication (optional)

```
s3://gfrc-backups-prod/
├── 2026/05/
│   ├── gfrc_backup_2026-05-28_020000.sql.enc
│   ├── gfrc_backup_2026-05-28_020000.sql.enc.sig
│   ├── gfrc_backup_2026-05-27_020000.sql.enc
│   └── ...
└── metadata.json
```

#### 3. Offline Storage (Cold)
- **Media**: Encrypted USB drive or external HDD
- **Location**: Secure physical location (safe, vault)
- **Frequency**: Monthly full backup + annual
- **Retention**: 3 years minimum
- **Encryption**: AES-256-CBC with dual-layer encryption key
- **Backup Manifest**: Include detailed restoration instructions

```
[USB Drive] 1TB Encrypted
├── README_RESTORE.txt
├── ENCRYPTION_KEY.txt.gpg (GPG encrypted)
├── gfrc_backup_2026-04-28_full.sql.enc
├── gfrc_backup_2026-05-28_full.sql.enc
└── manifest.json
```

---

## Backup Schedule

### Daily Backup Window
```
Time: 02:00 - 04:00 UTC (off-peak hours)
Location: Local SSD → Cloud S3 → Backup server

Steps:
1. 02:00 → Create full database dump
2. 02:15 → Encrypt dump with AES-256-CBC
3. 02:20 → Generate HMAC signature
4. 02:25 → Calculate CRC32 checksum
5. 02:30 → Upload to cloud storage
6. 02:45 → Verify upload integrity
7. 02:50 → Update backup manifest
8. 03:00 → Log backup completion
9. 03:05 → Rotate old backups (> 7 days delete local, > 90 days delete cloud)
10. 03:15 → Send confirmation email
```

### Hourly Incremental Backup (Optional)
```
Schedule: Every hour at :00 (on the hour)
Duration: < 5 minutes
Scope: Only data changed since last backup
Storage: Local backup partition only
Retention: 24 hours (rolling)
```

### Monthly Offline Backup
```
Schedule: Last Sunday of month at 23:00 UTC
Duration: Manual process, ~2 hours
Scope: Full database + configuration + audit logs
Storage: Encrypted USB drive + secure location
Process:
  1. Create full backup
  2. Verify integrity (100% checksum)
  3. Encrypt with dual-layer encryption
  4. Copy to USB with manifest
  5. GPG encrypt the encryption key
  6. Store in physical safe
  7. Document location & access procedures
```

### Quarterly Rotation
```
Schedule: Last day of quarter (Mar 31, Jun 30, Sep 30, Dec 31)
Action: Archive old backups to long-term cold storage
Retention: Latest backup kept, older ones archived
Verification: Full integrity check before archival
```

---

## Encryption & Security

### Encryption Algorithm Specifications

#### At-Rest Encryption
```php
Algorithm: AES-256-CBC (Advanced Encryption Standard, 256-bit key, CBC mode)
Key Derivation: PBKDF2 with SHA-256 (100,000 iterations)
IV: Random 16 bytes (included in backup header)
Authentication: HMAC-SHA256

// Encryption process
$key = hash_pbkdf2('sha256', env('BACKUP_ENCRYPTION_KEY'), 'salt', 100000, 32);
$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
$hmac = hash_hmac('sha256', $encrypted, $key, true);
$crc32 = crc32($plaintext);
```

#### In-Transit Encryption
```
TLS 1.3+ for all connections
- Database replication: SSL mandatory
- Cloud upload: HTTPS + signature verification
- Offline storage: Encrypted device + GPG encryption
```

### Key Management

#### Encryption Key Storage
```
Production Environment:
┌─────────────────┐
│  Secret Manager │  ← AWS Secrets Manager / HashiCorp Vault
│  (Master Key)   │
└─────────────────┘
         ↓ (fetched at runtime)
┌─────────────────┐
│  Environment    │
│  (RAM only)     │
└─────────────────┘
         ↓
┌─────────────────┐
│  Backup Process │
│  (uses key)     │
└─────────────────┘
         ↓
┌─────────────────┐
│  Encrypted File │
│  (secure)       │
└─────────────────┘
```

#### Key Rotation Schedule
- **Backup encryption key**: Rotate every 90 days
- **Master encryption key**: Rotate annually
- **Offline backup key**: Rotate biennially

**Rotation Procedure**:
```bash
1. Generate new encryption key
2. Create new backup with new key (keep old key in vault)
3. Verify new backup restores correctly
4. Re-encrypt old backups with new key (optional, if needed)
5. Document key rotation in audit log
6. Update KMS/secret manager
```

#### Key Escrow (For Disaster Recovery)
Store encrypted backups of encryption keys:
```
Primary Key:    /vault/backup_key_primary.gpg
Secondary Key:  /vault/backup_key_secondary.gpg
Master Key:     /vault/master_key.gpg

Access Protocol:
1. Requires 2 of 3 authorized personnel
2. Each key stored separately
3. Retrieved only during DR drills or incidents
4. All key access logged and audited
```

---

## Data Integrity Verification

### Pre-Backup Checks
```php
// 1. Database consistency
PRAGMA integrity_check;
SELECT COUNT(*) FROM receipts; // snapshot for comparison

// 2. Required tables exist
PRAGMA table_info(receipts);
PRAGMA table_info(receipt_items);
PRAGMA table_info(registers);

// 3. Foreign key constraints valid
PRAGMA foreign_key_check;

// 4. Disk space available
DISK_FREE >= BACKUP_SIZE + 2GB

// 5. Database is accessible
Connection timeout < 5s
```

### Backup Integrity Verification
```php
class BackupIntegrityChecker {
    public function verify(BackupFile $backup): IntegrityReport {
        return [
            'magic_number_valid' => $this->checkMagicNumber(),
            'version_compatible' => $this->checkVersion(),
            'timestamp_reasonable' => $this->checkTimestamp(),
            'decryption_successful' => $this->tryDecrypt(),
            'hmac_signature_valid' => $this->verifyHmac(),
            'crc32_valid' => $this->verifyCrc32(),
            'sql_structure_valid' => $this->validateSqlStructure(),
            'import_test_passed' => $this->testImport(),
        ];
    }
}
```

### Post-Restoration Checks
```bash
Step 1: Verify row counts
SELECT 'receipts' as table, COUNT(*) FROM receipts UNION ALL
SELECT 'users', COUNT(*) FROM users UNION ALL
SELECT 'registers', COUNT(*) FROM registers;

Step 2: Verify financial totals
SELECT 
  register_id,
  SUM(amount) as total_before,
  (SELECT SUM(amount) FROM archived_receipts WHERE register_id=?) as total_after
FROM receipts
GROUP BY register_id;

Step 3: Verify audit trail
SELECT COUNT(*) FROM activity_log WHERE created_at >= restore_time;

Step 4: Verify relationships
SELECT COUNT(*) FROM receipts WHERE register_id NOT IN (SELECT id FROM registers);
SELECT COUNT(*) FROM receipt_items WHERE receipt_id NOT IN (SELECT id FROM receipts);

Step 5: Application health check
GET /api/v1/health
GET /api/v1/auth/me
```

---

## Recovery Procedures

### Scenario 1: Restore Single Database (No Data Loss)
```bash
Time to Restore: 15 minutes
Step 1: Download backup from S3
  aws s3 cp s3://gfrc-backups/backup_2026-05-28_020000.sql.enc ./

Step 2: Verify backup integrity
  php artisan backup:verify backup_2026-05-28_020000.sql.enc

Step 3: Dry-run restoration (test without committing)
  php artisan backup:restore backup_2026-05-28_020000.sql.enc --dry-run

Step 4: Restore backup (if dry-run succeeds)
  php artisan backup:restore backup_2026-05-28_020000.sql.enc --force

Step 5: Verify data integrity
  php artisan backup:verify-restore

Step 6: Update application
  php artisan migrate:status
  php artisan cache:clear
  
Step 7: Confirm system operational
  curl -I https://app.com/api/v1/health
```

### Scenario 2: Full System Failure (Backup to New Server)
```bash
Time to Restore: 2 hours
Prerequisites: New server with PostgreSQL, PHP, Docker

Step 1: Provision new server
  - Clone infrastructure code
  - Install dependencies
  - Configure environment variables

Step 2: Download offline backup from secure storage
  # Physical retrieval or remote
  - Decrypt USB drive
  - Verify manifest

Step 3: Create temporary database
  - Create new PostgreSQL database
  - Import old schema

Step 4: Restore backup in phases
  a. Restore schema only (structure)
     pg_restore --schema-only backup.dump | psql gfrc_db
  
  b. Restore data in transactions
     pg_restore -d gfrc_db backup.dump
  
  c. Verify each phase
     SELECT COUNT(*) FROM receipts;

Step 5: Verify financial data integrity
  - Total receipts match before
  - All audit logs present
  - No orphaned records

Step 6: Update DNS to new server
  - Change CNAME to new IP
  - Update SSL certificates
  - Flush CDN cache

Step 7: Monitor for issues
  - Check error logs
  - Verify user access
  - Confirm report accuracy
```

### Scenario 3: Data Corruption (Partial Recovery)
```bash
Time to Restore: 4 hours
Strategy: Restore specific tables or date range

Step 1: Identify affected data
  - Determine corruption scope (which table/date range)
  - Calculate data loss impact
  - Notify stakeholders

Step 2: Choose backup point
  - Select backup before corruption occurred
  - Verify backup integrity
  - Test restoration in staging

Step 3: Selective restore (table-level)
  # Extract specific table from backup
  pg_restore -t receipts backup.dump > receipts.sql
  psql gfrc_db < receipts.sql

Step 4: Reconcile new transactions
  - Identify transactions after restore point
  - Export from backup
  - Manual review and re-entry if needed

Step 5: Verify financial balance
  - Recalculate all totals
  - Cross-check with manual records
  - Update audit log with incident

Step 6: Resume operations
  - Inform users of recovery completion
  - Publish incident report
```

### Scenario 4: Ransomware/Security Breach
```bash
Time to Restore: 8 hours
Strategy: Complete system rebuild

Step 1: Isolate affected systems
  - Disconnect from network
  - Stop all services
  - Preserve evidence for forensics

Step 2: Determine breach point
  - Review audit logs (offline copy)
  - Identify when breach occurred
  - Find clean backup point

Step 3: Prepare clean recovery environment
  - Provision new server infrastructure
  - New database instance
  - New encryption keys
  - Clean OS installation

Step 4: Restore from offline backup
  - Use offline USB backup (most secure)
  - Verify integrity quintuple-check
  - Restore to isolated network first

Step 5: Security hardening
  - Update all passwords
  - Rotate all encryption keys
  - Patch all vulnerabilities
  - Re-configure access controls

Step 6: Operational recovery
  - Connect to network with strict firewall
  - Enable enhanced monitoring
  - Run full security audit
  - Verify no backdoors

Step 7: Post-incident actions
  - Notify relevant authorities
  - Forensic investigation
  - User communication
  - Policy updates
```

---

## Failure Scenarios

### Scenario Matrix: RTO & RPO

| Scenario | Cause | RTO | RPO | Recovery Method |
|----------|-------|-----|-----|-----------------|
| **Disk Failure** | SSD hardware failure | 2 hours | 24 hours | Restore from cloud |
| **Database Corruption** | Software bug / crash | 30 minutes | 1 hour | Restore + incremental replay |
| **Ransomware** | Security breach | 8 hours | 1 day | Rebuild from offline backup |
| **Accidental Delete** | User error | 15 minutes | 1 hour | Restore from local backup |
| **Network Outage** | ISP/network failure | 4 hours | 12 hours | Failover to backup location |
| **Complete Data Loss** | Multiple failures | 24 hours | 30 days | Rebuild from offline backup |

### Scenario 1: Local Disk Failure
```
Impact: Cannot access local backups
Recovery: Use cloud backup
RTO: 2 hours
RPO: 24 hours

Action:
  1. Provision new storage
  2. Download from S3
  3. Restore database
  4. Verify integrity
```

### Scenario 2: S3 Bucket Compromised
```
Impact: All cloud backups potentially deleted/modified
Recovery: Use monthly offline backup
RTO: 8 hours
RPO: 30 days (since last monthly)

Action:
  1. Notify AWS security team
  2. Retrieve offline USB from vault
  3. Restore from offline backup
  4. Investigate breach
  5. Rotate all credentials
```

### Scenario 3: Encryption Key Lost
```
Impact: Cannot decrypt any backups
Recovery: Key escrow system
RTO: 4 hours
RPO: 0 (key stored offline)

Action:
  1. Retrieve key from escrow vault
  2. Verify key authentication
  3. Retrieve backup
  4. Decrypt with recovered key
  5. Restore to database
```

---

## Monitoring & Alerts

### Backup Success Indicators
```
✅ Backup completed within time window (before 04:00 UTC)
✅ Backup file size within expected range (±10%)
✅ Encryption completed without errors
✅ Upload to cloud succeeded
✅ Integrity verification passed
✅ Email notification sent to admin
```

### Backup Failure Alerts

| Condition | Severity | Action |
|-----------|----------|--------|
| Backup not completed in 4 hours | CRITICAL | Page on-call engineer |
| Integrity check failed | CRITICAL | Immediately retry |
| Cloud upload failed | HIGH | Retry, keep local copy |
| Encryption key unavailable | CRITICAL | Use key escrow system |
| Disk space low (< 1GB) | HIGH | Archive old backups |
| HMAC signature invalid | CRITICAL | Investigate tampering |

### Monitoring Dashboard
```
Dashboard: /admin/backups/monitor

Displays:
- Last backup timestamp
- Backup file size trend (MB)
- Backup duration (minutes)
- Success rate (%) - 7 day rolling
- Oldest backup in local storage (days)
- Oldest backup in cloud storage (days)
- Encryption status (enabled/disabled)
- Key rotation schedule
- Next scheduled backup
```

### Monthly Backup Report
```
Subject: GFRC Backup Report - May 2026

Summary:
- Total backups: 31
- Successful: 31 (100%)
- Failed: 0
- Average duration: 18 minutes
- Average size: 2.3 GB
- Total cloud storage used: 65 GB

Backups:
✅ May 1 - Size: 2.2 GB - Status: OK
✅ May 2 - Size: 2.4 GB - Status: OK
✅ May 3 - Size: 2.3 GB - Status: OK
...

Key Rotations:
- Last rotation: 2026-02-28
- Next rotation: 2026-05-28 (SCHEDULED)

Incidents:
- None reported

Recommendations:
- Monitor: Backup size increasing trend
- Action: Consider archiving old receipts
```

---

## Disaster Recovery Drill

### Quarterly DR Drill Schedule
```
Q1 (Jan-Mar): Scenario 1 → Restore from local backup
Q2 (Apr-Jun): Scenario 3 → Selective table restore
Q3 (Jul-Sep): Scenario 2 → Restore from cloud backup
Q4 (Oct-Dec): Scenario 4 → Full system rebuild + offline backup
```

### DR Drill Checklist
```
Before:
- [ ] Notify all stakeholders
- [ ] Schedule 2-hour maintenance window
- [ ] Set up isolated test environment
- [ ] Document baseline metrics

During:
- [ ] Execute recovery procedure
- [ ] Verify data integrity
- [ ] Test application functionality
- [ ] Confirm financial totals match
- [ ] Time the entire process

After:
- [ ] Complete incident report
- [ ] Document any issues found
- [ ] Update recovery procedures if needed
- [ ] Publish results to team
- [ ] Schedule follow-up training

Expected Results:
- RTO met (< 2 hours)
- RPO verified (< 24 hours)
- No data loss
- All systems operational
```

---

## References
- See: SECURITY.md (Encryption standards)
- See: DEPLOYMENT.md (Infrastructure setup)
- See: INCIDENT_RESPONSE.md (Emergency procedures)
