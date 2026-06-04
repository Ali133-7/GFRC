# GFRC System — Incident Response Plan

**Version:** 1.0 | **Last Updated:** 2026-05-28 | **Classification:** Internal

---

## Purpose

This document provides step-by-step procedures for responding to security incidents, system failures, and data emergencies. It ensures rapid, coordinated response to minimize impact and data loss.

---

## Incident Severity Levels

### SEVERITY 1 — CRITICAL (Response: Immediate, 15 minutes)

**Definition**: System down or data loss occurring

**Examples**:
- Complete system unavailability
- Financial data corruption
- Security breach with active data exfiltration
- Ransomware infection
- Database deletion or corruption

**Response**:
1. Activate emergency team immediately
2. Declare "SEV-1" incident in all channels
3. Begin incident response within 15 minutes
4. RTO target: < 2 hours
5. RPO target: < 24 hours

### SEVERITY 2 — HIGH (Response: Urgent, 1 hour)

**Definition**: Significant degradation or security risk

**Examples**:
- Major performance issues (> 50% slow)
- Unauthorized access attempt
- Audit trail gap discovered
- Backup failures (multiple consecutive)
- SSL certificate expiring < 7 days

**Response**:
1. Page on-call engineer
2. Notify management
3. Begin investigation
4. Prepare mitigation plan
5. Target resolution: < 4 hours

### SEVERITY 3 — MEDIUM (Response: Urgent, 4 hours)

**Definition**: Minor issues or configuration problems

**Examples**:
- Single backup failure
- Minor performance degradation (< 20% slow)
- Failed login attempts (brute force attempt)
- Disk space low (> 70% used)
- Non-critical error in logs

**Response**:
1. Create incident ticket
2. Assign to on-call engineer
3. Investigate during business hours
4. Target resolution: < 24 hours

### SEVERITY 4 — LOW (Response: Non-urgent, 24 hours)

**Definition**: Minor bugs or documentation issues

**Examples**:
- Typos in error messages
- Minor UI glitches
- Documentation updates needed
- Code cleanup
- Performance optimization opportunities

**Response**:
1. Create ticket for next sprint
2. Include in regular backlog
3. No immediate action required

---

## Incident Response Team

### Roles & Responsibilities

| Role | Primary | Backup | Contacts |
|------|---------|--------|----------|
| **Incident Commander** | [Name/Email] | [Name/Email] | [Phone] |
| **Database Admin** | [Name/Email] | [Name/Email] | [Phone] |
| **Security Lead** | [Name/Email] | [Name/Email] | [Phone] |
| **DevOps Engineer** | [Name/Email] | [Name/Email] | [Phone] |
| **Communications** | [Name/Email] | [Name/Email] | [Phone] |

### Escalation Path

```
User/Monitor Detection
        ↓
Level 1: On-Call Engineer (30 min)
        ↓ (if unresolved)
Level 2: Team Lead + Specialists (15 min)
        ↓ (if unresolved)
Level 3: Engineering Manager + Director (15 min)
        ↓ (if unresolved)
Level 4: VP Engineering + CTO (5 min)
```

---

## General Incident Response Process

### Phase 1: DETECT (5 minutes)

**Trigger**:
- Automated alert from monitoring
- User report
- Manual discovery

**Actions**:
```
□ Confirm incident is real (not false alarm)
□ Note time of discovery
□ Check status page
□ Gather initial information:
  - What system is affected?
  - How many users impacted?
  - What is visible to users?
  - Is data at risk?
□ Document initial symptoms
□ Take screenshots/logs
```

**Outcome**: Incident ticket created with severity level assigned

---

### Phase 2: ASSESS (10 minutes)

**Actions**:
```
□ Activate incident response team (via Slack/Email)
□ Assign Incident Commander
□ Gather more information:
  - Recent changes deployed?
  - Recent alerts or warnings?
  - Current system metrics (CPU, Memory, Disk)?
  - Recent backups successful?
  - Any security alerts?
□ Assess financial impact
□ Estimate time to resolution
□ Determine if escalation needed
□ Update status page (if customer-facing)
```

**Outcome**: Incident classified, team assembled, communication started

---

### Phase 3: MITIGATE (15-30 minutes)

**Actions**:
```
□ Implement immediate workaround if available
□ Scale up resources if needed
□ Isolate affected systems if security incident
□ Preserve evidence (logs, memory dumps, etc.)
□ Keep stakeholders informed
□ Update monitoring/alerting
□ Prepare rollback procedure
□ Notify backup systems to start restore if needed
```

**Outcome**: Immediate impact reduced, more time for proper fix

---

### Phase 4: RESOLVE (variable)

**Actions**:
```
□ Root cause analysis:
  - When exactly did issue start?
  - What changed recently?
  - What configuration is affected?
  - What is the fix?
□ Test fix in staging if possible
□ Apply fix with monitoring active
□ Verify fix resolves issue
□ Monitor for side effects
□ Document fix
```

**Outcome**: Issue resolved, system operational

---

### Phase 5: RECOVER (if needed)

**Actions**:
```
□ If data loss occurred:
  - Stop all write operations
  - Preserve evidence
  - Restore from backup
  - Verify data integrity
  - Replay any lost transactions
□ If security breach:
  - Rotate compromised credentials
  - Review audit logs for unauthorized access
  - Patch vulnerabilities
  - Notify affected users
□ Clear any manual workarounds
□ Return to normal operations
```

**Outcome**: Full system restore, data integrity confirmed

---

### Phase 6: POST-INCIDENT (24 hours after resolution)

**Actions**:
```
□ Collect incident artifacts
□ Prepare incident report:
  - What happened?
  - When did it start/end?
  - What was the impact?
  - What was the root cause?
  - What fix was applied?
  - What was the cost?
□ Conduct blameless postmortem
□ Identify action items
□ Update documentation
□ Share lessons learned
□ Schedule follow-up review
```

**Outcome**: Incident closed, lessons documented

---

## Specific Incident Scenarios

### Scenario A: System Unavailability

**Symptoms**:
- Website returns 503 Service Unavailable
- API endpoints timeout
- Database connection errors in logs

**Response**:

```bash
# MINUTE 0-5: ASSESS
1. Check uptime monitoring dashboard
2. Verify server is online
   ping server.ip.address
3. Check system resources
   ssh admin@server
   free -h           # Memory usage
   df -h             # Disk usage
   top -b -n 1      # CPU usage
4. Check services status
   systemctl status nginx
   systemctl status php8.3-fpm
   systemctl status postgresql

# MINUTE 5-15: DIAGNOSE
5. Check error logs
   tail -100 /var/log/nginx/error.log
   tail -100 /var/log/php-fpm.log
   tail -100 /var/www/gfrc/backend/storage/logs/laravel.log
6. Check PostgreSQL connectivity
   psql -h localhost -U gfrc_prod -d gfrc_production -c "SELECT 1"
7. Check Redis connectivity
   redis-cli ping
8. Check disk space (if > 90%, may cause failures)
   df -h /var/www/gfrc

# MINUTE 15-30: MITIGATE
9. If out of disk space:
   □ Archive old logs: find /var/log -mtime +30 -delete
   □ Clear cache: redis-cli FLUSHALL
   □ Check for runaway processes
   
10. If PostgreSQL down:
   □ Try restart: systemctl restart postgresql
   □ Check postgres logs: journalctl -u postgresql -n 50
   □ If recovery fails: restore from backup
   
11. If PHP-FPM down:
   □ Restart: systemctl restart php8.3-fpm
   □ Check memory: top | grep php
   □ If memory bloat: adjust pool config
   
12. If Nginx down:
   □ Test config: nginx -t
   □ Restart: systemctl restart nginx

# MINUTE 30+: VERIFY
13. Test application: curl https://yourdomain.com/api/v1/health
14. Check system metrics returned to normal
15. Monitor for 15 minutes for stability
```

**If not resolved after 30 minutes**:
- Escalate to Level 2
- Consider rolling back recent changes
- Prepare backup restoration

---

### Scenario B: Data Corruption Detected

**Symptoms**:
- Financial totals don't match
- Orphaned records found
- Database integrity check fails
- Unexpected NULL values in critical fields

**Response**:

```bash
# MINUTE 0-5: STOP THE BLEEDING
1. Declare SEV-1 incident
2. STOP ALL WRITE OPERATIONS immediately
   - Set application to read-only mode
   - Update database user permissions:
     ALTER ROLE gfrc_prod SET role = readonly;
3. Notify all users: "System in maintenance mode"
4. Preserve evidence:
   - Dump current state: pg_dump gfrc_prod > /tmp/corrupted_$(date +%s).sql
   - Take filesystem snapshot if available

# MINUTE 5-15: ASSESS DAMAGE
5. Identify scope of corruption:
   - Which tables affected?
   - When did corruption occur?
   - How much data is corrupted?
   
   SELECT COUNT(*) FROM receipts;
   SELECT COUNT(*) FROM receipts WHERE deleted_at IS NULL;
   SELECT COUNT(*) FROM receipts WHERE amount IS NULL;
   
6. Calculate recovery time:
   - Find last good backup date
   - Calculate data loss: (current_time - backup_time)
   - Determine which backup to use

# MINUTE 15-30: PREPARE RECOVERY
7. Choose recovery strategy:
   
   Option A: Full restore (if corruption > 1 hour old)
   - Restore entire database from backup
   - Replay transactions after backup point
   - Risk: 1-2 hours of data loss
   
   Option B: Selective restore (if corruption specific to 1-2 tables)
   - Extract specific tables from backup
   - Restore in isolated transaction
   - Merge back with current data
   - Risk: Data integrity verification needed
   
   Option C: Manual repair (if corruption minimal)
   - Identify and fix corrupted rows
   - Run database integrity check
   - Re-validate totals
   - Risk: Requires expertise, can miss issues

8. If choosing restore:
   - Download backup: aws s3 cp s3://backups/backup_2026-05-27.sql.enc ./
   - Verify backup integrity: php artisan backup:verify
   - Test restore in staging (if time allows)
   - Prepare rollback procedure

# MINUTE 30-60: EXECUTE RECOVERY
9. Execute chosen recovery:
   - Create savepoint: BEGIN; SAVEPOINT before_restore;
   - Execute restore commands
   - Verify totals: Compare financial sums
   - Run integrity checks:
     PRAGMA foreign_key_check;
     PRAGMA integrity_check;
   - If all OK: COMMIT;
   - If error: ROLLBACK TO SAVEPOINT before_restore;

10. Verify recovery:
    - Application can connect to database
    - All queries return results
    - Financial totals match expected
    - No NULL values where not allowed

# HOUR 1+: COMMUNICATION & ANALYSIS
11. Notify stakeholders of recovery completion
12. Document incident:
    - What caused corruption?
    - Was it application bug or infrastructure?
    - How to prevent?
13. Schedule root cause analysis meeting
```

---

### Scenario C: Security Breach / Unauthorized Access

**Symptoms**:
- Unauthorized login from unknown IP
- Modified audit log entries
- Encrypted files with ransom note
- Suspicious database queries
- Data exfiltration detected

**Response**:

```bash
# MINUTE 0-5: ISOLATE
1. Declare SEV-1 incident
2. IMMEDIATE ACTIONS:
   □ Disconnect affected server from network (if critical)
   □ Kill all active SSH sessions: killall sshd
   □ Disable external API access (or whitelist safe IPs only)
   □ Take server offline if compromised

# MINUTE 5-15: PRESERVE EVIDENCE
3. Preserve evidence BEFORE any cleanup:
   - Memory dump: sudo dd if=/dev/mem of=/tmp/memory_$(date +%s).bin
   - Process list: ps auxww > /tmp/processes_$(date +%s).txt
   - Network connections: netstat -tulpn > /tmp/netstat_$(date +%s).txt
   - Open files: lsof > /tmp/lsof_$(date +%s).txt
   - Audit logs: cp /var/log/audit/* /tmp/audit_backup/
   - Application logs: cp storage/logs/* /tmp/logs_backup/
   
4. Do NOT restart services (may destroy volatile evidence)

# MINUTE 15-30: INVESTIGATE
5. Timeline analysis:
   - When did breach occur? (Find earliest suspicious activity)
   - How was system accessed? (SSH, Web, SQL injection?)
   - What was accessed/modified?
   
   Query audit log:
   SELECT * FROM activity_log 
   WHERE created_at >= '2026-05-28 14:00:00'
   ORDER BY created_at;
   
6. Check for persistence mechanisms:
   - New user accounts created
   - SSH authorized_keys modified
   - Cron jobs added
   - Backdoors installed
   
   Check users: cat /etc/passwd | grep -E "uid=[0-9]{4,}"
   Check crontab: for user in $(cut -f1 -d: /etc/passwd); do crontab -l -u $user 2>/dev/null; done
   Check SSH keys: find / -name ".ssh" -type d 2>/dev/null

# MINUTE 30-60: CONTAIN
7. Rotate all credentials:
   □ Change all user passwords
   □ Rotate database passwords
   □ Rotate API keys
   □ Revoke active sessions
   □ Generate new JWT secrets
   
   ALTER USER gfrc_prod WITH PASSWORD 'NEW_STRONG_PASSWORD_HERE';
   UPDATE personal_access_tokens SET revoked = true;

8. Review access logs:
   - Identify which accounts were compromised
   - Which data was accessed
   - Who might be affected

# HOUR 1+: RECOVERY
9. Decision tree:
   
   Option A: Patch and redeploy
   - If vulnerability found and patched
   - Redeploy clean code
   - Restart services
   - Monitor closely
   
   Option B: Full system rebuild
   - If persistence mechanisms found
   - Provision new server
   - Restore from known good backup
   - Never restore from suspect time period
   - Verify all signatures

10. Post-recovery:
    □ Notify all users: "System was compromised"
    □ Advise: "Please change your password"
    □ If payment data: "Notify payment processor"
    □ Review what data could have been accessed
    □ Legal review for notification requirements

# HOUR 2+: EXTERNAL ACTIONS
11. If data breach:
    - Notify CISO / Legal team
    - File incident report
    - Notify relevant authorities (if required)
    - Prepare customer notification

12. Conduct forensic analysis:
    - Copy all evidence to secure storage
    - Engage forensic specialist if needed
    - Preserve chain of custody
```

---

### Scenario D: Backup Failure

**Symptoms**:
- Backup did not complete in scheduled window
- Backup file is corrupted or unreadable
- Cannot restore from backup

**Response**:

```bash
# SEVERITY 2: URGENT RESPONSE

# MINUTE 0-10: ASSESS
1. Verify backup actually failed:
   ls -lah /var/backups/gfrc/local/
   
2. Check backup logs:
   tail -100 /var/log/gfrc_backup.log
   
3. Test latest backup:
   php artisan backup:verify gfrc_backup_latest.sql.enc

# MINUTE 10-30: DIAGNOSE
4. Common causes:
   
   a) Disk space full:
      df -h
      # Free up space if < 10GB available
   
   b) Database locked:
      psql gfrc_prod -c "SELECT * FROM pg_stat_activity WHERE state != 'idle';"
      # Kill long-running queries if needed
   
   c) Encryption key unavailable:
      echo $BACKUP_ENCRYPTION_KEY
      # Verify key is set
   
   d) Network issue (cloud upload):
      curl -I https://s3.amazonaws.com
      # Test AWS connectivity

# MINUTE 30-60: REMEDIATE
5. Fix and retry:
   
   # Manually trigger backup
   php artisan backup:create
   
   # Monitor output
   tail -f /var/log/gfrc_backup.log
   
   # Verify success
   php artisan backup:verify gfrc_backup_latest.sql.enc

6. If manual backup succeeds:
   - Issue is likely environment-related
   - Check cron daemon: systemctl status cron
   - Re-test scheduled backup in 1 hour

7. If manual backup fails:
   - Issue is application or infrastructure
   - Troubleshoot specific error
   - Escalate to Level 2

# HOUR 1+: PREVENTIVE ACTIONS
8. Add monitoring alert:
   - Alert if backup not completed by 04:30 UTC
   - Alert if backup file size < expected
   - Alert if restore test fails

9. Schedule full backup restoration test for next day
```

---

## Communication Templates

### Internal Team Alert
```
[SEVERITY-1] System Alert: Database Unavailable

Time: 2026-05-28 14:23:00 UTC
Impact: All users - cannot create or view receipts
Status: Investigating

Incident Lead: [Name]
Team: [Names]

Updates every 15 minutes in #incident-response
```

### Customer Notification (if needed)
```
Subject: System Maintenance - GFRC Financial System

We are currently investigating a technical issue affecting the GFRC system.
The system is temporarily unavailable.

Status: Under investigation
Expected resolution time: [TIME]
Last update: [TIME UTC]

We apologize for the inconvenience. Your data is being protected.

Please refresh the page in 15 minutes or visit our status page:
https://status.yourdomain.com
```

### Post-Incident Report
```
# GFRC Incident Report #2026-0527-001

## Summary
[Brief 2-sentence summary]

## Impact
- Start time: 2026-05-28 14:23:00 UTC
- End time: 2026-05-28 15:18:00 UTC
- Duration: 55 minutes
- Users affected: [Number]
- Data loss: [Amount]
- Severity: SEV-[1-4]

## Root Cause
[Detailed explanation]

## Actions Taken
- [Action 1]
- [Action 2]

## Follow-up Actions
- [ ] [Action item 1 - owner, deadline]
- [ ] [Action item 2]

## Timeline
14:23 - Issue detected
14:28 - Team assembled
14:35 - Root cause identified
15:00 - Fix implemented
15:18 - System operational
```

---

## Post-Incident Review (Blameless Postmortem)

**Conduct within 24 hours of resolution**

### Agenda (60 minutes)
```
1. Timeline review (10 min)
2. What went well (10 min)
3. What could improve (20 min)
4. Action items (15 min)
5. Next steps (5 min)
```

### Questions to Answer
```
□ What led to the incident?
□ What early warning signs did we miss?
□ What helped us detect it?
□ What helped us respond effectively?
□ What delayed our response?
□ What tools/processes do we need?
□ What training is needed?
□ How do we prevent this recurring?
□ Is this a known issue we've seen before?
□ What's our confidence level in the fix?
```

---

## Training & Drills

### Monthly Incident Response Drill
```
Schedule: First Friday of month, 2:00-3:00 PM UTC
Scenario: Rotate through different scenarios
Objective: Test procedures and team readiness

Scenarios:
1. Database unavailable
2. Data corruption
3. Security breach
4. Backup failure
5. Network outage
```

### Quarterly Full Disaster Recovery Drill
```
Schedule: End of each quarter
Duration: 4-hour exercise
Objective: Full system rebuild and recovery

Procedure:
1. Assume production down completely
2. Activate full incident response team
3. Execute complete recovery from backup
4. Verify all systems operational
5. Document issues found
6. Conduct postmortem
```

---

## Resources

### Documentation
- SECURITY.md - Security guidelines
- BACKUP_STRATEGY.md - Backup procedures
- DEPLOYMENT.md - Infrastructure setup

### Tools
- Log aggregation: [Tool]
- Monitoring: [Tool]
- Chat: Slack/Teams
- Status page: [URL]

### External
- AWS Support: [Link]
- Database vendor support: [Link]
- Cyber incident insurance: [Policy #]

---

## Version History
| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-05-28 | Initial version |

*Last reviewed: 2026-05-28*
*Next review: 2026-08-28*
