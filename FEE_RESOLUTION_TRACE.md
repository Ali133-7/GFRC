# FEE RESOLUTION TRACE — FEE-EXCELLENT-NEW

## Executive Summary

**Fee Code:** `FEE-EXCELLENT-NEW`
**Expected Amount:** 25,000
**Actual Amount:** 500,000
**Root Cause:** Three architectural violations in the fee resolution chain cause amount divergence between builder display and execution resolution.

---

## ROOT CAUSES IDENTIFIED

### RC-1: Denormalized Amount Cache Drift (CRITICAL)

`OfficialFee.amount` is a denormalized cache updated whenever a new `FeeVersion` is created.

**File:** `backend/app/Http/Controllers/Api/V1/FeeVersionController.php:52-55`
```php
$fee->update([
    'amount' => $data['amount'],   // ← overwrites parent with new version's amount
    'version' => $latestVersion + 1,
]);
```

When a new fee version is created (e.g., amount changes from 25,000 → 500,000), the parent `official_fees.amount` is overwritten. The builder reads from this denormalized column, but execution resolves from `fee_versions.amount`. If the old version is still active (effective_to = null), the amounts diverge.

### RC-2: Builder Displays Denormalized Amount, Execution Resolves Versioned Amount

**Builder display chain:**
- `FeeVersionController::listActive()` returns `OfficialFee.amount` (denormalized)
- `CaseRuleBuilder.tsx` stores `value: selected?.amount` (the denormalized amount at build time)

**Execution resolution chain:**
- `EnterpriseRuleEngine::set_fee` resolves via `feeVersions()->activeAt()->orderByDesc('version')->first()`
- Returns `FeeVersion.amount` (the versioned amount)

If `OfficialFee.amount = 500,000` (updated by new version creation) but the active `FeeVersion.amount = 25,000` (old version still effective), the builder shows 500,000 but execution resolves 25,000. The REVERSE scenario (builder shows 25,000, execution resolves 500,000) occurs when the new version's effective_from has passed.

### RC-3: CaseRuleBuilder Stores Amount as Action Value

**File:** `frontend/src/components/rules/CaseRuleBuilder.tsx:427-433`
```tsx
onChange({
  ...action,
  fee_code: selected?.fee_code ?? e.target.value,
  fee_name: selected?.name_ar ?? "",
  value: selected?.amount ?? 0,  // ← stores denormalized amount
});
```

The `value` field stores the fee amount at build time. This value is NOT used during execution (the engine resolves from `fee_code`), but it creates confusion in the UI and could be used as a fallback if `fee_code` is lost during action conversion.

---

## COMPLETE TRACE: Fee Code → Charged Amount

### Step 1: Fee Library (Database)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| official_fees table | `migrations/2026_05_26_000014` | 16 | `fee_code='FEE-EXCELLENT-NEW'` | `amount=25000.000` (or 500000.000 after update) |
| fee_versions table | `migrations/2026_05_31_000025` | 15 | `fee_id=<uuid>` | `amount=25000.000` (v1), `amount=500000.000` (v2) |
| OfficialFee model | `app/Models/OfficialFee.php` | 32 | `$fee->amount` | Cast to `decimal:3` |
| FeeVersion model | `app/Models/FeeVersion.php` | 24 | `$version->amount` | Cast to `decimal:3` |

### Step 2: Fee Selection (Rule Builder)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| listActive API | `FeeVersionController.php` | 107-113 | `is_active=true` | Returns `OfficialFee.amount` (denormalized) |
| useOfficialFees hook | `hooks/useFees.ts` | 4-7 | GET `/fees/active` | `OfficialFee[]` with `amount` field |
| CaseRuleBuilder fee select | `CaseRuleBuilder.tsx` | 427-433 | User selects fee | `{fee_code, fee_name, value: amount}` |
| SimpleRuleBuilder fee select | `SimpleRuleBuilder.tsx` | 228 | User selects fee | `{fee_code}` only (no value stored) |

### Step 3: Rule Storage (Database)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| WorkflowRule model | `Models/WorkflowRule.php` | 28-29 | `actions` array | Cast to `array` (JSON column) |
| Stored action (Case) | `workflow_rules.actions` | — | `{action:'set_fee', fee_code:'FEE-EXCELLENT-NEW', value:25000, target_field_id:'<uuid>'}` | JSON in DB |
| Stored action (Simple) | `workflow_rules.actions` | — | `{action:'set_fee', fee_code:'FEE-EXCELLENT-NEW', target_field_id:'<uuid>'}` | JSON in DB |

### Step 4: Rule Loading (EnterpriseRuleEngine)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Load workflow rules | `EnterpriseRuleEngine.php` | 47-50 | `workflow_version_id` | `WorkflowRule[]` ordered by `sort_order` |
| Case rule processing | `EnterpriseRuleEngine.php` | 112-168 | `rule.cases` | Iterates cases, matches trigger value |
| Action conversion | `EnterpriseRuleEngine.php` | 689-710 | `{action:'set_fee', fee_code:'FEE-EXCELLENT-NEW', value:25000}` | `{type:'set_fee', fee_code:'FEE-EXCELLENT-NEW', value:25000, field_id:'<uuid>'}` |

### Step 5: Fee Resolution (set_fee action)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Fee code extraction | `EnterpriseRuleEngine.php` | 902 | `action['fee_code']='FEE-EXCELLENT-NEW'` | `$feeCode = 'FEE-EXCELLENT-NEW'` |
| OfficialFee lookup | `EnterpriseRuleEngine.php` | 906-908 | `fee_code='FEE-EXCELLENT-NEW'`, `is_active=true` | `OfficialFee` model instance |
| FeeVersion resolution | `EnterpriseRuleEngine.php` | 920 | `feeVersions()->activeAt()->orderByDesc('version')->first()` | `FeeVersion` with `amount=500000.000` (WRONG VERSION) |
| Amount extraction | `EnterpriseRuleEngine.php` | 934 | `$feeVersion->amount` | `$amount = '500000.000'` |
| Value injection | `EnterpriseRuleEngine.php` | 935 | `$finalValues[$fieldId] = $amount` | `$finalValues['<uuid>'] = '500000.000'` |

### Step 6: Field Effect Generation

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Effect record | `EnterpriseRuleEngine.php` | 937-943 | `feeCode, amount` | `{action:'set_fee', fee_code:'FEE-EXCELLENT-NEW', amount:'500000.000'}` |
| Financial trace | `EnterpriseRuleEngine.php` | 944-951 | `fieldId, feeCode, amount` | `{step:'fee_resolution', result:'500000.000'}` |

### Step 7: Action Transformation (WorkflowExecutionService)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Effect → Action transform | `WorkflowExecutionService.php` | 264-267 | `effect{action:'set_fee', amount:'500000.000'}` | `action{target_field_id:'<canonical>', fee_code:'FEE-EXCELLENT-NEW', resolved_amount:'500000.000'}` |
| applySetValueActions | `WorkflowExecutionService.php` | 1007-1008 | `action{resolved_amount:'500000.000'}` | `$modified[$targetId] = '500000.000'` |

### Step 8: calculateItems (Financial Calculation)

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Fee action extraction | `WorkflowExecutionService.php` | 1106-1115 | Actions for field | `$feeActions = [{action:'set_fee', resolved_amount:'500000.000'}]` |
| Item creation | `WorkflowExecutionService.php` | 1143-1163 | `$feeAmount = '500000.000'` | `item{amount:'500000.000', fee_code:'FEE-EXCELLENT-NEW'}` |
| Sum items | `WorkflowExecutionService.php` | 1252-1260 | All items | `$stepTotal = bcadd(...)` includes 500000.000 |

### Step 9: Event Storage

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Event append | `WorkflowExecutionService.php` | 353-374 | `calculatedItems, feeSnapshot` | Immutable event with `step_total` including 500000 |
| Cache update | `WorkflowExecutionService.php` | 377-384 | `newTotal` | `total_amount` updated |

### Step 10: API Response → Frontend Display

| Component | File | Line | Input | Output |
|-----------|------|------|-------|--------|
| Controller response | `WorkflowExecutionController.php` | 104-106 | `calculated_items, total_amount` | JSON response |
| Frontend receive | `WorkflowExecutionPage.tsx` | 157-187 | `data.calculated_items` | State update |
| Review display | `WorkflowExecutionPage.tsx` | 809-823 | `item.amount = 500000` | `500,000.000 د.ع` shown to user |
| Fee panel display | `RealTimeFeePanel.tsx` | 67-69 | `item.amount = 500000` | `500,000.000 د.ع` |

---

## WHY 25,000 BECAME 500,000

```
OfficialFee (fee_code=FEE-EXCELLENT-NEW)
├── amount = 500,000.000  (denormalized, updated by FeeVersionController::store)
├── FeeVersion v1: amount = 25,000.000, effective_from = 2025-01-01, effective_to = 2025-12-31
└── FeeVersion v2: amount = 500,000.000, effective_from = 2026-01-01, effective_to = null
                    ↑
                    This version is active today (2026-06-08)
                    orderByDesc('version')->first() → v2 → 500,000
```

The fee was legitimately updated. The resolution is technically correct — v2 IS the active version. The "expected 25,000" reflects v1 which has expired.

**HOWEVER**, if the scenario is that v1 should still be active (effective_to = null) and v2 was created incorrectly, then the overlap check in `FeeVersion::saving` failed or was bypassed.

---

## ARCHITECTURAL VIOLATIONS

| # | Violation | Severity | File | Line |
|---|-----------|----------|------|------|
| 1 | Denormalized `OfficialFee.amount` drifts from active `FeeVersion.amount` | CRITICAL | `FeeVersionController.php` | 52-55 |
| 2 | `listActive` returns denormalized amount instead of resolved version amount | HIGH | `FeeVersionController.php` | 107-113 |
| 3 | `CaseRuleBuilder` stores fee amount in `value` (stale at write time) | MEDIUM | `CaseRuleBuilder.tsx` | 427-433 |
| 4 | `SimpleRuleBuilder` does NOT store fee amount (inconsistent with CaseRuleBuilder) | LOW | `SimpleRuleBuilder.tsx` | 228 |
| 5 | No fee version snapshot stored in rule action (no audit trail for build-time version) | HIGH | `CaseRuleBuilder.tsx` | 427-433 |
| 6 | `EnterpriseRuleEngine.set_fee` does not record `fee_version_id` in field effect | MEDIUM | `EnterpriseRuleEngine.php` | 937-943 |
