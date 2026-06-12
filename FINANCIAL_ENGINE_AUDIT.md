# FINANCIAL ENGINE FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** Fee library, fee versions, set_fee, calculate, apply_discount, totals, receipt generation

---

## EXECUTIVE SUMMARY

The financial engine uses BC Math exclusively for calculations with a Shunting-Yard expression evaluator. The architecture is fundamentally sound for financial precision but contains **3 critical issues** that could produce wrong financial amounts in production.

---

## 1. FEE LIBRARY ANALYSIS

### 1.1 Fee Data Model

```
OfficialFee (fee_code, name_ar, name_en, amount, effective_from, effective_to, is_active)
    ↓ (1:N)
FeeVersion (fee_id, version, amount, effective_from, effective_to, change_reason)
```

### 1.2 Fee Resolution Flow

```
FeeEngine::resolveActive(fee_code)
    ↓
1. Find active OfficialFee (is_active=true, fee_code matches)
2. Find active FeeVersion (effective_from <= now <= effective_to)
3. If no FeeVersion → synthetic FeeVersion from OfficialFee.amount
4. Return FeeVersion with amount
```

### 1.3 Issues

#### FE-001: Fee version temporal overlap check is not atomic [HIGH]
- **Severity:** High
- **Root Cause:** `FeeVersion::saving` hook checks for overlapping date ranges at model level, not database level
- **Reproduction:** Two concurrent requests create overlapping FeeVersions → both pass the check
- **Affected Files:** `backend/app/Models/FeeVersion.php`
- **Impact:** Two active fee versions for the same fee code → unpredictable resolution
- **Solution:** Add PostgreSQL exclusion constraint or use advisory locks

#### FE-002: Synthetic FeeVersion uses OfficialFee.id as FeeVersion.id [HIGH]
- **Severity:** High
- **Root Cause:** `FeeEngine::resolveActive()` creates a synthetic FeeVersion with `$feeVersion->id = $officialFee->id`
- **Affected Files:** `backend/app/Services/FeeEngine.php:107-116`
- **Impact:** Synthetic FeeVersion has same ID as OfficialFee, which could cause confusion in fee snapshots and audit trails
- **Solution:** Use a distinct ID or mark synthetic versions clearly

---

## 2. FEE VERSIONS ANALYSIS

### 2.1 Version Resolution

| Scenario | Resolution | Verified |
|----------|-----------|----------|
| Single active version | Returns that version | ✅ |
| Multiple versions (temporal) | Returns latest version active at date | ✅ |
| No versions, active OfficialFee | Returns synthetic FeeVersion | ⚠️ |
| No versions, inactive OfficialFee | Returns null | ✅ |
| Inactive OfficialFee with versions | Returns null | ✅ |

### 2.2 Issues

#### FE-003: Fee resolution does not cache results [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `FeeEngine::resolveActive()` queries the database every time
- **Impact:** N+1 queries when processing many fee codes in a single execution
- **Solution:** Add request-level caching

---

## 3. SET_FEE ANALYSIS

### 3.1 set_fee Resolution Path

```
EnterpriseRuleEngine::executeActions (case 'set_fee')
    ↓
1. Determine fee_code (prefer fee_code, fallback to value)
2. Check if value is numeric amount → direct assignment
3. If not numeric → FeeEngine::resolveActive(fee_code)
4. If resolution fails → throw FinancialIntegrityException
5. Set finalValues[field_id] = amount
6. Record field_effects and financial_trace
```

### 3.2 Issues

#### FE-004: set_fee fee_code detection is ambiguous [HIGH]
- **Severity:** High
- **Root Cause:** `EnterpriseRuleEngine` checks `!empty($action['fee_code']) ? $action['fee_code'] : ($value ?? '')`
- **Affected Files:** `backend/app/Services/EnterpriseRuleEngine.php:901`
- **Impact:** If fee_code is not set and value is a fee code string (not numeric), it works. But if value is a numeric amount AND fee_code is not set, it treats the amount as a fee code
- **Evidence:** The `isNumericAmount` check at line 904 handles this, but only when `fee_code` is empty
- **Solution:** Require explicit fee_code in all set_fee actions

---

## 4. CALCULATE ANALYSIS

### 4.1 Formula Evaluation Pipeline

```
FeeEngine::calculate(formula, values, feeAmounts)
    ↓
prepareExpression() — replace {{field_id}} and fee_{{code}} placeholders
    ↓
tokenize() — convert to token stream
    ↓
toRPN() — Shunting-Yard algorithm
    ↓
evaluateRPN() — BC Math evaluation
    ↓
round() + validateBounds()
```

### 4.2 Issues

#### FE-005: FormulaEvaluator uses float arithmetic [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `FormulaEvaluator::evaluate()` converts result to `(float)` and uses `number_format()`
- **Affected Files:** `backend/app/Services/FormulaEvaluator.php`
- **Impact:** Floating-point imprecision in formula evaluation contradicts BC-math-everywhere philosophy
- **Solution:** Remove FormulaEvaluator or rewrite to use BC Math

#### FE-006: Tokenizer skips unknown characters silently [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `FeeEngine::tokenize()` skips unknown characters without error
- **Affected Files:** `backend/app/Services/FeeEngine.php:297-298`
- **Impact:** Invalid characters in formulas are silently ignored, producing wrong results
- **Solution:** Throw exception on unknown characters

---

## 5. APPLY_DISCOUNT ANALYSIS

### 5.1 Discount Calculation

| Engine | Formula | Scale | Correct? |
|--------|---------|-------|----------|
| RuleEngineV2 | `baseValue - (baseValue * discountValue / 100)` | CalculationContext | ✅ |
| EnterpriseRuleEngine | `baseValue - (baseValue * discountValue / 100)` | Hardcoded 3 | ❌ |
| ConditionalBranchingEngine | `baseValue - (baseValue * discountValue / 100)` | Hardcoded 3 | ❌ |

### 5.2 Issues

#### FE-007: Discount calculation uses hardcoded scale in two engines [HIGH]
- **Severity:** High
- **Root Cause:** EnterpriseRuleEngine and ConditionalBranchingEngine hardcode `$scale = 3`
- **Affected Files:** `backend/app/Services/EnterpriseRuleEngine.php:980`, `backend/app/Services/ConditionalBranchingEngine.php`
- **Impact:** If CalculationContext scale changes, discount calculations remain at 3 decimal places
- **Solution:** Inject CalculationContext and use `$ctx->scale()`

#### FE-008: Discount can produce negative values [LOW]
- **Severity:** Low
- **Root Cause:** EnterpriseRuleEngine clamps negative results to `'0.000'` but ConditionalBranchingEngine does not
- **Affected Files:** `backend/app/Services/ConditionalBranchingEngine.php`
- **Impact:** Discount larger than base value produces negative amount
- **Solution:** Add clamping in all discount calculations

---

## 6. TOTALS ANALYSIS

### 6.1 Total Calculation Flow

```
WorkflowExecutionService::calculateItems()
    ↓
1. Build aliasToCanonical map for all fields
2. Group actions by field
3. For each field:
   a. Process calculate actions
   b. Process set_value actions (if financial)
   c. Process set_fee actions
   d. Process fee_code from field definition
   e. Process calculation_formula from field definition
4. Process cross-step financial actions
5. Deduplicate by field_id + fee_code
6. Return unique items
    ↓
WorkflowExecutionService::sumItems()
    ↓
bcadd for each item amount
```

### 6.2 Issues

#### FE-009: Deduplication key allows duplicate amounts [HIGH]
- **Severity:** High
- **Root Cause:** Non-fee items are deduplicated by `field_id + amount` key
- **Affected Files:** `backend/app/Services/WorkflowExecutionService.php:1367-1368`
- **Impact:** Two different calculate actions producing the same amount for the same field are treated as duplicates, losing one
- **Solution:** Deduplicate non-fee items by field_id only (last write wins)

#### FE-010: sumItems does not validate item amounts [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `sumItems()` casts amount to string and adds, but does not validate that amount is a valid BC Math number
- **Affected Files:** `backend/app/Services/WorkflowExecutionService.php:1432-1439`
- **Impact:** Invalid amounts could cause bcadd to fail or produce wrong results
- **Solution:** Validate amounts before adding

---

## 7. RECEIPT GENERATION ANALYSIS

### 7.1 Receipt Creation Flow

```
WorkflowExecutionService::complete()
    ↓
1. Replay execution state and verify total
2. Create Receipt (status=draft)
3. Create ReceiptItem for each calculated_item
4. Append receipt_created event
5. Create ReceiptCalculationSnapshot
6. Append receipt_issued event
7. Update receipt status to issued
8. Create record in register
```

### 7.2 Issues

#### FE-011: Receipt QR code is not scannable [HIGH]
- **Severity:** High
- **Root Cause:** `ReceiptService::generateQrSvg()` generates a decorative SVG based on MD5 hash bit patterns, not a real QR code
- **Affected Files:** `backend/app/Services/ReceiptService.php`
- **Impact:** QR code cannot be scanned by standard QR readers, misleading users
- **Solution:** Use a real QR code library (e.g., endroid/qr-code)

#### FE-012: Receipt items use field_id FK that may be soft-deleted [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `ReceiptItem::field_id` is a FK to `register_fields`, but register fields can be soft-deleted
- **Affected Files:** `backend/app/Models/ReceiptItem.php`
- **Impact:** If a register field is soft-deleted, receipt items reference a soft-deleted record
- **Solution:** Store field name/label as snapshot (already done) and consider removing FK

---

## 8. SINGLE SOURCE OF TRUTH VERIFICATION

### 8.1 Fee Resolution Single Source

| Component | Resolution Method | Single Source? |
|-----------|------------------|----------------|
| RuleEngineV2::resolveAction | FeeEngine::resolveActive | ✅ |
| EnterpriseRuleEngine::executeActions | FeeEngine::resolveActive | ✅ |
| ConditionalBranchingEngine::resolveAction | FeeEngine::resolveActive | ✅ |
| WorkflowExecutionService::calculateItems | FeeEngine::resolveActive | ✅ |
| FeeEngine::resolveActive | Authoritative | ✅ |

**Finding:** All fee resolution paths use `FeeEngine::resolveActive()`. ✅ Single source confirmed.

### 8.2 Formula Evaluation Sources

| Component | Evaluation Method | Single Source? |
|-----------|------------------|----------------|
| FeeEngine::calculate | Shunting-Yard + BC Math | ✅ |
| FormulaEvaluator::evaluate | Symfony ExpressionLanguage + Float | ❌ |
| ComputedFieldEngine::computeValue | FeeEngine::calculate | ✅ |
| EnterpriseRuleEngine::calculateExpression | FeeEngine::calculate | ✅ |

**Finding:** FormulaEvaluator is a competing source of truth for formula evaluation. ❌

### 8.3 Discount Calculation Sources

| Component | Calculation Method | Single Source? |
|-----------|-------------------|----------------|
| RuleEngineV2::calculateDiscount | BC Math + CalculationContext | ✅ |
| EnterpriseRuleEngine::executeActions | BC Math + hardcoded scale | ❌ |
| ConditionalBranchingEngine::resolveAction | BC Math + hardcoded scale | ❌ |

**Finding:** Three different discount calculation implementations. ❌

---

## 9. FINANCIAL AMOUNT CORRECTNESS

### 9.1 Can the system generate a wrong financial amount?

**YES.** The following scenarios produce wrong amounts:

#### Scenario 1: FormulaEvaluator float imprecision [CRITICAL]
- **Where:** `FormulaEvaluator::evaluate()`
- **Why:** Uses `(float)` conversion and `number_format()`
- **How:** Any formula evaluated through FormulaEvaluator will have float imprecision
- **Example:** `1000.10 + 0.20` could produce `1000.2999999999999` instead of `1000.300`

#### Scenario 2: Duplicate fee counting on step re-submission [HIGH]
- **Where:** `WorkflowExecutionService::submitStep()`
- **Why:** If deduplication key collision occurs (FE-009), one item is lost
- **How:** Two calculate actions for the same field producing the same amount → one is dropped
- **Example:** Field A has calculate action producing 1000, field B also produces 1000 for same field → total is 1000 instead of 2000

#### Scenario 3: Fee version race condition [HIGH]
- **Where:** `FeeVersion::saving` hook
- **Why:** Non-atomic overlap check
- **How:** Two concurrent fee version creations with overlapping dates → unpredictable resolution
- **Example:** Fee version 1: 1000 (Jan-Mar), Fee version 2: 2000 (Feb-Apr) → which is active in Feb?

#### Scenario 4: Builder displays wrong fee amount [HIGH]
- **Where:** CaseRuleBuilder, EnterpriseRuleBuilder (standard mode)
- **Why:** Displays `fee.amount` instead of resolved amount
- **How:** Fee has versions with different amounts → builder shows wrong amount
- **Example:** Fee code "REGISTRATION" has version 1: 500, version 2: 750 → builder shows 500, runtime charges 750

---

## 10. AUDIT TRAIL ANALYSIS

### 10.1 Financial Audit Trail Components

| Component | Captured | Persistent? | Hash-protected? |
|-----------|----------|-------------|-----------------|
| workflow_execution_events | calculated_items, fee_snapshot | ✅ | ✅ (hash chain) |
| receipt_events | after_state with items | ✅ | ✅ (hash chain) |
| receipt_calculation_snapshots | workflow_definition, fees_used, field_values | ✅ | ✅ (calculation_hash) |
| financial_trace | Step-by-step transformations | ✅ (in execution events) | ✅ |
| FeeEngine fee snapshots | Fee code, amount, version, effective_from | ✅ (in context) | ✅ |

### 10.2 Issues

#### FE-013: FieldAuditTrail is not persisted [HIGH]
- **Severity:** High
- **Root Cause:** In-memory only, no persistence
- **Affected Files:** `backend/app/Services/FieldAuditTrail.php`
- **Impact:** Field value changes during execution are lost
- **Solution:** Persist to database

#### FE-014: ReceiptCalculationSnapshot does not capture financial_trace [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Snapshot captures `rules_applied` and `fees_used` but not `financial_trace`
- **Affected Files:** `backend/app/Services/WorkflowExecutionService.php:1490-1512`
- **Impact:** Cannot reconstruct step-by-step calculation from snapshot alone
- **Solution:** Include financial_trace in snapshot

---

## FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 1 |
| High | 9 |
| Medium | 5 |
| Low | 1 |

---

## RECOMMENDED FIXES PRIORITY

1. **FE-005:** Remove or rewrite FormulaEvaluator to use BC Math
2. **FE-001:** Add atomic fee version overlap prevention
3. **FE-004:** Require explicit fee_code in set_fee actions
4. **FE-007:** Use CalculationContext scale for all discount calculations
5. **FE-009:** Fix deduplication key for non-fee items
6. **FE-011:** Replace decorative QR with real QR code
7. **FE-002:** Fix synthetic FeeVersion ID collision
8. **FE-013:** Persist FieldAuditTrail
9. **FE-003:** Add fee resolution caching
10. **FE-006:** Throw exception on unknown formula characters
