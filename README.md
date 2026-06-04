# GFRC System

Government Financial Receipt & Cash Ledger System

## Setup

### Backend

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Docker

```bash
docker-compose up -d
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

## Default Admin

- username: `admin`
- password: **MUST CHANGE BEFORE PRODUCTION** (See SECURITY.md → "Default Credentials")
- Change instructions: Settings → Security → Change Password

⚠️ **CRITICAL**: The default password `Admin@12345` is for development only. Change it immediately before going to production.

## Documentation

- **[PROJECT_STATUS.md](PROJECT_STATUS.md)** - Project overview and features
- **[GFRC_Master_Specification.md](GFRC_Master_Specification.md)** - Technical architecture
- **[SECURITY.md](SECURITY.md)** ⭐ Security policy and requirements
- **[BACKUP_STRATEGY.md](BACKUP_STRATEGY.md)** ⭐ Backup & disaster recovery
- **[DEPLOYMENT.md](DEPLOYMENT.md)** ⭐ Production deployment guide
- **[INCIDENT_RESPONSE.md](INCIDENT_RESPONSE.md)** ⭐ Emergency procedures
- **[MAINTENANCE.md](MAINTENANCE.md)** - Daily/weekly/monthly tasks
- **[PRODUCTION_READINESS_CHECKLIST.md](PRODUCTION_READINESS_CHECKLIST.md)** ⭐ Pre-production checklist

⭐ = Critical for production deployment
