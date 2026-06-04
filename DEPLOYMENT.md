# GFRC System — Production Deployment Guide

**Version:** 1.0 | **Last Updated:** 2026-05-28 | **Audience:** DevOps/SysAdmin

---

## Pre-Deployment Checklist

### Security Review (MUST COMPLETE)
```
□ Change default admin password
□ Enable HTTPS with valid SSL certificate
□ Configure production .env file
□ Enable all security headers
□ Enable session encryption
□ Setup database backup schedule
□ Configure IP whitelisting (if applicable)
□ Review firewall rules
□ Enable WAF (Web Application Firewall)
□ Setup monitoring and alerting
□ Configure log aggregation
□ Review access permissions
□ Document incident procedures
```

### Infrastructure Requirements
```
Minimum Configuration:
- CPU: 4 cores
- RAM: 8 GB
- Storage: 100 GB SSD
- Network: 10 Mbps uplink

Recommended Configuration:
- CPU: 8+ cores
- RAM: 16+ GB
- Storage: 500 GB SSD + 1 TB HDD (backups)
- Network: 100+ Mbps uplink
- Load Balancer: Yes
- CDN: Optional
```

---

## Pre-Production Environment Setup

### 1. SSL/TLS Certificate Installation

```bash
# Using Let's Encrypt (Recommended)
sudo apt-get install certbot python3-certbot-nginx -y

# Generate certificate
sudo certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com

# Certificate locations:
# /etc/letsencrypt/live/yourdomain.com/fullchain.pem
# /etc/letsencrypt/live/yourdomain.com/privkey.pem

# Auto-renewal (runs daily)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

### 2. PostgreSQL Database Setup

```bash
# Install PostgreSQL 15
sudo apt-get install postgresql-15 postgresql-contrib-15 -y

# Create database and user
sudo -u postgres psql << EOF
CREATE DATABASE gfrc_production;
CREATE USER gfrc_prod WITH ENCRYPTED PASSWORD 'GENERATE_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE gfrc_production TO gfrc_prod;
ALTER USER gfrc_prod CREATEDB; -- For migrations
EOF

# Configure PostgreSQL for SSL
sudo -u postgres psql << EOF
ALTER SYSTEM SET ssl = on;
EOF

# Update pg_hba.conf for SSL
# Change: host  all  all  127.0.0.1/32  md5
# To:     hostssl  all  all  127.0.0.1/32  scram-sha-256

sudo systemctl restart postgresql
```

### 3. Redis Setup (Caching & Sessions)

```bash
# Install Redis
sudo apt-get install redis-server -y

# Secure Redis
sudo sed -i 's/# requirepass foobared/requirepass GENERATE_STRONG_PASSWORD_HERE/' /etc/redis/redis.conf

# Enable SSL
# Configure TLS in /etc/redis/redis.conf
tls-port 6380
tls-cert-file /path/to/cert.pem
tls-key-file /path/to/key.pem

# Restart
sudo systemctl restart redis-server
```

### 4. Nginx Configuration

```nginx
# /etc/nginx/sites-available/gfrc.conf

upstream laravel_app {
    server 127.0.0.1:9000; # PHP-FPM
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS Server Block
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.3 TLSv1.2;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'" always;

    # Logging
    access_log /var/log/nginx/gfrc_access.log combined;
    error_log /var/log/nginx/gfrc_error.log warn;

    # Root directory
    root /var/www/gfrc/public;
    index index.php index.html;

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass laravel_app;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_intercept_errors on;
    }

    # Static files
    location ~ ^/(js|css|images|fonts)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\. { deny all; }
    location ~ /\.env { deny all; }
    location ~ /\.git { deny all; }
}

# Enable
sudo ln -s /etc/nginx/sites-available/gfrc.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## Application Deployment

### 1. Clone Application

```bash
# Setup directory
sudo mkdir -p /var/www/gfrc
sudo chown www-data:www-data /var/www/gfrc
cd /var/www/gfrc

# Clone repository (production-ready)
sudo -u www-data git clone -b main https://github.com/org/gfrc.git .
```

### 2. Configure Environment

```bash
# Copy .env.example to .env.production
sudo -u www-data cp backend/.env.example backend/.env.production

# Edit with secure values (use secret manager)
# DO NOT put secrets directly in .env file
sudo nano backend/.env.production

# Link to .env (for Laravel)
sudo -u www-data cp backend/.env.production backend/.env
```

### 3. Install Dependencies

```bash
cd backend

# PHP dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Generate APP_KEY
sudo -u www-data php artisan key:generate

# Frontend build
cd ../frontend
npm ci --production
npm run build

# Copy to public
sudo cp -r dist /var/www/gfrc/public/build/
sudo chown -R www-data:www-data /var/www/gfrc/public/build/
```

### 4. Database Migration

```bash
cd /var/www/gfrc/backend

# Run migrations
sudo -u www-data php artisan migrate --force

# Seed initial data
sudo -u www-data php artisan db:seed

# Verify
sudo -u www-data php artisan tinker
>>> User::count()
>>> Register::count()
```

### 5. Initialize Backup System

```bash
# Create backup directories
sudo mkdir -p /var/backups/gfrc/{local,cloud,offline}
sudo chown -R www-data:www-data /var/backups/gfrc

# Test backup creation
sudo -u www-data php artisan backup:create

# Verify
ls -lah /var/backups/gfrc/local/
```

### 6. Setup Cron Jobs

```bash
# Edit crontab
sudo crontab -e -u www-data

# Add these lines:
# Daily backup at 2:00 AM
0 2 * * * php /var/www/gfrc/backend/artisan backup:create >> /var/log/gfrc_backup.log 2>&1

# Hourly queue processing
0 * * * * php /var/www/gfrc/backend/artisan queue:work --stop-when-empty >> /var/log/gfrc_queue.log 2>&1

# Daily log cleanup (older than 30 days)
0 3 * * * find /var/www/gfrc/backend/storage/logs -name "*.log" -mtime +30 -delete

# Monthly offline backup
0 23 28 * * php /var/www/gfrc/backend/artisan backup:offline >> /var/log/gfrc_offline_backup.log 2>&1

# Verify crontab
sudo crontab -l -u www-data
```

### 7. Permissions & Security

```bash
# Set directory permissions
cd /var/www/gfrc

# Application directories
sudo chmod -R 755 backend/app backend/config backend/routes
sudo chmod -R 755 frontend/public

# Writable directories
sudo chmod -R 775 backend/storage backend/bootstrap/cache
sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache

# Database file (if SQLite, which we don't use but just in case)
sudo chmod 660 backend/database/database.sqlite

# Remove write access to sensitive files
sudo chmod 644 backend/.env.production
sudo chmod 600 backend/.env.production  # More secure

# PHP-FPM configuration
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
# Ensure:
# listen.owner = www-data
# listen.group = www-data
# listen.mode = 0660
```

---

## Post-Deployment Verification

### 1. Application Health Check

```bash
# Check application status
curl -I https://yourdomain.com/api/v1/health

# Expected response:
# HTTP/2 200
# X-Powered-By: Laravel
```

### 2. Security Headers Verification

```bash
# Verify HTTPS works
curl -I https://yourdomain.com/

# Check headers:
# Strict-Transport-Security: max-age=31536000
# X-Frame-Options: DENY
# X-Content-Type-Options: nosniff
# Content-Security-Policy: default-src 'self'
```

### 3. Database Verification

```bash
# Connect to database
psql -h localhost -U gfrc_prod -d gfrc_production

# Verify tables
\dt

# Check row counts
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM registers;
```

### 4. Backup Verification

```bash
# List backups
ls -lah /var/backups/gfrc/local/

# Test restore
php artisan backup:verify gfrc_backup_YYYY-MM-DD_hhmmss.sql.enc
```

### 5. Authentication Test

```bash
# Login test
curl -X POST https://yourdomain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"NewPassword123!@#"}'

# Expected response:
# {
#   "data": {
#     "user": {...},
#     "token": "..."
#   }
# }
```

### 6. SSL Certificate Verification

```bash
# Check certificate validity
openssl x509 -in /etc/letsencrypt/live/yourdomain.com/cert.pem -text -noout

# Check expiration
openssl x509 -in /etc/letsencrypt/live/yourdomain.com/cert.pem -noout -dates

# Test with SSL Labs
# Visit: https://www.ssllabs.com/ssltest/analyze.html?d=yourdomain.com
```

---

## Monitoring & Logging

### 1. System Monitoring

```bash
# Install monitoring tools
sudo apt-get install htop iotop nethogs -y

# Monitor in real-time
htop

# Monitor disk I/O
iotop -o

# Monitor network
nethogs
```

### 2. Log Aggregation

```bash
# Create log directory
sudo mkdir -p /var/log/gfrc
sudo chown www-data:www-data /var/log/gfrc

# Rotate logs
sudo nano /etc/logrotate.d/gfrc

# Add:
/var/log/gfrc/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}

# Test logrotate
sudo logrotate -f /etc/logrotate.d/gfrc
```

### 3. Application Logging

```php
// config/logging.php - production configuration
'production' => [
    'driver' => 'stack',
    'channels' => ['single', 'errorlog'],
    'level' => 'warning', // Only log warnings and above
],

'channels' => [
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'warning',
    ],
    'errorlog' => [
        'driver' => 'errorlog',
        'level' => 'error',
    ],
],
```

---

## Performance Optimization

### 1. Caching Strategy

```php
// Enable query caching
redis-cli CONFIG SET maxmemory 2gb
redis-cli CONFIG SET maxmemory-policy allkeys-lru

// Laravel cache configuration
CACHE_STORE=redis
CACHE_REDIS_CONNECTION=default

// Cache frequently accessed data
Cache::remember('registers.all', 3600, function() {
    return Register::all();
});
```

### 2. Database Optimization

```sql
-- Index frequently queried columns
CREATE INDEX idx_receipts_register_id ON receipts(register_id);
CREATE INDEX idx_receipts_status ON receipts(status);
CREATE INDEX idx_receipts_created_at ON receipts(created_at DESC);
CREATE INDEX idx_receipt_items_receipt_id ON receipt_items(receipt_id);

-- Analyze query performance
EXPLAIN ANALYZE SELECT * FROM receipts WHERE status = 'issued';

-- Vacuum and analyze
VACUUM ANALYZE;
```

### 3. PHP-FPM Tuning

```bash
# /etc/php/8.3/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

# Restart
sudo systemctl restart php8.3-fpm
```

---

## Maintenance Schedule

### Daily
- [ ] Monitor application logs
- [ ] Verify backup completion
- [ ] Check disk space
- [ ] Monitor database performance

### Weekly
- [ ] Review security logs
- [ ] Update security patches
- [ ] Test backup restoration
- [ ] Check SSL certificate expiration

### Monthly
- [ ] Full security audit
- [ ] Database optimization
- [ ] Performance review
- [ ] Capacity planning
- [ ] Create offline backup

### Quarterly
- [ ] Disaster recovery drill
- [ ] Security assessment
- [ ] Penetration testing
- [ ] Backup integrity verification

---

## Rollback Procedure

In case of critical failure:

```bash
# 1. Stop all services
sudo systemctl stop nginx php8.3-fpm

# 2. Backup current state
sudo cp -r /var/www/gfrc /var/www/gfrc.failed

# 3. Restore from version control
cd /var/www/gfrc
sudo -u www-data git checkout HEAD~1  # Go back one commit

# 4. Restore database (if needed)
php artisan backup:restore last_working_backup.sql.enc --force

# 5. Restart services
sudo systemctl start php8.3-fpm nginx

# 6. Verify
curl https://yourdomain.com/api/v1/health
```

---

## Emergency Contacts

- **On-Call Engineer**: [contact info]
- **Database Admin**: [contact info]
- **Security Team**: [contact info]
- **Management**: [contact info]

## References
- See: SECURITY.md (Security guidelines)
- See: BACKUP_STRATEGY.md (Backup procedures)
- See: INCIDENT_RESPONSE.md (Emergency response)
