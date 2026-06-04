# GFRC System — Security Policy & Guidelines

**Version:** 1.0 | **Last Updated:** 2026-05-28 | **Classification:** Internal

---

## Table of Contents
1. [Critical Security Requirements](#critical-requirements)
2. [Default Credentials](#default-credentials)
3. [Environment Configuration](#environment-configuration)
4. [Data Protection](#data-protection)
5. [Backup Security](#backup-security)
6. [Access Control](#access-control)
7. [Incident Response](#incident-response)
8. [Compliance](#compliance)

---

## Critical Requirements

### ⚠️ MUST DO BEFORE PRODUCTION

1. **Change Default Admin Password**
   ```bash
   # Using backend API
   POST /api/v1/auth/change-password
   {
     "current_password": "Admin@12345",
     "new_password": "SecurePassword123!@#",
     "password_confirmation": "SecurePassword123!@#"
   }
   ```
   - Minimum 12 characters
   - Mix of uppercase, lowercase, numbers, special characters
   - No dictionary words

2. **Enable HTTPS/TLS**
   - Generate SSL certificate: `certbot certonly -d yourdomain.com`
   - Update nginx configuration with SSL
   - Force HTTP to HTTPS redirect
   - Set HSTS header: `Strict-Transport-Security: max-age=31536000; includeSubDomains`

3. **Secure Environment Variables**
   - Never commit `.env` to repository
   - Use `.env.example` for template only
   - Store sensitive values in secret management system
   - Rotate keys quarterly

4. **Enable Session Encryption**
   - Set `SESSION_ENCRYPT=true`
   - Set `SESSION_SECURE=true` (HTTPS only)
   - Set `SESSION_HTTP_ONLY=true`
   - Set `SESSION_SAME_SITE=strict`

5. **Database Security**
   - Use PostgreSQL in production (not SQLite)
   - Enable SSL for database connection
   - Use strong passwords for database users
   - Apply principle of least privilege to DB accounts

---

## Default Credentials

### Initial Setup
```
Username: admin
Password: Admin@12345 (MUST CHANGE IMMEDIATELY)
```

### Change Password Steps
1. Login to the system with default credentials
2. Go to Settings → Security → Change Password
3. Enter current password: `Admin@12345`
4. Enter new secure password (12+ chars with complexity)
5. Confirm changes

### Password Requirements
- ✅ Minimum 12 characters
- ✅ At least 1 uppercase letter (A-Z)
- ✅ At least 1 lowercase letter (a-z)
- ✅ At least 1 number (0-9)
- ✅ At least 1 special character (!@#$%^&*)
- ❌ No user's name or username
- ❌ No dictionary words
- ❌ No sequential numbers (123456)
- ❌ No repeated characters (aaaaaa)

---

## Environment Configuration

### `.env.production` Template
```bash
# Application
APP_NAME=GFRC
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Security
APP_KEY=base64:GENERATE_NEW_KEY_WITH_php_artisan_key:generate
BCRYPT_ROUNDS=14

# Database (PostgreSQL Recommended)
DB_CONNECTION=pgsql
DB_HOST=your-secure-db-host
DB_PORT=5432
DB_DATABASE=gfrc_production
DB_USERNAME=gfrc_user
DB_PASSWORD=GENERATE_STRONG_PASSWORD

# Session & Cookies
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
SESSION_LIFETIME=480

# Security Headers
SECURE_HEADERS_ENABLED=true
HSTS_ENABLED=true
HSTS_MAX_AGE=31536000

# Rate Limiting
RATE_LIMIT_LOGIN_ATTEMPTS=5
RATE_LIMIT_LOGIN_WINDOW=900

# Backup
BACKUP_ENCRYPTION=true
BACKUP_COMPRESSION=true
BACKUP_RETENTION_DAYS=90
BACKUP_SCHEDULE_TIME=02:00

# Monitoring
LOG_CHANNEL=stack
LOG_LEVEL=warning
ENABLE_ERROR_MONITORING=true

# Redis (for cache & session)
REDIS_HOST=your-redis-host
REDIS_PASSWORD=GENERATE_STRONG_PASSWORD
REDIS_PORT=6379
REDIS_TLS=true
```

### Required Secret Management
Store these securely (NOT in .env):
- `APP_KEY` - Use `php artisan key:generate`
- `DB_PASSWORD` - 32-character random string
- `REDIS_PASSWORD` - 32-character random string
- `BACKUP_ENCRYPTION_KEY` - 32-character random string
- `JWT_SECRET` (if using JWT) - 32-character random string

### Environment Variable Validation
```php
// config/security.php should validate all critical variables
return [
    'has_app_key' => !empty(env('APP_KEY')),
    'is_debug_disabled' => env('APP_DEBUG') === false,
    'has_secure_session' => env('SESSION_ENCRYPT') === true,
    'uses_https' => env('APP_ENV') === 'production' && str_starts_with(env('APP_URL'), 'https'),
    'database_secured' => !empty(env('DB_PASSWORD')) && strlen(env('DB_PASSWORD')) >= 16,
];
```

---

## Data Protection

### Data Classification
| Level | Examples | Protection |
|-------|----------|-----------|
| **PUBLIC** | System version, feature list | No encryption needed |
| **INTERNAL** | Register definitions, user list | Access control, audit log |
| **CONFIDENTIAL** | Receipts, financial data | Encryption at rest, TLS in transit, audit log |
| **SECRET** | Database credentials, API keys | Vault/Secret manager only, never in logs |

### Encryption at Rest
```php
// Use Laravel's built-in encryption for sensitive data
protected $encrypted = ['bank_account_number', 'tax_id'];

// Encrypt specific fields before saving
$receipt->encrypted_field = Crypt::encryptString($value);
$decrypted = Crypt::decryptString($encrypted_field);
```

### Encryption in Transit
- ✅ All API endpoints use HTTPS/TLS 1.3
- ✅ Database connections use SSL
- ✅ Redis connections use TLS
- ✅ External API calls use verified certificates

### Key Rotation
- Database passwords: **Every 90 days**
- Session encryption keys: **Every 180 days**
- API keys: **Every 30 days**
- Backup encryption keys: **Every 90 days**

---

## Backup Security

### Encryption Standards
```php
// Backup encryption algorithm
$algorithm = 'AES-256-CBC';
$key = hash('sha256', env('BACKUP_ENCRYPTION_KEY'));
$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt($data, $algorithm, $key, 0, $iv);
```

### Backup Integrity Verification
Every backup includes:
- **SHA-256 Hash**: Detect corruption
- **HMAC Signature**: Detect tampering
- **CRC-32 Checksum**: Fast integrity check
- **Timestamp**: Versioning & expiration

### Backup File Structure
```
gfrc_backup_2026-05-28_140000.sql.enc
├── Header (100 bytes)
│   ├── Magic number (4 bytes)
│   ├── Version (1 byte)
│   ├── Algorithm (1 byte)
│   ├── Timestamp (8 bytes)
│   ├── SHA256 hash (32 bytes)
│   └── IV (16 bytes)
└── Encrypted payload
    ├── Compressed SQL dump
    ├── HMAC signature (32 bytes)
    └── CRC checksum (4 bytes)
```

### Backup Storage
- 📍 **Local**: `storage/backups/` (encrypted)
- 📍 **Cloud**: S3/Azure Blob Storage (versioning enabled)
- 📍 **Offline**: USB drive in secure location (monthly)

### Backup Verification Checklist
Before considering a backup valid:
```
✅ File exists and is readable
✅ Magic number matches (prevents version mismatch)
✅ Encryption algorithm is supported
✅ Timestamp is reasonable (not future date)
✅ SHA-256 hash validates
✅ Decryption succeeds
✅ SQL structure is valid
✅ HMAC signature matches
✅ CRC checksum passes
✅ Database can be imported without errors
```

---

## Access Control

### Role-Based Access Control (RBAC)
| Role | Permissions | Audit Level |
|------|-------------|------------|
| **super_admin** | All actions | Full audit |
| **manager** | Create, issue, cancel, view, report | Full audit |
| **cashier** | Create, issue, print, view | Full audit |
| **auditor** | View, report, audit-logs (read-only) | Read-only |
| **data_entry** | Create, view (no issue) | Full audit |

### IP Whitelisting (Optional)
```php
// .env
IP_WHITELIST_ENABLED=true
IP_WHITELIST=192.168.1.0/24,10.0.0.5

// Middleware checks
if (config('security.ip_whitelist_enabled')) {
    $allowed = config('security.ip_whitelist');
    if (!$request->ip() in $allowed) {
        abort(403, 'Access denied');
    }
}
```

### Session Limits
- **Concurrent sessions per user**: 3
- **Session timeout (idle)**: 30 minutes
- **Session timeout (absolute)**: 8 hours
- **Logout on password change**: Immediate

### Two-Factor Authentication (2FA)
Optional but recommended for admin accounts:
```
1. Setup: User enables 2FA in security settings
2. TOTP: Use Google Authenticator or similar
3. Backup codes: Generated and stored securely
4. Fallback: SMS option (optional)
```

---

## Incident Response

### Security Incident Classification
| Severity | Impact | Response Time | Example |
|----------|--------|---|---------|
| **CRITICAL** | System down, data loss | 15 minutes | Ransomware, DB compromise |
| **HIGH** | Data breach, unauthorized access | 1 hour | Stolen credentials, SQL injection |
| **MEDIUM** | Audit trail gap, configuration error | 4 hours | Misconfigured permissions |
| **LOW** | Minor bugs, documentation issues | 24 hours | Typo in error message |

### Incident Response Steps
1. **Detect** → Monitor logs, alerts, user reports
2. **Assess** → Determine severity and scope
3. **Contain** → Isolate affected systems
4. **Investigate** → Analyze logs and audit trail
5. **Recover** → Restore from backup if needed
6. **Document** → Create incident report
7. **Improve** → Update security controls

### Emergency Contacts
```
Security Team Lead: [contact info]
Database Admin: [contact info]
Network Admin: [contact info]
Management: [contact info]
```

### Backup Restore Procedure
```bash
# 1. Verify backup integrity
php artisan backup:verify backup_filename.sql.enc

# 2. Create checkpoint (backup current state)
php artisan backup:create --name "pre_restore_checkpoint"

# 3. Restore from backup (with validation)
php artisan backup:restore backup_filename.sql.enc \
  --verify \
  --check-data-integrity \
  --dry-run

# 4. If dry-run succeeds, proceed with restore
php artisan backup:restore backup_filename.sql.enc \
  --force \
  --notify-admin

# 5. Verify restored data
php artisan backup:verify-restore

# 6. Document incident
Activity::create([
  'event' => 'backup_restored',
  'reason' => 'incident_recovery',
  'backup_file' => 'backup_filename.sql.enc',
])
```

---

## Compliance

### Data Protection Standards
- **GDPR**: Personal data retention, deletion rights, DPA agreements
- **PCI DSS**: If handling credit cards (we don't)
- **ISO 27001**: Information security management
- **SOC 2**: Security, availability, integrity controls

### Audit Requirements
- ✅ All financial transactions logged with: user, timestamp, IP, action, old/new values
- ✅ Monthly audit log review
- ✅ Quarterly security assessment
- ✅ Annual penetration testing
- ✅ Disaster recovery drill: Quarterly

### Documentation Requirements
Keep for audit purposes:
- System configuration changes (2 years)
- User access logs (1 year)
- Backup logs (3 years)
- Security incidents (3 years)
- Disaster recovery drills (3 years)

### Reporting Requirements
- Monthly security summary to management
- Quarterly compliance report
- Annual security assessment
- Incident reports within 24 hours

---

## Security Checklist

### Before Going to Production
- [ ] Change default admin password
- [ ] Enable HTTPS with valid SSL certificate
- [ ] Configure `.env.production` with strong secrets
- [ ] Enable session encryption
- [ ] Switch to PostgreSQL database
- [ ] Enable backup encryption
- [ ] Set up automated backups
- [ ] Configure IP whitelisting (if applicable)
- [ ] Enable audit logging
- [ ] Set up monitoring and alerts
- [ ] Create incident response plan
- [ ] Document all security procedures
- [ ] Conduct security review meeting
- [ ] Perform penetration testing

### Monthly Maintenance
- [ ] Review audit logs for anomalies
- [ ] Verify backup integrity
- [ ] Check session timeouts are working
- [ ] Update security patches
- [ ] Review user access permissions
- [ ] Test backup restoration

### Quarterly Maintenance
- [ ] Rotate database passwords
- [ ] Rotate API keys
- [ ] Security assessment
- [ ] Disaster recovery drill
- [ ] Update security policies

---

## Contact & Resources
- **Security Questions**: security@organization.com
- **Bug Bounty**: security@organization.com
- **Documentation**: See BACKUP_STRATEGY.md, DEPLOYMENT.md
- **Incident Response**: See INCIDENT_RESPONSE.md
