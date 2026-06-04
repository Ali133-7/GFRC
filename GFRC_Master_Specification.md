# GFRC System — Master Technical Specification
## Government Financial Receipt & Cash Ledger System
**Version:** 1.0 | **Classification:** Internal Technical Document | **Audience:** AI Implementation Agent (Kimi)

---

## PART ONE: MISSION & SCOPE

### 1.1 System Purpose

Build a **government-grade, browser-based financial management system** that handles:

- Financial receipts (وصولات مالية)
- Cash ledger entries (سجلات المقبوضات)
- Dynamic financial classifications (تصنيفات مالية ديناميكية)
- User access & permission control
- Full financial audit trails
- Reporting and print functions
- Future inter-system integration

### 1.2 Core Guarantees

The system **must guarantee** at all times:

1. Zero financial loss — no amount is ever silently discarded
2. Zero data corruption — every record is safe and versioned
3. Full traceability — every action has an actor, timestamp, and reason
4. Horizontal scalability — adding features never breaks existing data
5. Integration-readiness — external systems can connect via API

### 1.3 What This System Is NOT

This is **not** a CRUD form application.
This is **not** an Excel replacement.
This is **not** a simple data entry tool.

This is a **Financial Transaction Engine** — engineered for correctness, auditability, and long-term institutional use.

---

## PART TWO: ARCHITECTURAL PRINCIPLES (NON-NEGOTIABLE)

These five principles govern every design decision. Any feature, module, or database change must be evaluated against all five before implementation.

### Principle 1 — Dynamic Register Architecture

All registers (سجلات) and their fields (حقول) must be **fully runtime-configurable**:

- New registers are added without code changes
- Fields can be added/removed/reordered without breaking historical data
- Field types and validation rules are stored in the database, not hardcoded

**Implementation rule:** Use a `registers` + `register_fields` table pair. Never hardcode field names in application logic.

---

### Principle 2 — Immutable Financial Records

Financial records are **never physically deleted**.

| Operation | Implementation |
|-----------|---------------|
| Delete | `soft_delete` — set `deleted_at` timestamp, record remains |
| Edit | `versioning` — new revision created, old one preserved |
| Cancel | Status change to `CANCELLED`, original values retained |

**Implementation rule:** Every financial table must have `deleted_at`, `status`, and `version` fields. The word "DELETE" must not appear in any financial SQL operation.

---

### Principle 3 — Audit First Design

Every system operation **automatically produces** an audit log entry. This is not optional and not a later feature.

Each log entry must contain:

```
user_id, user_name, ip_address, device_fingerprint,
action_type, model, model_id,
old_values (JSON), new_values (JSON),
timestamp, notes
```

**Implementation rule:** Use the `Spatie\Activitylog` package with a custom global observer. No action that changes financial data should be possible without triggering an audit log.

---

### Principle 4 — API-First Architecture

The frontend **never accesses the database directly**.

All business logic lives in the backend API layer. The frontend only:
- Sends requests to API endpoints
- Displays responses
- Manages UI state

**Implementation rule:** Every function must have a corresponding API endpoint before any UI is built for it.

---

### Principle 5 — Database Transaction Safety

Every financial operation runs **inside a database transaction**.

```php
DB::transaction(function () {
    // All financial writes here
    // On exception: automatic full rollback
});
```

**Implementation rule:** No financial write operation (create, update, soft-delete) should exist outside a `DB::transaction()` block.

---

## PART THREE: TECHNOLOGY STACK

### 3.1 Selected Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Frontend Framework | React | 18+ |
| Frontend Language | TypeScript | 5+ |
| Frontend Styling | Tailwind CSS | 3+ |
| Data Grid | AG Grid | Community |
| Backend Framework | Laravel | 12+ |
| Auth System | Laravel Sanctum | latest |
| Permissions | Spatie Permission | 6+ |
| Audit Logging | Spatie Activitylog | 4+ |
| Database | PostgreSQL | 15+ |
| Containerization | Docker + Docker Compose | latest |
| Web Server | Nginx | latest |
| Deployment | Local Network (LAN) | — |

### 3.2 Technology Justification

**Why React + TypeScript?**
Type safety prevents financial calculation errors at compile time. Component reusability supports dynamic form generation. Strong ecosystem for table/grid components.

**Why Laravel?**
Mature transaction support, Eloquent model observers for automatic audit logging, built-in API resource transformation, Sanctum for stateless API auth.

**Why PostgreSQL over MySQL?**
Full ACID compliance, JSONB support for dynamic field storage, row-level locking, superior concurrency handling for financial workloads.

**Why Modular Monolith (not Microservices)?**
Appropriate complexity for a government departmental system. Easier to deploy, maintain, and audit. Can be decomposed into services later if needed.

---

## PART FOUR: MODULE DEFINITIONS

### Module 1 — Authentication Module

**Responsibility:** Identity verification and session management

**Functions:**
- Username/password login with rate limiting
- JWT token issuance via Sanctum
- Session tracking (device, IP, timestamp)
- Forced logout / session invalidation
- Login attempt audit logging
- (Future) Two-Factor Authentication

**API Endpoints:**
```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
POST   /api/auth/refresh
```

---

### Module 2 — User & Roles Module

**Responsibility:** Access control and duty separation

**Functions:**
- User CRUD (admin only)
- Role assignment (many-to-many)
- Permission assignment to roles
- Financial duty separation enforcement
- User activity summary

**Roles (predefined, extensible):**

| Role | Arabic | Key Permissions |
|------|--------|-----------------|
| `super_admin` | مدير النظام | Full access |
| `cashier` | أمين الصندوق | Create receipts |
| `auditor` | المدقق | Read-only + audit logs |
| `manager` | المدير | Approve, reports |
| `data_entry` | مدخل البيانات | Limited entry |

**API Endpoints:**
```
GET    /api/users
POST   /api/users
PUT    /api/users/{id}
DELETE /api/users/{id}        ← soft delete only
GET    /api/roles
POST   /api/roles
PUT    /api/roles/{id}/permissions
```

---

### Module 3 — Register Management Module

**Responsibility:** Define and manage financial registers and their dynamic fields

**Concept:** A "register" (سجل) is a template for a type of financial transaction. Each register has its own set of fields with their own validation rules.

**Example registers:**
- سجل الوارد العام (General Income Register)
- سجل حجز الاسم التجاري (Trade Name Reservation Register)
- سجل الغرامات (Fines Register)

**Field Types Supported:**
```
text | number | decimal | date | select | textarea | hidden | calculated
```

**Field Properties:**
```json
{
  "id": 1,
  "register_id": 3,
  "name": "fine_amount",
  "label_ar": "مبلغ الغرامة",
  "type": "decimal",
  "is_required": true,
  "is_visible": true,
  "is_financial": true,
  "sort_order": 2,
  "validation_rules": "numeric|min:0",
  "default_value": null
}
```

**API Endpoints:**
```
GET    /api/registers
POST   /api/registers
PUT    /api/registers/{id}
GET    /api/registers/{id}/fields
POST   /api/registers/{id}/fields
PUT    /api/registers/{id}/fields/{fieldId}
PATCH  /api/registers/{id}/fields/reorder
```

---

### Module 4 — Receipt Engine Module

**Responsibility:** Core receipt lifecycle management

**Receipt States:**
```
DRAFT → PENDING → ISSUED → PRINTED → CANCELLED (soft)
```

**Key Functions:**
- Auto-generate sequential receipt number (per register, per fiscal year)
- Validate that sum of financial fields equals total amount
- Generate QR code containing: receipt number, amount, date, issuer ID, verification hash
- Print-ready PDF generation (A4)
- Duplicate prevention via idempotency key
- Batch receipt creation (future)

**Receipt Number Format:**
```
{REGISTER_CODE}-{FISCAL_YEAR}-{SEQUENCE}
Example: GEN-2025-004521
```

**API Endpoints:**
```
GET    /api/receipts
POST   /api/receipts
GET    /api/receipts/{id}
PATCH  /api/receipts/{id}/cancel
GET    /api/receipts/{id}/print
GET    /api/receipts/{id}/qr
POST   /api/receipts/{id}/revise
```

---

### Module 5 — Financial Validation Module

**Responsibility:** Enforce financial integrity rules at the engine level

**Validation Rules (enforced server-side, cannot be bypassed by frontend):**

1. `SUM(financial_fields) == receipt.total_amount` — fields must match total
2. `amount >= 0` — no negative financial values
3. `total_amount > 0` — receipts cannot have zero value
4. Receipt number uniqueness per register + fiscal year
5. No modification after status = `ISSUED` without creating a revision
6. Cancellation requires reason and supervisor permission

**Implementation:** Dedicated `FinancialValidator` service class called inside every transaction block before commit.

---

### Module 6 — Audit Trail Module

**Responsibility:** Complete, tamper-evident operation history

**Logged Events:**

| Event | Details Captured |
|-------|-----------------|
| Login / Logout | IP, device, timestamp |
| Receipt Created | All field values, user, register |
| Receipt Revised | Old values, new values, reason |
| Receipt Cancelled | Reason, authorizing user |
| Receipt Printed | Timestamp, user, count |
| User Created/Modified | Old/new permissions |
| Register Modified | Field changes |
| Settings Changed | Key, old value, new value |

**Storage:** `audit_logs` table. Records are **never deleted**. Indexed on `user_id`, `model`, `model_id`, `created_at`.

**API Endpoints:**
```
GET    /api/audit-logs
GET    /api/audit-logs?model=receipt&model_id={id}
GET    /api/audit-logs?user_id={id}
GET    /api/audit-logs?date_from={date}&date_to={date}
```

---

### Module 7 — Reporting Module

**Responsibility:** Financial summaries and operational reporting

**Report Types:**

| Report | Filters Available |
|--------|------------------|
| Daily Cash Summary | Date, register, cashier |
| Monthly Summary | Month, register |
| Per-User Activity | User, date range |
| Per-Register Summary | Register, date range |
| Financial Category Breakdown | Category, date range |
| Totals & Reconciliation | Any combination |

**Output Formats:** PDF (A4, print-ready), Excel (.xlsx), Screen display

**API Endpoints:**
```
GET    /api/reports/daily
GET    /api/reports/monthly
GET    /api/reports/user-activity
GET    /api/reports/register-summary
GET    /api/reports/reconciliation
POST   /api/reports/custom
```

---

### Module 8 — Backup & Recovery Module

**Responsibility:** Data durability and disaster recovery

**Functions:**
- Daily automated PostgreSQL dump (pg_dump)
- Timestamped backup files stored locally and optionally on network share
- Manual backup trigger (admin only)
- Backup integrity verification
- Point-in-time restore procedure (documented)
- Data export: Excel, PDF, JSON

---

## PART FIVE: DATABASE SCHEMA

### 5.1 Design Principles

- **No fixed financial columns** — financial values are stored as `receipt_items` rows, not table columns
- **JSONB for metadata** — flexible attributes without schema changes
- **Soft deletes everywhere** — `deleted_at` on all major tables
- **Timestamps everywhere** — `created_at`, `updated_at` on all tables
- **UUID primary keys** — recommended for receipt IDs to prevent enumeration

### 5.2 Core Tables

#### `users`
```sql
id            UUID    PRIMARY KEY
name          VARCHAR NOT NULL
username      VARCHAR UNIQUE NOT NULL
email         VARCHAR UNIQUE
password      VARCHAR NOT NULL (hashed)
is_active     BOOLEAN DEFAULT true
last_login_at TIMESTAMP
created_at    TIMESTAMP
updated_at    TIMESTAMP
deleted_at    TIMESTAMP (soft delete)
```

#### `roles` and `permissions`
```
Managed by Spatie Permission package.
Standard tables: roles, permissions, model_has_roles, model_has_permissions, role_has_permissions
```

#### `registers`
```sql
id            UUID    PRIMARY KEY
code          VARCHAR UNIQUE NOT NULL     -- e.g. 'GEN', 'FINE'
name_ar       VARCHAR NOT NULL
name_en       VARCHAR
description   TEXT
is_active     BOOLEAN DEFAULT true
fiscal_year   INTEGER                     -- for sequence scoping
current_sequence INTEGER DEFAULT 0
created_by    UUID FK → users.id
created_at    TIMESTAMP
updated_at    TIMESTAMP
deleted_at    TIMESTAMP
```

#### `register_fields`
```sql
id             UUID    PRIMARY KEY
register_id    UUID FK → registers.id
name           VARCHAR NOT NULL            -- machine name
label_ar       VARCHAR NOT NULL            -- Arabic display label
label_en       VARCHAR
field_type     ENUM (text|number|decimal|date|select|textarea|hidden|calculated)
is_required    BOOLEAN DEFAULT false
is_visible     BOOLEAN DEFAULT true
is_financial   BOOLEAN DEFAULT false       -- does this field contribute to total?
sort_order     INTEGER DEFAULT 0
validation_rules VARCHAR                   -- Laravel validation string
default_value  VARCHAR
options        JSONB                       -- for 'select' type: [{value, label_ar}]
created_at     TIMESTAMP
updated_at     TIMESTAMP
deleted_at     TIMESTAMP                   -- hiding a field never destroys history
```

#### `receipts`
```sql
id             UUID    PRIMARY KEY
receipt_number VARCHAR UNIQUE NOT NULL     -- e.g. GEN-2025-004521
register_id    UUID FK → registers.id
created_by     UUID FK → users.id
approved_by    UUID FK → users.id (nullable)
total_amount   DECIMAL(15,3) NOT NULL      -- 3 decimal places for IQD
status         ENUM (draft|pending|issued|printed|cancelled)
version        INTEGER DEFAULT 1
notes          TEXT
idempotency_key VARCHAR UNIQUE             -- prevent duplicate submission
qr_code        TEXT                       -- generated QR string
printed_at     TIMESTAMP
cancelled_at   TIMESTAMP
cancelled_by   UUID FK → users.id
cancel_reason  TEXT
metadata       JSONB                      -- extensible extra data
created_at     TIMESTAMP
updated_at     TIMESTAMP
deleted_at     TIMESTAMP
```

#### `receipt_items`
```sql
id             UUID    PRIMARY KEY
receipt_id     UUID FK → receipts.id
field_id       UUID FK → register_fields.id
field_name     VARCHAR NOT NULL            -- snapshot at time of creation
amount         DECIMAL(15,3)              -- nullable for non-financial fields
text_value     VARCHAR                    -- for text fields
created_at     TIMESTAMP
```

**Note on `receipt_items`:** Both `field_name` and `field_id` are stored. This ensures that even if a field is later renamed or deleted, historical receipts retain their exact original data.

#### `receipt_revisions`
```sql
id             UUID    PRIMARY KEY
receipt_id     UUID FK → receipts.id
version        INTEGER NOT NULL
revised_by     UUID FK → users.id
reason         TEXT NOT NULL
old_values     JSONB NOT NULL             -- complete snapshot of old receipt + items
new_values     JSONB NOT NULL             -- complete snapshot of new receipt + items
created_at     TIMESTAMP
```

#### `audit_logs`
```sql
id             BIGSERIAL PRIMARY KEY
log_name       VARCHAR
description    TEXT
subject_type   VARCHAR                    -- model class name
subject_id     VARCHAR                    -- model ID
causer_type    VARCHAR
causer_id      UUID FK → users.id
properties     JSONB                      -- {old, new, attributes}
ip_address     INET
user_agent     TEXT
created_at     TIMESTAMP
```

**Note:** `audit_logs` has NO `deleted_at`. Records are permanent.

---

## PART SIX: API STRUCTURE & STANDARDS

### 6.1 Request/Response Format

**All API responses follow this envelope:**
```json
{
  "success": true,
  "data": { ... },
  "message": "تم إنشاء الوصل بنجاح",
  "meta": {
    "pagination": { "page": 1, "per_page": 25, "total": 342 }
  }
}
```

**Error response format:**
```json
{
  "success": false,
  "message": "فشل التحقق من البيانات",
  "errors": {
    "total_amount": ["المبلغ الكلي لا يطابق مجموع الحقول"]
  },
  "error_code": "VALIDATION_FAILED"
}
```

### 6.2 Authentication

All endpoints require `Authorization: Bearer {token}` header.
Tokens issued via `POST /api/auth/login`.
Token expiry: 8 hours (configurable).

### 6.3 Versioning

API base path: `/api/v1/`
Current version: v1

### 6.4 Standard Query Parameters

```
?page=1&per_page=25          — pagination
?sort_by=created_at&order=desc — sorting
?search=keyword               — global search
?date_from=2025-01-01&date_to=2025-12-31 — date filtering
?status=issued               — status filtering
```

---

## PART SEVEN: FRONTEND SPECIFICATIONS

### 7.1 UI Requirements

| Requirement | Specification |
|-------------|--------------|
| Text direction | RTL (Arabic primary) |
| Language | Arabic UI, English field names in code |
| Table engine | AG Grid (handles thousands of rows) |
| Number format | Iraqi Dinar (IQD), 3 decimal places |
| Date format | DD/MM/YYYY |
| Keyboard navigation | Full support for fast data entry |
| Fast entry mode | Tab-through form fields with auto-advance |

### 7.2 Key Screens

1. **Login Screen** — username, password, device info
2. **Dashboard** — daily totals, recent receipts, quick actions
3. **New Receipt** — dynamic form rendered from register fields
4. **Receipt List** — filterable, sortable, paginated table
5. **Receipt Detail** — full view with audit history
6. **Register Management** — field builder UI (drag-and-drop sort)
7. **User Management** — user CRUD + role assignment
8. **Reports** — date/filter pickers + visual summaries
9. **Audit Log Viewer** — filterable log table
10. **Settings** — system configuration

### 7.3 Print / PDF Requirements

Print output must include:

- Government department header (logo, name)
- Receipt number (large, prominent)
- QR code (top-right corner)
- All financial field breakdown (itemized)
- Total amount (in numbers and Arabic words)
- Cashier name + signature line
- Electronic seal / system stamp
- Verification code (for authenticity check)
- Date and time of issuance

---

## PART EIGHT: SECURITY POLICIES

### 8.1 Access Control Rules

| Rule | Implementation |
|------|---------------|
| No financial delete | No `DELETE` on financial tables, only soft deletes |
| Edit requires permission | `can('edit-receipt')` gate check |
| Edit after issue requires revision | Status check before any field update |
| All changes logged | Global Eloquent observer |
| Row-level access | Users only see receipts from their assigned registers |
| IP logging | Middleware captures IP on every request |

### 8.2 Data Validation

- All validation runs **server-side** regardless of frontend validation
- Financial totals are recalculated server-side before commit
- Input is sanitized against SQL injection and XSS
- Rate limiting on authentication endpoints (5 attempts / 15 min)

### 8.3 Forbidden Operations (Hard Block)

These operations must be **architecturally impossible**, not just permission-blocked:

1. `DELETE FROM receipts WHERE ...` — physical deletion
2. `UPDATE receipts SET amount = ...` without versioning
3. Any write to `audit_logs` — only the audit system writes there
4. Any request that bypasses `DB::transaction()`

---

## PART NINE: INFRASTRUCTURE & DEPLOYMENT

### 9.1 Docker Compose Services

```yaml
services:
  nginx:      # reverse proxy, port 80/443
  php-fpm:    # Laravel application
  postgres:   # database, port 5432
  redis:      # session cache, queue (future)
  node:       # React build (development)
```

### 9.2 Environment Configuration

Required `.env` variables:
```
APP_NAME=GFRC_System
APP_ENV=production
APP_URL=http://192.168.x.x        # LAN IP
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=gfrc_db
DB_USERNAME=gfrc_user
DB_PASSWORD={strong_password}
SANCTUM_STATEFUL_DOMAINS=192.168.x.x
SESSION_LIFETIME=480               # 8 hours
```

### 9.3 Backup Configuration

```
Daily backup time: 02:00 AM
Backup location: /backups/daily/
Retention: 30 days
Format: gfrc_backup_YYYY-MM-DD.sql.gz
Alert on failure: log + admin notification
```

---

## PART TEN: IMPLEMENTATION ROADMAP

### Phase 1 — Foundation (Weeks 1–3)
- [ ] Docker environment setup
- [ ] Laravel project initialization
- [ ] PostgreSQL schema migration (all tables)
- [ ] Authentication module
- [ ] User & Roles module
- [ ] Audit logging infrastructure

### Phase 2 — Core Engine (Weeks 4–6)
- [ ] Register Management module
- [ ] Receipt Engine module
- [ ] Financial Validation module
- [ ] Receipt Revisions system
- [ ] QR code generation

### Phase 3 — Frontend (Weeks 7–9)
- [ ] React + TypeScript project setup
- [ ] Authentication screens
- [ ] Dashboard
- [ ] Dynamic receipt form (from register fields)
- [ ] Receipt list + detail views
- [ ] Print/PDF generation

### Phase 4 — Reporting & Admin (Weeks 10–11)
- [ ] Reporting module
- [ ] Audit log viewer
- [ ] User management UI
- [ ] Register field builder UI

### Phase 5 — QA & Hardening (Week 12)
- [ ] Financial calculation tests
- [ ] Security audit
- [ ] Performance testing (1000+ receipts)
- [ ] Backup verification
- [ ] User acceptance testing

---

## PART ELEVEN: FUTURE EXTENSION POINTS

The architecture is designed to accommodate these future additions **without rewriting existing code**:

| Feature | Extension Point |
|---------|----------------|
| Payment module (صرف) | New module using same transaction engine |
| Daily journal entries | New module, reads from receipts |
| Chart of accounts | Add `accounts` table, link to receipt_items |
| Multi-branch | Add `branch_id` to users and receipts |
| Multi-currency | Add `currency`, `exchange_rate` to receipts |
| Mobile app | Same REST API, new client |
| ERP integration | Webhook module + API tokens |
| Signature authority | Add `approval_chain` to registers |

---

## APPENDIX A: CODING STANDARDS

### Laravel (PHP)
- Use `Form Requests` for all input validation
- Use `API Resources` for all response transformation
- Use `Repository Pattern` for database queries
- Use `Service Classes` for business logic
- Never put business logic in Controllers

### React (TypeScript)
- Use `React Query` for API state management
- Use `Zustand` for global UI state
- Use `React Hook Form` for form management
- Never call API directly from components — use custom hooks

### Database
- All migrations must be reversible (`down()` method)
- No raw SQL in application code — use Eloquent query builder
- All decimal financial columns use `DECIMAL(15,3)`
- All timestamps are UTC in the database

---

## APPENDIX B: GLOSSARY

| Arabic | English | System Term |
|--------|---------|-------------|
| وصل / وصول | Receipt | `receipt` |
| سجل | Register / Ledger | `register` |
| حقل | Field | `field` |
| مقبوضات | Collections / Receipts | `receipts` |
| أمين الصندوق | Cashier | `cashier` (role) |
| مدقق | Auditor | `auditor` (role) |
| ترحيل | Posting / Finalizing | status = `issued` |
| سجل التدقيق | Audit Log | `audit_log` |
| صلاحية | Permission | `permission` |
| دور | Role | `role` |

---

*End of GFRC Master Technical Specification v1.0*
*Document prepared for AI-assisted implementation via Kimi*
