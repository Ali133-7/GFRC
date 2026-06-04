# GFRC System — Maintenance & Operations Guide

**Version:** 1.0 | **Last Updated:** 2026-05-28 | **Audience:** DevOps/SysAdmin

---

## Maintenance Overview

This document outlines routine maintenance tasks to keep the GFRC system secure, performant, and reliable.

---

## Daily Maintenance (5 minutes)

### 1. Monitor Backup Success

```bash
# Check if backup completed
ls -lah /var/backups/gfrc/local/ | head -1
# Should show today's date with reasonable size (> 1 GB)

# Check backup log
tail -10 /var/log/gfrc_backup.log

# Alert if:
✗ No backup from today
✗ File size is 0 bytes (corrupted)
✗ Error messages in log
```

### 2. Check System Health

```bash
# Load average (should be < 4 on 8-core system)
uptime

# Memory usage (should be < 80%)
free -h | grep Mem

# Disk usage (should be < 80%)
df -h / | tail -1

# Database connection
psql -h localhost -U gfrc_prod -d gfrc_production -c "SELECT 1"

# Redis connection
redis-cli ping
```

### 3. Review Error Logs

```bash
# Check for errors in application logs
tail -50 /var/www/gfrc/backend/storage/logs/laravel.log | grep -i error

# Check for errors in web server
tail -50 /var/log/nginx/error.log | grep -i error

# Check for database errors
journalctl -u postgresql -n 20 | grep -i error
```

---

## Weekly Maintenance (30 minutes)

### Monday: Security Review

```bash
# Check for failed login attempts
grep "login_failed" /var/www/gfrc/backend/storage/logs/laravel.log | wc -l

# List active sessions
psql gfrc_production -c "SELECT id, user_id, ip_address, expires_at FROM sessions WHERE expires_at > NOW();"

# Check audit log for unusual activity
psql gfrc_production -c "SELECT * FROM activity_log WHERE created_at > NOW() - INTERVAL '7 days' ORDER BY created_at DESC LIMIT 50;"

# Alert if:
✗ > 100 failed logins in a day
✗ Login from unusual IP address
✗ Permissions were escalated unexpectedly
✗ Large data exports without approval
```

### Tuesday: Backup Verification

```bash
# Test restore from latest backup (in staging environment)
# Do NOT restore in production!

cd /var/backups/gfrc/staging/
sudo -u www-data php artisan backup:restore \
  /var/backups/gfrc/local/$(ls -t /var/backups/gfrc/local/ | head -1) \
  --verify \
  --check-data-integrity

# Alert if:
✗ Restore test fails
✗ Data integrity check fails
✗ Restore takes > 30 minutes
```

### Wednesday: Database Optimization

```bash
# Analyze query performance
psql gfrc_production << EOF
-- Find slow queries
SELECT query, calls, total_time, mean_time 
FROM pg_stat_statements 
WHERE mean_time > 100 
ORDER BY mean_time DESC LIMIT 10;

-- Vacuum and analyze
VACUUM ANALYZE;

-- Reindex if needed
REINDEX DATABASE gfrc_production;
EOF
```

### Thursday: Update Check

```bash
# Check for available security updates
sudo apt-get update -y
sudo apt-cache policy php8.3 postgresql-15 nginx

# List pending updates
sudo apt list --upgradable

# If critical security updates available:
# 1. Test in staging
# 2. Schedule maintenance window
# 3. Update in production
# 4. Verify services restart properly
```

### Friday: Full System Check

```bash
# Comprehensive health check
./scripts/health-check.sh

# Verify all components:
□ Web server (nginx)
□ App server (PHP-FPM)
□ Database (PostgreSQL)
□ Cache (Redis)
□ Backups
□ SSL certificate
□ DNS resolution
□ Firewall rules
```

---

## Monthly Maintenance (2 hours)

### First Monday: Security Audit

```bash
# Run security assessment
./scripts/security-audit.sh

# Checks:
□ All passwords have been changed
□ No default credentials remain
□ SSH keys are properly secured
□ File permissions are correct
□ No world-readable sensitive files
□ All user accounts are active
□ Old sessions are cleared

# Review results and fix any issues
```

### Second Monday: Database Maintenance

```bash
# Full maintenance cycle
psql gfrc_production << EOF
-- 1. Identify bloated tables
SELECT 
  schemaname, tablename, 
  ROUND(pg_total_relation_size(schemaname||'.'||tablename) / 1024 / 1024) as size_mb
FROM pg_tables 
WHERE schemaname = 'public' 
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- 2. Vacuum full (LOCKS TABLE - do during maintenance window)
VACUUM FULL ANALYZE;

-- 3. Reindex all indexes
REINDEX DATABASE gfrc_production;

-- 4. Check for unused indexes
SELECT schemaname, tablename, indexname, idx_scan 
FROM pg_stat_user_indexes 
WHERE idx_scan = 0;
EOF
```

### Third Monday: Capacity Planning

```bash
# Check growth trends
./scripts/capacity-report.sh

# Metrics to monitor:
□ Database size growth (MB/month)
□ User count growth
□ Receipt volume growth
□ Storage space remaining
□ Network bandwidth usage

# Action if:
✗ Database growing > 10% per month
✗ Storage < 20% remaining
✗ Bandwidth approaching limit
  → Plan for scaling/archival
```

### Fourth Monday: Documentation Update

```bash
# Review and update documentation
□ Update DEPLOYMENT.md if infrastructure changed
□ Update SECURITY.md if policies changed
□ Update this file with new maintenance tasks
□ Update incident procedures if learned from issues
□ Update runbooks for operational procedures

# Commit changes
git add -A
git commit -m "Monthly documentation update"
git push
```

---

## Quarterly Maintenance (1 day)

### End of Quarter: Full Security Assessment

```bash
# Run comprehensive security scan
./scripts/full-security-assessment.sh

# Review:
□ SSL certificate expiration (must be > 30 days away)
□ All user permissions (RBAC review)
□ Firewall rules (still current and needed?)
□ Backup encryption keys (still secure?)
□ Session security settings
□ Database access controls
□ API key exposure risk
□ Code vulnerabilities
□ Dependency updates

# Action on findings:
□ Create tickets for any issues
□ Schedule fixes before next quarter
□ Update security policies
```

### Disaster Recovery Drill

```bash
# Full system recovery test
# Duration: 4 hours
# Do in staging environment ONLY

1. Prepare:
   □ Notify all stakeholders
   □ Schedule maintenance window
   □ Backup current staging data

2. Execute:
   □ Download backup file
   □ Verify backup integrity
   □ Restore to staging
   □ Verify all services start
   □ Test application functionality
   □ Confirm all data restored
   □ Run integrity checks

3. Document:
   □ Time to restore: ____ minutes
   □ Issues encountered: ____
   □ Fixes needed: ____
   □ Update procedures if needed

4. Report:
   □ Share results with team
   □ Update RTO/RPO if different
   □ Plan for next drill
```

### Penetration Testing (Annual - Q4)

```bash
# Hire external security firm for penetration test
# Scope:
□ Web application security
□ API security
□ Database security
□ Infrastructure security
□ Social engineering

# Post-test actions:
□ Review findings
□ Create remediation plan
□ Fix critical issues within 30 days
□ Fix high-risk issues within 90 days
□ Document lessons learned
□ Update security procedures
```

---

## Specific Maintenance Tasks

### SSL Certificate Renewal

```bash
# Automatic (Let's Encrypt with certbot)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Manual renewal (if needed)
sudo certbot renew --force-renewal

# Verify certificate
openssl x509 -in /etc/letsencrypt/live/yourdomain.com/cert.pem -text -noout

# Alert before expiration (add to cron)
0 9 1 * * certbot expire-soon --agree-tos --email admin@yourdomain.com

# If renewal fails:
sudo certbot --standalone certonly -d yourdomain.com
# Then update nginx configuration
sudo systemctl restart nginx
```

### Password Rotation Schedule

| Item | Frequency | Last Date | Next Date |
|------|-----------|-----------|-----------|
| Admin user | Quarterly | 2026-05-28 | 2026-08-28 |
| Database user | Quarterly | 2026-05-28 | 2026-08-28 |
| Redis password | Quarterly | 2026-05-28 | 2026-08-28 |
| Backup key | Quarterly | 2026-05-28 | 2026-08-28 |

**Rotation procedure**:
```bash
# 1. Generate new password (32 characters)
openssl rand -base64 32

# 2. Update secret manager / .env.production
# 3. Update database/service
# 4. Test with new password
# 5. Verify old password still works (during grace period)
# 6. Disable old password after 24 hours
# 7. Log rotation in audit trail
```

### Database Backup Archival

```bash
# Monthly: Archive old backups
# Keep last 7 days locally
# Keep last 90 days in cloud
# Archive older to cold storage (USB/tape)

# Script:
#!/bin/bash
BACKUP_DIR="/var/backups/gfrc/local"
CLOUD_DIR="s3://gfrc-backups"

# Move backups older than 7 days to cloud archive
find $BACKUP_DIR -mtime +7 -exec aws s3 mv {} $CLOUD_DIR/archive/ \;

# After 90 days in cloud, archive to offline storage
# (done manually as part of quarterly process)
```

### Log Rotation

```bash
# Configured in /etc/logrotate.d/gfrc

# Manual trigger (if needed)
sudo logrotate -f /etc/logrotate.d/gfrc

# Verify
sudo logrotate -d /etc/logrotate.d/gfrc

# Cleanup old logs
find /var/log -name "*.log.*" -mtime +30 -delete
```

### Performance Tuning

```bash
# Monitor and optimize based on metrics
htop  # Interactive process monitoring
iotop # Disk I/O monitoring
nethogs # Network monitoring

# Common tuning points:

# PHP-FPM process pool
# If: memory usage high
# Action: Reduce pm.max_children or increase swap

# PostgreSQL shared buffers
# If: cache hit ratio < 99%
# Action: Increase shared_buffers (25% of RAM)

# Redis memory
# If: evictions happening
# Action: Increase maxmemory or reduce TTL

# Nginx worker connections
# If: connection limits reached
# Action: Increase worker_connections
```

---

## Monitoring & Alerts

### Key Metrics to Monitor

| Metric | Warning | Critical |
|--------|---------|----------|
| CPU Usage | > 70% | > 90% |
| Memory Usage | > 75% | > 90% |
| Disk Usage | > 80% | > 95% |
| Backup Success | None | Failed |
| Database Conn | > 80 | > 95 |
| Redis Memory | 80% | 95% |
| SSL Expires | < 30 days | < 7 days |
| Response Time | > 500ms | > 2000ms |

### Alert Channels

```
Priority | Channel | Response Time
---------|---------|---------------
CRITICAL | PagerDuty + SMS | < 5 minutes
HIGH | Slack #alerts + Email | < 15 minutes
MEDIUM | Slack #alerts | < 1 hour
LOW | Email digest | Daily
```

### Dashboard

Setup monitoring dashboard showing:
- System metrics (CPU, memory, disk, network)
- Application metrics (requests/sec, response time, errors)
- Database metrics (connections, query time, locks)
- Backup status (last successful, next scheduled)
- Security metrics (failed logins, audit log entries)

---

## Troubleshooting Common Issues

### Issue: High Database Load

```bash
# 1. Check current connections
psql gfrc_production -c "SELECT * FROM pg_stat_activity WHERE state != 'idle';"

# 2. Kill long-running queries (if needed)
SELECT pg_terminate_backend(pid) FROM pg_stat_activity 
WHERE duration > interval '10 minutes' AND pid <> pg_backend_pid();

# 3. Check for missing indexes
SELECT schemaname, tablename, indexname, idx_scan 
FROM pg_stat_user_indexes 
WHERE idx_scan = 0;

# 4. Run VACUUM ANALYZE
VACUUM ANALYZE;

# 5. Check slow query log
SELECT query, calls, total_time FROM pg_stat_statements 
ORDER BY mean_time DESC LIMIT 10;
```

### Issue: Out of Disk Space

```bash
# 1. Find large files
du -sh /* | sort -rh | head -20

# 2. Check backup directory
du -sh /var/backups/gfrc/*

# 3. Archive old backups
aws s3 sync /var/backups/gfrc/local s3://gfrc-backups/archive/

# 4. Clear cache
redis-cli FLUSHALL

# 5. Clean old logs
find /var/log -mtime +30 -delete
```

### Issue: Memory Leak

```bash
# 1. Check processes
top -b -n 1 | sort -k6 -rh | head -10

# 2. Check PHP-FPM
ps aux | grep php-fpm | awk '{print $6}' | paste -sd+ | bc

# 3. Check for large queries
psql gfrc_production -c "EXPLAIN ANALYZE SELECT * FROM large_table;"

# 4. Restart service
systemctl restart php8.3-fpm

# 5. Monitor improvement
watch 'free -h'
```

---

## Operational Runbooks

### Runbook 1: Add New User

```bash
# 1. Create user via API
curl -X POST https://yourdomain.com/api/v1/users \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "username": "john.doe",
    "email": "john@example.com",
    "password": "TempPassword123!@#",
    "is_active": true
  }'

# 2. Assign roles
curl -X PUT https://yourdomain.com/api/v1/users/{id}/roles \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{"roles": ["cashier"]}'

# 3. User must change password on first login

# 4. Log in audit trail ✓ (automatic)
```

### Runbook 2: Rotate All Passwords

```bash
# Quarterly procedure

# 1. Admin password
# 2. Database password
# 3. Redis password
# 4. Backup encryption key

# Execute with orchestration tool (Ansible/Terraform)
# Verify all services can connect with new passwords
# Keep old passwords in escrow for 24 hours
# Then delete old passwords
```

### Runbook 3: Scale Database

```bash
# If database reaches capacity:

# 1. Increase PostgreSQL shared_buffers
# 2. Archive old receipts to separate table
# 3. Add read replicas for reporting
# 4. Implement connection pooling (PgBouncer)
# 5. Monitor performance improvement
```

---

## Documentation Links

- SECURITY.md - Security guidelines and policies
- BACKUP_STRATEGY.md - Backup procedures and recovery
- DEPLOYMENT.md - Initial deployment and setup
- INCIDENT_RESPONSE.md - Emergency procedures
- PROJECT_STATUS.md - Project overview

---

## Maintenance Log

| Date | Task | Status | Notes |
|------|------|--------|-------|
| 2026-05-28 | Initial setup | ✓ | Document created |
| | | | |

---

*Reviewed: 2026-05-28*
*Next Review: 2026-08-28*
