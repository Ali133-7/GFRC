# GFRC Workflow Engine — System Architecture Design
## Government Financial Receipt & Cash Management Platform
### Phase 4 Architecture Blueprint — v1.0

---

## 1. EXECUTIVE SUMMARY

The existing GFRC system (Phases 1–3) has built a **strong foundation** that maps closely to the Workflow Engine vision:

| Workflow Engine Requirement | GFRC Existing Implementation | Gap |
|----------------------------|------------------------------|-----|
| Dynamic Registers | ✅ `registers`, `register_fields` | Minor |
| Workflow / Steps | ✅ `transaction_templates`, `sections` JSON | No versioning |
| Dynamic Forms | ✅ `TransactionTemplateField` overrides | No field-level conditions |
| Conditional Logic | ✅ `TemplateRule` (single-field) | No AND/OR, no nesting |
| Fee Library | ✅ `official_fees`, `official_fee_categories` | No fee codes, no versioning |
| Receipt Generator | ✅ `GuidedReceiptController` | No calculation snapshot |
| Audit Trail | ✅ `spatie/activitylog` + custom extensions | Missing workflow_version in logs |
| RBAC | ✅ `spatie/laravel-permission` | Complete |
| Wizard UI | ✅ `ReceiptCreatePage` stepper | Needs keyboard nav, better UX |
| Immutable Receipts | ✅ Soft deletes, revisions | Needs stricter lock after issue |

**Conclusion:** We do NOT need to rebuild. We need to **evolve** the existing architecture into a true Workflow Engine by adding **versioning**, **workflow executions**, **calculation snapshots**, and **advanced rule engine v2**.

---

## 2. CORE ARCHITECTURE PRINCIPLE

### From "Templates" to "Workflow Engine"

The mental model shifts:

```
OLD: Transaction Template → Receipt
NEW:  Workflow Definition (versioned)
           ↓
      Workflow Execution (instance)
           ↓
      Step-by-step data collection
           ↓
      Rule Engine v2 evaluation
           ↓
      Fee Engine calculation
           ↓
      Receipt Generation (immutable snapshot)
```

### Key Abstractions

| Concept | Table(s) | Description |
|---------|----------|-------------|
| **Workflow Definition** | `workflows` | Versioned process blueprint. Replaces `transaction_templates`. |
| **Workflow Version** | `workflow_versions` | Each change creates a new version. Old receipts bind to old versions. |
| **Workflow Step** | `workflow_steps` | Ordered steps within a workflow version. Replaces `sections` JSON. |
| **Workflow Field** | `workflow_fields` | Field configuration per workflow version. Replaces `transaction_template_fields`. |
| **Workflow Rule** | `workflow_rules` | Conditional logic per workflow version. Replaces `template_rules`. |
| **Workflow Execution** | `workflow_executions` | A running instance of a workflow. Tracks state, values, step progress. |
| **Fee Library** | `official_fees` + `fee_versions` | Centralized fee codes with temporal versioning. |
| **Calculation Snapshot** | `receipts.calculation_snapshot` | JSON blob storing exact rules+fees used at creation time. |
| **Receipt** | `receipts` | Immutable financial record. Already well-designed. |

---

## 3. DATABASE SCHEMA EVOLUTION

### 3.1 New Tables

#### `workflows` — Workflow Registry
```sql
CREATE TABLE workflows (
    id UUID PRIMARY KEY,
    register_id UUID NOT NULL REFERENCES registers(id),
    code VARCHAR(50) UNIQUE NOT NULL,           -- e.g. "MERCHANT_REG"
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200),
    description TEXT,
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT true,
    current_version INTEGER DEFAULT 1,
    sort_order INTEGER DEFAULT 0,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP  -- soft delete
);
```

#### `workflow_versions` — Version Control
```sql
CREATE TABLE workflow_versions (
    id UUID PRIMARY KEY,
    workflow_id UUID NOT NULL REFERENCES workflows(id),
    version INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',         -- draft, active, archived
    published_at TIMESTAMP,
    archived_at TIMESTAMP,
    published_by UUID REFERENCES users(id),
    change_summary TEXT,                         -- human-readable changelog
    created_at TIMESTAMP,
    UNIQUE(workflow_id, version)
);
```

#### `workflow_steps` — Step Definitions (replaces `sections` JSON)
```sql
CREATE TABLE workflow_steps (
    id UUID PRIMARY KEY,
    workflow_version_id UUID NOT NULL REFERENCES workflow_versions(id) ON DELETE CASCADE,
    title_ar VARCHAR(200) NOT NULL,
    title_en VARCHAR(200),
    description TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    condition_logic JSONB,                      -- { "and": [...], "or": [...] } for step visibility
    is_visible BOOLEAN DEFAULT true,
    created_at TIMESTAMP
);
```

#### `workflow_fields` — Field Configurations
```sql
CREATE TABLE workflow_fields (
    id UUID PRIMARY KEY,
    workflow_version_id UUID NOT NULL REFERENCES workflow_versions(id) ON DELETE CASCADE,
    register_field_id UUID NOT NULL REFERENCES register_fields(id),
    step_id UUID REFERENCES workflow_steps(id) ON DELETE SET NULL,
    label_override VARCHAR(200),
    placeholder VARCHAR(200),
    default_value TEXT,
    is_required BOOLEAN DEFAULT false,
    is_visible BOOLEAN DEFAULT true,
    is_readonly BOOLEAN DEFAULT false,
    is_financial BOOLEAN DEFAULT false,
    sort_order INTEGER NOT NULL DEFAULT 0,
    condition_logic JSONB,                      -- field-level visibility conditions
    fee_code VARCHAR(50),                       -- links to official_fees.code
    calculation_formula TEXT,                    -- e.g. "base * quantity + tax"
    created_at TIMESTAMP
);
```

#### `workflow_rules` — Rule Engine v2 (replaces `template_rules`)
```sql
CREATE TABLE workflow_rules (
    id UUID PRIMARY KEY,
    workflow_version_id UUID NOT NULL REFERENCES workflow_versions(id) ON DELETE CASCADE,
    name VARCHAR(200),
    description TEXT,
    
    -- Condition (JSON for complex logic)
    condition_logic JSONB NOT NULL,             -- see Rule Engine v2 spec below
    
    -- Actions (array of actions)
    actions JSONB NOT NULL,                     -- [{ "action": "set_value", "target_field_id": "...", "value": "..." }, ...]
    
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP
);
```

#### `workflow_executions` — Running Instances
```sql
CREATE TABLE workflow_executions (
    id UUID PRIMARY KEY,
    workflow_version_id UUID NOT NULL REFERENCES workflow_versions(id),
    register_id UUID NOT NULL REFERENCES registers(id),
    
    -- State tracking
    status VARCHAR(20) DEFAULT 'in_progress',   -- in_progress, completed, cancelled, abandoned
    current_step_index INTEGER DEFAULT 0,
    
    -- Collected data
    values_snapshot JSONB DEFAULT '{}',          -- all field values collected so far
    calculated_items JSONB DEFAULT '[]',         -- fee calculation results
    total_amount DECIMAL(15,3) DEFAULT 0,
    
    -- Receipt linkage (nullable until completion)
    receipt_id UUID REFERENCES receipts(id),
    
    -- Meta
    started_by UUID NOT NULL REFERENCES users(id),
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    cancelled_at TIMESTAMP,
    cancel_reason TEXT,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### `fee_versions` — Temporal Fee Versioning
```sql
CREATE TABLE fee_versions (
    id UUID PRIMARY KEY,
    fee_id UUID NOT NULL REFERENCES official_fees(id),
    version INTEGER NOT NULL,
    amount DECIMAL(15,3) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE,
    change_reason TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP,
    UNIQUE(fee_id, version)
);
```

#### `receipt_calculation_snapshots` — Immutable Calculation Evidence
```sql
CREATE TABLE receipt_calculation_snapshots (
    id UUID PRIMARY KEY,
    receipt_id UUID NOT NULL REFERENCES receipts(id),
    workflow_version_id UUID NOT NULL,
    
    -- Exact state at creation time
    workflow_definition JSONB NOT NULL,          -- full workflow version snapshot
    rules_applied JSONB NOT NULL,                -- which rules fired and their results
    fees_used JSONB NOT NULL,                    -- fee codes + amounts at that moment
    field_values JSONB NOT NULL,                 -- all input values
    
    -- Verification
    calculation_hash VARCHAR(64) NOT NULL,       -- SHA-256 of above for integrity
    created_at TIMESTAMP
);
```

### 3.2 Modified Tables

#### `official_fees` — Add fee_code
```sql
ALTER TABLE official_fees ADD COLUMN fee_code VARCHAR(50) UNIQUE;
ALTER TABLE official_fees ADD COLUMN version INTEGER DEFAULT 1;
```

#### `receipts` — Add workflow linkage
```sql
ALTER TABLE receipts ADD COLUMN workflow_execution_id UUID REFERENCES workflow_executions(id);
ALTER TABLE receipts ADD COLUMN workflow_version_id UUID REFERENCES workflow_versions(id);
```

#### `activity_log` — Add workflow context
```sql
ALTER TABLE activity_log ADD COLUMN workflow_version_id UUID;
ALTER TABLE activity_log ADD COLUMN execution_id UUID;
```

### 3.3 Tables to Deprecate (Gradual Migration)

| Old Table | Replacement | Migration Strategy |
|-----------|-------------|-------------------|
| `transaction_templates` | `workflows` + `workflow_versions` | Copy data, keep old table read-only for 1 release |
| `transaction_template_fields` | `workflow_fields` | Migrate per workflow version |
| `template_rules` | `workflow_rules` | Migrate simple rules, manually upgrade complex ones |
| `transaction_templates.sections` JSON | `workflow_steps` | JSON parse → rows |

---

## 4. RULE ENGINE v2 DESIGN

### 4.1 Condition Logic JSON Schema

```json
{
  "operator": "and",
  "conditions": [
    {
      "field_id": "category_field_uuid",
      "operator": "equals",
      "value": "Premium"
    },
    {
      "operator": "or",
      "conditions": [
        {
          "field_id": "location_field_uuid",
          "operator": "equals",
          "value": "Inside City"
        },
        {
          "field_id": "location_field_uuid",
          "operator": "equals",
          "value": "Suburb"
        }
      ]
    },
    {
      "field_id": "quantity_field_uuid",
      "operator": "gt",
      "value": "5"
    }
  ]
}
```

### 4.2 Supported Operators

| Operator | Types | Description |
|----------|-------|-------------|
| `equals` | all | Exact match |
| `not_equals` | all | Inverse match |
| `contains` | text, select | Substring match |
| `starts_with` | text | Prefix match |
| `ends_with` | text | Suffix match |
| `gt` | number, decimal | Greater than |
| `gte` | number, decimal | Greater than or equal |
| `lt` | number, decimal | Less than |
| `lte` | number, decimal | Less than or equal |
| `in` | select, multi-select | Value in list |
| `not_in` | select, multi-select | Value not in list |
| `between` | number, decimal, date | Range check |
| `is_empty` | all | Null or empty string |
| `is_not_empty` | all | Not null and not empty |

### 4.3 Actions Schema

```json
[
  {
    "action": "set_value",
    "target_field_id": "fee_field_uuid",
    "value": "500000"
  },
  {
    "action": "set_fee",
    "target_field_id": "fee_field_uuid",
    "fee_code": "F001"
  },
  {
    "action": "calculate",
    "target_field_id": "total_field_uuid",
    "formula": "{{fee_1}} + {{fee_2}} * {{quantity}}"
  },
  {
    "action": "hide",
    "target_field_id": "conditional_field_uuid"
  },
  {
    "action": "show",
    "target_field_id": "conditional_field_uuid"
  },
  {
    "action": "set_required",
    "target_field_id": "field_uuid",
    "value": true
  },
  {
    "action": "skip_step",
    "target_step_id": "step_uuid"
  }
]
```

### 4.4 Evaluation Algorithm (PHP)

```php
class RuleEngineV2
{
    public function evaluate(array $rules, array $values, array $context): array
    {
        $results = [];
        foreach ($rules as $rule) {
            if (!$rule['is_active']) continue;
            
            $conditionMet = $this->evaluateCondition(
                $rule['condition_logic'], 
                $values, 
                $context
            );
            
            if ($conditionMet) {
                foreach ($rule['actions'] as $action) {
                    $results[] = $this->applyAction($action, $values, $context);
                }
            }
        }
        return $results;
    }
    
    private function evaluateCondition(array $condition, array $values, array $context): bool
    {
        $op = $condition['operator'] ?? 'and';
        
        if (in_array($op, ['and', 'or'])) {
            $results = array_map(
                fn($c) => $this->evaluateCondition($c, $values, $context),
                $condition['conditions'] ?? []
            );
            return $op === 'and' 
                ? !in_array(false, $results, true) 
                : in_array(true, $results, true);
        }
        
        // Leaf condition
        $fieldValue = $values[$condition['field_id']] ?? null;
        return $this->compare($fieldValue, $op, $condition['value']);
    }
}
```

---

## 5. FEE ENGINE DESIGN

### 5.1 Fee Resolution Algorithm

```php
class FeeEngine
{
    public function resolve(string $feeCode, ?DateTime $asOf = null): ?FeeVersion
    {
        $asOf ??= now();
        
        return FeeVersion::whereHas('fee', fn($q) => $q->where('fee_code', $feeCode))
            ->where('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $asOf);
            })
            ->orderByDesc('version')
            ->first();
    }
    
    public function calculate(array $feeCodes, array $values, array $context): array
    {
        $items = [];
        foreach ($feeCodes as $code) {
            $feeVersion = $this->resolve($code);
            if (!$feeVersion) continue;
            
            $items[] = [
                'fee_code' => $code,
                'fee_name' => $feeVersion->fee->name_ar,
                'amount' => $this->applyFormula($feeVersion, $values, $context),
                'version' => $feeVersion->version,
                'effective_at' => $feeVersion->effective_from,
            ];
        }
        return $items;
    }
}
```

### 5.2 Fee Formula Support

Fees can have formulas that reference field values:

```
base_amount                    -- flat fee
base_amount * quantity         -- per-unit fee
base_amount + (area - 100) * 50 -- tiered fee
max(base_amount, area * 10)    -- minimum fee
```

Formula evaluation uses a safe math parser (no `eval()`).

---

## 6. WORKFLOW EXECUTION LIFECYCLE

```
┌─────────────────┐
│   STARTED       │ ← User selects workflow type
│   (in_progress) │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  STEP N         │ ← User fills step N form
│  (data entry)   │   Rules evaluate in real-time
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────┐
│  LAST STEP?     │──NO─┤  NEXT STEP   │
│                 │     │  (advance)   │
└────────┬────────┘     └──────────────┘
        YES
         │
         ▼
┌─────────────────┐
│  PREVIEW        │ ← Show all fees, total, summary
│  (review)       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  CONFIRM        │ ← User clicks "Generate Receipt"
│                 │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  COMPLETED      │ ← Receipt created, snapshot saved
│  (immutable)    │   Execution linked to receipt
└─────────────────┘
```

### 6.1 Execution State Machine

| State | Transitions | Description |
|-------|-------------|-------------|
| `in_progress` | → `completed`, `cancelled`, `abandoned` | Active data entry |
| `completed` | (terminal) | Receipt generated successfully |
| `cancelled` | (terminal) | User explicitly cancelled |
| `abandoned` | (terminal) | Auto-cleanup after timeout (e.g., 24h) |

---

## 7. AUDIT SYSTEM DESIGN

### 7.1 Audit Scope Matrix

| Action | Logged By | Context Captured |
|--------|-----------|-----------------|
| Workflow created | ActivityLog | old=null, new=full definition |
| Workflow published | ActivityLog | version N→N+1, change_summary |
| Workflow archived | ActivityLog | version, archived_at |
| Fee changed | ActivityLog + FeeVersion | old_amount, new_amount, reason |
| Execution started | workflow_executions + ActivityLog | ip, user_agent, workflow_version_id |
| Step advanced | workflow_executions (values_snapshot) | step_index, values diff |
| Receipt generated | receipts + calculation_snapshot | full snapshot with hash |
| Receipt cancelled | ActivityLog | old=issued, new=cancelled, reason |
| Receipt revised | ReceiptRevision | old_snapshot, new_snapshot |

### 7.2 Immutable Audit Guarantees

1. **No UPDATE on audit tables** — `activity_log` has no `updated_at` column.
2. **No DELETE on audit tables** — Soft delete only, with `deleted_by` tracking.
3. **Cryptographic hash** — `receipt_calculation_snapshots.calculation_hash` = SHA-256 of serialized snapshot.
4. **Chain of custody** — Each receipt links to its execution, which links to its workflow version, which links to its workflow definition.

---

## 8. API DESIGN

### 8.1 Workflow Management APIs

```
GET    /api/v1/workflows                     # List workflows
POST   /api/v1/workflows                     # Create workflow
GET    /api/v1/workflows/{id}                # Get workflow + current version
PUT    /api/v1/workflows/{id}                # Update workflow metadata
DELETE /api/v1/workflows/{id}                # Soft delete

GET    /api/v1/workflows/{id}/versions       # List all versions
POST   /api/v1/workflows/{id}/versions       # Create new version (draft)
GET    /api/v1/workflows/{id}/versions/{v}   # Get specific version
PUT    /api/v1/workflows/{id}/versions/{v}   # Edit draft version
POST   /api/v1/workflows/{id}/versions/{v}/publish   # Publish version
POST   /api/v1/workflows/{id}/versions/{v}/archive   # Archive version
POST   /api/v1/workflows/{id}/versions/{v}/clone     # Clone to new draft

GET    /api/v1/workflows/{id}/versions/{v}/steps     # List steps
POST   /api/v1/workflows/{id}/versions/{v}/steps     # Create step
PUT    /api/v1/workflows/{id}/versions/{v}/steps/{s} # Update step
DELETE /api/v1/workflows/{id}/versions/{v}/steps/{s} # Delete step
PATCH  /api/v1/workflows/{id}/versions/{v}/steps/reorder

GET    /api/v1/workflows/{id}/versions/{v}/fields    # List fields
POST   /api/v1/workflows/{id}/versions/{v}/fields    # Add field
PUT    /api/v1/workflows/{id}/versions/{v}/fields/{f}# Update field
DELETE /api/v1/workflows/{id}/versions/{v}/fields/{f}# Remove field

GET    /api/v1/workflows/{id}/versions/{v}/rules     # List rules
POST   /api/v1/workflows/{id}/versions/{v}/rules     # Create rule
PUT    /api/v1/workflows/{id}/versions/{v}/rules/{r} # Update rule
DELETE /api/v1/workflows/{id}/versions/{v}/rules/{r} # Delete rule
```

### 8.2 Workflow Execution APIs

```
POST   /api/v1/workflow-executions             # Start execution
GET    /api/v1/workflow-executions/{id}        # Get execution state
PUT    /api/v1/workflow-executions/{id}/step   # Submit step data + advance
POST   /api/v1/workflow-executions/{id}/preview # Preview calculation
POST   /api/v1/workflow-executions/{id}/complete # Generate receipt
POST   /api/v1/workflow-executions/{id}/cancel  # Cancel execution
GET    /api/v1/workflow-executions/{id}/audit    # Full audit trail
```

### 8.3 Fee Engine APIs

```
GET    /api/v1/fees                            # List fees (with current version)
POST   /api/v1/fees                            # Create fee
GET    /api/v1/fees/{id}                       # Get fee + version history
PUT    /api/v1/fees/{id}                       # Update fee metadata
POST   /api/v1/fees/{id}/versions              # Add new fee version
GET    /api/v1/fees/{id}/versions              # List fee versions
GET    /api/v1/fees/resolve/{code}             # Resolve fee code to current amount
POST   /api/v1/fees/bulk-resolve               # Resolve multiple codes at date
```

### 8.4 Receipt APIs (Extended)

```
GET    /api/v1/receipts/{id}/snapshot          # Get calculation snapshot
GET    /api/v1/receipts/{id}/reproduce          # Re-run calculation from snapshot
```

---

## 9. FRONTEND ARCHITECTURE

### 9.1 Page Structure

```
pages/
├── workflows/
│   ├── WorkflowListPage.tsx          # Grid of workflow cards
│   ├── WorkflowFormPage.tsx          # Create/edit workflow metadata
│   ├── WorkflowVersionPage.tsx       # Version timeline + diff
│   ├── WorkflowDesignerPage.tsx      # Visual step/field/rule builder
│   └── WorkflowExecutionPage.tsx     # Run a workflow (wizard)
├── receipts/
│   ├── ReceiptCreatePage.tsx         # Entry point → choose workflow
│   ├── ReceiptListPage.tsx           # (existing)
│   ├── ReceiptDetailPage.tsx         # + snapshot viewer tab
│   └── ReceiptPrintPage.tsx          # (existing)
├── fees/
│   ├── FeeLibraryPage.tsx            # Fee list + version timeline
│   └── FeeFormPage.tsx               # Create/edit fee + versions
├── audit/
│   └── AuditLogPage.tsx              # (existing, enhanced)
├── dashboard/
│   └── DashboardPage.tsx             # (existing)
└── ...
```

### 9.2 Wizard Component Architecture

```
WorkflowWizard
├── WizardHeader
│   ├── StepIndicator (dots + titles)
│   ├── ProgressBar
│   └── CancelButton
├── WizardBody
│   └── StepRenderer
│       ├── DynamicForm (fields for current step)
│       ├── RealTimeFeePanel (sticky sidebar)
│       └── RuleFeedback (which rules fired)
├── WizardFooter
│   ├── BackButton
│   ├── NextButton
│   └── CompleteButton (on review step)
└── WizardStateMachine
    ├── useWorkflowExecution()  ← TanStack Query
    ├── useRuleEngine()         ← WebSocket or polling
    └── useFeeCalculator()      ← debounced API calls
```

### 9.3 Keyboard Navigation

| Key | Action |
|-----|--------|
| `Tab` | Next field |
| `Shift+Tab` | Previous field |
| `Enter` | Submit field / Next step |
| `Ctrl+Enter` | Complete workflow |
| `Esc` | Cancel / Close modal |
| `↑/↓` | Select dropdown options |
| `Ctrl+S` | Save draft |

---

## 10. IMPLEMENTATION PHASES

### Phase A: Foundation (Week 1–2)
1. Database migrations for new tables
2. Models + relationships
3. Fee Engine v2 (`fee_code`, `fee_versions`, resolution algorithm)
4. Rule Engine v2 (JSON conditions, AND/OR, nested)

### Phase B: Workflow Engine Core (Week 3–4)
1. Workflow CRUD APIs
2. Workflow Versioning (publish, archive, clone)
3. Workflow Steps API
4. Workflow Fields API
5. Workflow Rules API with v2 engine

### Phase C: Execution Engine (Week 5–6)
1. Workflow Execution lifecycle
2. Step-by-step data collection API
3. Real-time preview/calculation API
4. Receipt generation with snapshot
5. Calculation snapshot storage + hash

### Phase D: Frontend (Week 7–8)
1. Workflow Designer UI (visual builder)
2. Workflow Version Timeline UI
3. Workflow Execution Wizard (keyboard-friendly)
4. Fee Library with version history
5. Receipt detail with snapshot viewer

### Phase E: Migration & Polish (Week 9–10)
1. Data migration from `transaction_templates` → `workflows`
2. Audit log enhancements
3. Performance optimization
4. Integration testing
5. Documentation

---

## 11. SECURITY & COMPLIANCE

### 11.1 Data Integrity
- All financial calculations stored with SHA-256 hash
- Receipts immutable after issue (only cancel + revision)
- Calculation snapshots reproducible from stored data

### 11.2 Access Control
| Role | Workflows | Rules | Fees | Receipts | Audit |
|------|-----------|-------|------|----------|-------|
| Super Admin | CRUD | CRUD | CRUD | All | All |
| Manager | View | View | View | All | All |
| Cashier | View only active | None | None | Create/View own | None |
| Auditor | View | View | View | View | View |
| Data Entry | View only active | None | None | Create/View own | None |

### 11.3 Backup Strategy
- Daily automated backups (AES-256-CBC encrypted)
- Point-in-time recovery via calculation snapshots
- Export/import with version preservation

---

## 12. SCALABILITY CONSIDERATIONS

| Concern | Solution |
|---------|----------|
| High concurrent executions | Queue workflow completions, use optimistic locking on receipt numbers |
| Large rule sets | Cache compiled rule trees in Redis per workflow version |
| Fee lookups | Cache fee resolution results with TTL = `effective_to` |
| Audit log growth | Partition `activity_log` by month, archive to cold storage after 2 years |
| Receipt printing | Queue PDF generation, use pre-signed URLs for download |
| Frontend bundle | Lazy-load wizard components, code-split by workflow type |

---

*Document Version: 1.0*
*Date: 2026-05-26*
*Status: Architecture Design — Pending Approval*
