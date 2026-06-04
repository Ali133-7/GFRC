# frist read the file GFRC_Master_Specification.md
# KIMI EXECUTION PROMPT — GFRC SYSTEM
## Single-Shot Full Implementation Brief

---

## ROLE & OBJECTIVE

You are a senior full-stack engineer. Your task is to **fully implement** the GFRC System (Government Financial Receipt & Cash Ledger System) in one continuous session. Output production-ready code only — no explanations, no commentary, no alternatives. Every file must be complete and runnable.

---

## OUTPUT RULES (READ BEFORE ANY CODE)

1. **Output files sequentially** — one file at a time, complete, no truncation
2. **No filler text** between files — only file path header + code block
3. **No "I will now..." or "Next we will..."** — go straight to code
4. **No placeholder comments** like `// TODO`, `// add logic here`, `// implement later`
5. **Every function must be fully implemented** — no stubs
6. If a file exceeds your output limit, finish the current file then continue with `CONTINUE: [filename]`
7. Use this exact file header format before each code block:

```
=== FILE: path/to/filename.ext ===
```

---

## STACK (FIXED — DO NOT DEVIATE)

| Layer | Choice |
|-------|--------|
| Backend | Laravel 11, PHP 8.3 |
| Auth | Laravel Sanctum |
| Permissions | Spatie/laravel-permission |
| Audit | Spatie/laravel-activitylog |
| Database | PostgreSQL 15 |
| Frontend | React 18 + TypeScript 5 |
| Styling | Tailwind CSS 3 |
| Data Grid | AG Grid Community |
| Forms | React Hook Form + Zod |
| API State | TanStack Query (React Query v5) |
| Global State | Zustand |
| PDF/Print | Laravel DomPDF |
| QR Code | SimpleSoftwareIO/simple-qrcode |
| Container | Docker + Docker Compose |
| Web Server | Nginx |

---

## ARCHITECTURE CONSTRAINTS (HARD RULES)

These are non-negotiable. Violating any of them is a critical bug:

**R1 — NO PHYSICAL DELETE**
Never use `->delete()` on financial records. Always `->softDelete()`. Financial tables must have `deleted_at`. The word DELETE must not appear in financial SQL.

**R2 — TRANSACTIONS EVERYWHERE**
Every financial write (create/update/cancel) must be wrapped in `DB::transaction(fn() => ...)`. No exception.

**R3 — SERVER-SIDE VALIDATION ALWAYS**
Frontend validation is UX only. Backend must independently validate all inputs using Form Request classes. Financial totals must be recalculated server-side before commit.

**R4 — IMMUTABLE REVISIONS**
Editing a receipt after status = `issued` creates a new row in `receipt_revisions` (with full JSON snapshot of old values) and increments `receipts.version`. The original is never overwritten.

**R5 — AUDIT EVERYTHING**
Use a global Eloquent observer bound to: Receipt, Register, RegisterField, User. Every create/update/softDelete must write to `audit_logs` via Spatie activitylog with: `ip_address`, `user_agent`, `old_values`, `new_values`.

**R6 — DYNAMIC FIELDS (NO HARDCODED FINANCIAL COLUMNS)**
Financial field names are NEVER hardcoded as DB columns. They live in `register_fields` table. Values live in `receipt_items` table as rows. This is Entity-Attribute-Value for financial data.

**R7 — API FIRST**
React never queries DB directly. All logic goes through `/api/v1/` endpoints. Controllers are thin — delegate to Service classes.

**R8 — DECIMAL PRECISION**
All monetary values use `DECIMAL(15,3)` in PostgreSQL. PHP: `bcmath` for all arithmetic. Never use float for money.

---

## DATABASE SCHEMA (IMPLEMENT EXACTLY)

### Table: users
```
id uuid PK
name varchar(100) NOT NULL
username varchar(50) UNIQUE NOT NULL
email varchar(150) UNIQUE
password varchar NOT NULL
is_active boolean DEFAULT true
last_login_at timestamp NULL
created_at, updated_at, deleted_at timestamps
```

### Table: registers
```
id uuid PK
code varchar(20) UNIQUE NOT NULL        -- e.g. 'GEN', 'FINE'
name_ar varchar(200) NOT NULL
name_en varchar(200)
description text
is_active boolean DEFAULT true
fiscal_year integer NOT NULL
current_sequence integer DEFAULT 0
created_by uuid FK→users.id
created_at, updated_at, deleted_at
```

### Table: register_fields
```
id uuid PK
register_id uuid FK→registers.id
name varchar(100) NOT NULL              -- machine name, snake_case
label_ar varchar(200) NOT NULL
label_en varchar(200)
field_type ENUM('text','number','decimal','date','select','textarea','hidden','calculated')
is_required boolean DEFAULT false
is_visible boolean DEFAULT true
is_financial boolean DEFAULT false      -- contributes to total_amount?
sort_order integer DEFAULT 0
validation_rules varchar(500)           -- Laravel validation string
default_value varchar(500)
options jsonb                           -- [{value, label_ar, label_en}] for select
created_at, updated_at, deleted_at
```

### Table: receipts
```
id uuid PK
receipt_number varchar(50) UNIQUE NOT NULL   -- format: {CODE}-{YEAR}-{SEQ:06d}
register_id uuid FK→registers.id
created_by uuid FK→users.id
approved_by uuid FK→users.id NULL
total_amount DECIMAL(15,3) NOT NULL
status ENUM('draft','pending','issued','printed','cancelled') DEFAULT 'draft'
version integer DEFAULT 1
notes text
idempotency_key varchar(100) UNIQUE NULL
qr_payload text
printed_at timestamp NULL
cancelled_at timestamp NULL
cancelled_by uuid FK→users.id NULL
cancel_reason text
metadata jsonb
created_at, updated_at, deleted_at
```

### Table: receipt_items
```
id uuid PK
receipt_id uuid FK→receipts.id
field_id uuid FK→register_fields.id
field_name_snapshot varchar(100)        -- copy of field name at creation time
label_ar_snapshot varchar(200)          -- copy of Arabic label at creation time
amount DECIMAL(15,3) NULL               -- for financial fields
text_value text NULL                    -- for non-financial fields
created_at
```
NOTE: No updated_at, no deleted_at — items are immutable. Revision = new receipt version.

### Table: receipt_revisions
```
id uuid PK
receipt_id uuid FK→receipts.id
version integer NOT NULL
revised_by uuid FK→users.id
reason text NOT NULL
old_snapshot jsonb NOT NULL             -- complete receipt + items before change
new_snapshot jsonb NOT NULL             -- complete receipt + items after change
created_at
```

### Table: audit_logs
```
(managed by Spatie activitylog)
add columns via migration: ip_address inet, user_agent text
```
NO deleted_at on this table. Records are permanent.

---

## API ENDPOINTS (IMPLEMENT ALL)

### Auth
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
```

### Users
```
GET    /api/v1/users                    ?page,per_page,search
POST   /api/v1/users
GET    /api/v1/users/{id}
PUT    /api/v1/users/{id}
DELETE /api/v1/users/{id}               soft delete
PUT    /api/v1/users/{id}/roles
```

### Registers
```
GET    /api/v1/registers
POST   /api/v1/registers
GET    /api/v1/registers/{id}
PUT    /api/v1/registers/{id}
GET    /api/v1/registers/{id}/fields
POST   /api/v1/registers/{id}/fields
PUT    /api/v1/registers/{id}/fields/{fieldId}
DELETE /api/v1/registers/{id}/fields/{fieldId}
PATCH  /api/v1/registers/{id}/fields/reorder    body: [{id, sort_order}]
```

### Receipts
```
GET    /api/v1/receipts                 ?page,per_page,register_id,status,date_from,date_to,search
POST   /api/v1/receipts
GET    /api/v1/receipts/{id}
PUT    /api/v1/receipts/{id}            only if status=draft or status=pending
POST   /api/v1/receipts/{id}/issue      draft→issued, validates total
POST   /api/v1/receipts/{id}/cancel     requires reason, any non-cancelled status
POST   /api/v1/receipts/{id}/revise     issued receipt correction
GET    /api/v1/receipts/{id}/print      returns PDF binary
GET    /api/v1/receipts/{id}/qr         returns QR image
GET    /api/v1/receipts/{id}/revisions
```

### Reports
```
GET    /api/v1/reports/daily            ?date,register_id,user_id
GET    /api/v1/reports/monthly          ?year,month,register_id
GET    /api/v1/reports/user-activity    ?user_id,date_from,date_to
GET    /api/v1/reports/register-summary ?register_id,date_from,date_to
```

### Audit
```
GET    /api/v1/audit-logs               ?subject_type,subject_id,causer_id,date_from,date_to,page
```

---

## RESPONSE ENVELOPE (USE EVERYWHERE)

### Success
```json
{
  "success": true,
  "data": {},
  "message": "string",
  "meta": { "pagination": { "page":1, "per_page":25, "total":0, "last_page":1 } }
}
```

### Error
```json
{
  "success": false,
  "message": "string",
  "errors": {},
  "error_code": "SNAKE_CASE_CODE"
}
```

---

## FINANCIAL VALIDATION RULES (SERVER-SIDE, NON-BYPASSABLE)

```
1. SUM(receipt_items.amount WHERE is_financial=true) == receipts.total_amount
2. Every receipt_item.amount >= 0
3. receipts.total_amount > 0
4. receipt_number is unique per register + fiscal_year
5. On issue: all required fields must have values
6. On revise: reason is mandatory, min 10 chars
7. On cancel: reason is mandatory, min 10 chars
```

---

## RECEIPT NUMBER GENERATION

```php
// Inside a DB transaction with SELECT FOR UPDATE on registers row:
$register = Register::lockForUpdate()->find($id);
$register->increment('current_sequence');
$number = sprintf('%s-%d-%06d', 
    $register->code, 
    $register->fiscal_year, 
    $register->current_sequence
);
// e.g.: GEN-2025-000142
```

---

## QR CODE PAYLOAD FORMAT

```json
{
  "sys": "GFRC",
  "num": "GEN-2025-000142",
  "amt": "75000.000",
  "date": "2025-06-15",
  "reg": "GEN",
  "usr": "cashier_username",
  "hash": "sha256(num+amt+date+APP_KEY)[0:16]"
}
```

---

## FRONTEND SCREENS (IMPLEMENT ALL)

1. **LoginPage** — username + password, RTL, error display
2. **DashboardPage** — today's totals per register, last 10 receipts table, quick-create button
3. **ReceiptListPage** — AG Grid table, filters (register, status, date range, search), export button
4. **ReceiptCreatePage** — select register → dynamic form renders from API fields → submit
5. **ReceiptDetailPage** — full receipt view, status badge, action buttons (issue/cancel/print/revise), revision history accordion
6. **ReceiptPrintView** — print-only layout (no navbar), A4, QR top-right, itemized breakdown, total in words
7. **RegisterListPage** — table of registers, activate/deactivate toggle
8. **RegisterDetailPage** — field builder: add/edit/delete fields, drag-to-reorder
9. **UserListPage** — users table, create/edit/deactivate
10. **UserFormPage** — user CRUD + role assignment checkboxes
11. **ReportsPage** — tabs: Daily / Monthly / User Activity / Register Summary, date pickers, print button
12. **AuditLogPage** — filterable table: subject, user, date range, action type

---

## FRONTEND COMPONENT STANDARDS

```
src/
  api/          # axios instance + one file per resource (receipts.ts, registers.ts, etc.)
  components/   # shared UI: Button, Input, Select, Badge, Modal, DataTable, PageHeader
  hooks/        # useReceipts, useRegisters, useAuth, usePermissions
  pages/        # one folder per screen
  stores/       # authStore.ts (zustand), uiStore.ts
  types/        # Receipt.ts, Register.ts, User.ts — all interfaces here
  utils/        # formatCurrency.ts, formatDate.ts, amountToWords.ts
```

RTL configuration: set `dir="rtl"` on `<html>`. Tailwind: add `rtl:` variants. Arabic font: Noto Sans Arabic via Google Fonts.

Currency display: `Intl.NumberFormat('ar-IQ', {style:'currency', currency:'IQD'})` — always 3 decimal places.

Amount to Arabic words: implement `amountToWords(amount: number): string` utility — converts `75000` to `خمسة وسبعون ألف دينار`.

---

## PRINT LAYOUT REQUIREMENTS

Print CSS (`@media print`): hide navbar/sidebar, show print-only elements.

Receipt print must include:
- Department name header (configurable in .env as `DEPT_NAME_AR`)
- Receipt number: large, bold, centered
- QR code: top-right, 3cm × 3cm
- Issue date + time
- Cashier name
- Register name
- Itemized table: field label | amount
- Separator line
- Total amount: right-aligned, large font
- Total in Arabic words below total
- Verification code (first 8 chars of QR hash)
- System stamp placeholder box (bottom-right)
- Signature line (bottom-left): "أمين الصندوق: ___________"

---

## DOCKER SETUP

```yaml
# docker-compose.yml structure:
services:
  nginx:    ports 80:80, depends on php
  php:      PHP 8.3-fpm, Laravel app
  postgres: postgres:15, persistent volume
  redis:    redis:7 (for cache + queue future use)
  node:     node:20 for React dev server (dev profile only)
```

Nginx config: serve React build from `/var/www/html/public`, proxy `/api/*` to `php:9000`.

---

## LARAVEL PROJECT STRUCTURE

```
app/
  Http/
    Controllers/Api/V1/    # thin controllers only
    Requests/              # FormRequest for every endpoint
    Resources/             # API Resource for every model
  Models/                  # Eloquent models with relationships + scopes
  Services/                # ReceiptService, RegisterService, ReportService
  Observers/               # ReceiptObserver, UserObserver (bound in AppServiceProvider)
  Policies/                # ReceiptPolicy, RegisterPolicy
  Rules/                   # FinancialTotalRule (custom validation rule)
database/
  migrations/              # one per table
  seeders/                 # DatabaseSeeder → RolesSeeder → AdminUserSeeder
routes/
  api.php                  # all /api/v1/ routes
```

---

## SEEDER DATA

### Roles & Permissions (seed these exactly):
```
Roles: super_admin, manager, cashier, auditor, data_entry

Permissions:
  receipts:    create-receipt, view-receipt, issue-receipt, cancel-receipt, revise-receipt, print-receipt
  registers:   manage-registers, view-registers
  users:       manage-users, view-users
  reports:     view-reports, export-reports
  audit:       view-audit-logs
  settings:    manage-settings

Role assignments:
  super_admin  → all permissions
  manager      → view-receipt, issue-receipt, cancel-receipt, view-registers, view-users, view-reports, export-reports, view-audit-logs
  cashier      → create-receipt, view-receipt, issue-receipt, print-receipt, view-registers
  auditor      → view-receipt, view-registers, view-reports, view-audit-logs
  data_entry   → create-receipt, view-receipt, view-registers
```

### Admin user seed:
```
name: مدير النظام
username: admin
password: Admin@12345  (hashed)
role: super_admin
```

---

## EXECUTION ORDER

Generate files in this exact order — do not skip ahead:

1. `docker-compose.yml`
2. `nginx/default.conf`
3. `backend/Dockerfile`
4. `backend/.env.example`
5. All Laravel migrations (in dependency order: users → roles → registers → register_fields → receipts → receipt_items → receipt_revisions → audit_logs extension)
6. All Laravel models
7. All Seeders
8. All FormRequest classes
9. All Service classes (ReceiptService, RegisterService, ReportService, AuditService)
10. All Observers
11. All API Resources
12. All Controllers
13. `routes/api.php`
14. `AppServiceProvider.php` (observer bindings, policy bindings)
15. `frontend/package.json`
16. `frontend/tailwind.config.ts`
17. `frontend/src/types/` (all type files)
18. `frontend/src/api/` (all API files)
19. `frontend/src/stores/`
20. `frontend/src/hooks/`
21. `frontend/src/utils/`
22. `frontend/src/components/` (shared UI)
23. All page components (in the order listed above)
24. `frontend/src/App.tsx` (router setup)
25. `frontend/src/main.tsx`
26. `README.md` (setup instructions only, no explanation)

---

## START NOW

Begin with file 1. Do not write any introduction. Do not confirm this prompt. Output the first file immediately.
