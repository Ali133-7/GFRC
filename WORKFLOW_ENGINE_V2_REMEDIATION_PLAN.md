# WORKFLOW ENGINE V2 — REMEDIATION PLAN

## Executive Summary

This document consolidates findings from 7 forensic audits of the Workflow Engine V2 implementation. It identifies **4 CRITICAL**, **6 HIGH**, and **5 MEDIUM** severity issues requiring remediation. No hotfixes or temporary workarounds are recommended — all fixes address root architectural causes.

---

## ROOT CAUSES

### RC-1: Dual Fee Amount Authority (CRITICAL)

**Problem:** Two sources of truth for fee amounts exist: `OfficialFee.amount` (denormalized cache) and `FeeVersion.amount` (authoritative). They diverge when new versions are created.

**Affected Files:**
- `backend/app/Http/Controllers/Api/V1/FeeVersionController.php:52-55`
- `backend/app/Http/Controllers/Api/V1/FeeVersionController.php:107-113`
- `frontend/src/components/rules/CaseRuleBuilder.tsx:427-433`

**Evidence:** `FEE-EXCELLENT-NEW` expected 25,000 but resolved 500,000 because `OfficialFee.amount` was overwritten when a new version was created, while the builder displayed the denormalized amount.

### RC-2: Float Arithmetic in Financial Pipeline (CRITICAL)

**Problem:** `apply_discount`, `calculate`, and numeric condition comparisons use PHP `(float)` arithmetic instead of BC Math, violating the project's financial integrity policy.

**Affected Files:**
- `backend/app/Services/EnterpriseRuleEngine.php:576-586` (float comparison)
- `backend/app/Services/EnterpriseRuleEngine.php:959-965` (float discount)
- `backend/app/Services/EnterpriseRuleEngine.php:1257-1284` (float formula context)
- `backend/app/Services/FormulaEvaluator.php:45` (float context)

**Evidence:** `33333.333 * 7.5 / 100` produces `2499.999975` (float) vs `2500.000` (BC Math).

### RC-3: Duplicate Action Registry Entries (CRITICAL)

**Problem:** `ACTION_METADATA` in `enterprise-rule-engine.ts` contains duplicate entries for `pause_execution` (lines 308, 312) and `resume_execution` (lines 309, 313).

**Affected Files:**
- `frontend/src/types/enterprise-rule-engine.ts:308-313`

**Evidence:** React console warning: "Encountered two children with the same key, 'pause_execution'".

### RC-4: Missing fee_version_id in Execution Chain (CRITICAL)

**Problem:** `EnterpriseRuleEngine.set_fee` does not include `fee_version_id` in the field effect, breaking the audit trail for fee version resolution.

**Affected Files:**
- `backend/app/Services/EnterpriseRuleEngine.php:937-943`

**Evidence:** `calculateItems` receives `fee_version_id = null` for enterprise rule fee actions.

---

## ARCHITECTURAL VIOLATIONS

| # | Violation | Severity | Principle Violated |
|---|-----------|----------|-------------------|
| AV-1 | Denormalized `OfficialFee.amount` drifts from `FeeVersion.amount` | CRITICAL | Single source of truth |
| AV-2 | Float arithmetic in financial calculations | CRITICAL | BC Math-only financial policy |
| AV-3 | Duplicate action metadata entries | CRITICAL | Registry integrity |
| AV-4 | `fee_version_id` not propagated through effect chain | CRITICAL | Audit trail completeness |
| AV-5 | `EnterpriseRuleEngine` uses float comparison; `RuleEngineV2` uses BC Math | HIGH | Consistent comparison policy |
| AV-6 | Field effects from routing decisions use UUID keys; frontend expects canonical keys | HIGH | Key convention consistency |
| AV-7 | `buildFieldStates` + `applyFieldControlActions` duplicate action application | MEDIUM | Single responsibility |
| AV-8 | Enterprise rules always execute before workflow rules regardless of priority | MEDIUM | Unified priority ordering |
| AV-9 | `CaseRuleBuilder` stores fee amount in action `value` (stale at write time) | MEDIUM | Separation of concerns |
| AV-10 | `show`/`hide` actions missing from `ActionType` union | MEDIUM | Type completeness |

---

## AFFECTED FILES (Complete Inventory)

### Backend — CRITICAL

| File | Issues | Lines |
|------|--------|-------|
| `app/Services/EnterpriseRuleEngine.php` | RC-2, RC-4, AV-5, AV-8 | 576-586, 920, 937-943, 959-965, 1257-1284 |
| `app/Services/FormulaEvaluator.php` | RC-2 | 45 |
| `app/Http/Controllers/Api/V1/FeeVersionController.php` | RC-1 | 52-55, 107-113 |

### Backend — HIGH

| File | Issues | Lines |
|------|--------|-------|
| `app/Services/WorkflowExecutionService.php` | AV-6, AV-7 | 254-282, 296-297, 324-328 |
| `app/Services/VisibilityResolver.php` | AV-7 | 120-186 |

### Frontend — CRITICAL

| File | Issues | Lines |
|------|--------|-------|
| `src/types/enterprise-rule-engine.ts` | RC-3, AV-10 | 43-54, 308-313 |
| `src/components/rules/CaseRuleBuilder.tsx` | RC-1, AV-9 | 427-433 |

### Frontend — HIGH

| File | Issues | Lines |
|------|--------|-------|
| `src/pages/workflows/WorkflowExecutionPage.tsx` | AV-6 | 231-327 |

---

## REMEDIATION STRATEGY

### Phase 1: Financial Integrity (CRITICAL — Week 1)

**Goal:** Eliminate all float arithmetic from the financial pipeline.

#### 1.1 Replace float arithmetic in `apply_discount`

**File:** `backend/app/Services/EnterpriseRuleEngine.php:955-986`

Replace:
```php
$baseValue = (float) ($finalValues[$fieldId] ?? 0);
$discountAmount = $baseValue * ($discountVal / 100);
$finalValue = max(0, $baseValue - $discountAmount);
```

With:
```php
$baseValue = (string) ($finalValues[$fieldId] ?? '0');
$discountVal = (string) ($action['discount_value'] ?? $action['value'] ?? '0');
$discountType = $action['discount_type'] ?? 'percentage';
$scale = 3; // or from context
if ($discountType === 'percentage') {
    $discountAmount = bcmul($baseValue, bcdiv($discountVal, '100', $scale), $scale);
} else {
    $discountAmount = $discountVal;
}
$finalValue = bcsub($baseValue, $discountAmount, $scale);
if (bccomp($finalValue, '0', $scale) < 0) $finalValue = '0.' . str_repeat('0', $scale);
```

#### 1.2 Replace float comparison in conditions

**File:** `backend/app/Services/EnterpriseRuleEngine.php:576-586`

Replace all `(float)` casts with `bccomp()`:
```php
case 'greater_than':
    return bccomp((string) $actualValue, (string) $expectedValue, 3) > 0;
case 'greater_or_equal':
    return bccomp((string) $actualValue, (string) $expectedValue, 3) >= 0;
case 'less_than':
    return bccomp((string) $actualValue, (string) $expectedValue, 3) < 0;
case 'less_or_equal':
    return bccomp((string) $actualValue, (string) $expectedValue, 3) <= 0;
```

#### 1.3 Replace float context in FormulaEvaluator

**File:** `backend/app/Services/FormulaEvaluator.php:45`

Replace `(float) $value` with string-based evaluation. Since Symfony ExpressionLanguage requires numeric types, the fix is to use the `FeeEngine::calculate()` method (which uses BC Math exclusively) instead of `FormulaEvaluator` for financial formulas.

**Migration:** Change `EnterpriseRuleEngine::calculateExpression` to delegate to `FeeEngine::calculate()`:
```php
protected function calculateExpression(string $expression, array $values): string
{
    $feeEngine = app(FeeEngine::class);
    return $feeEngine->calculate($expression, $values);
}
```

#### 1.4 Add `fee_version_id` to set_fee field effect

**File:** `backend/app/Services/EnterpriseRuleEngine.php:937-943`

Add to the field effect array:
```php
'field_effect' => [
    'field_id' => $fieldId,
    'action' => 'set_fee',
    'fee_code' => $feeCode,
    'amount' => $amount,
    'fee_version_id' => $feeVersion->id,  // ← ADD THIS
    'fee_name' => $officialFee->name_ar ?? $feeCode,
],
```

---

### Phase 2: Fee Resolution Authority (CRITICAL — Week 1)

**Goal:** Eliminate denormalized amount confusion.

#### 2.1 `listActive` endpoint returns resolved version amount

**File:** `backend/app/Http/Controllers/Api/V1/FeeVersionController.php:105-113`

Replace:
```php
$fees = OfficialFee::where('is_active', true)
    ->whereNotNull('fee_code')
    ->orderBy('name_ar')
    ->get(['id', 'fee_code', 'name_ar', 'name_en', 'amount']);
```

With:
```php
$fees = OfficialFee::where('is_active', true)
    ->whereNotNull('fee_code')
    ->with(['feeVersions' => function ($q) {
        $q->activeAt()->orderByDesc('version')->limit(1);
    }])
    ->orderBy('name_ar')
    ->get(['id', 'fee_code', 'name_ar', 'name_en', 'amount']);

$fees->each(function ($fee) {
    $activeVersion = $fee->feeVersions->first();
    if ($activeVersion) {
        $fee->resolved_amount = $activeVersion->amount;
        $fee->resolved_version = $activeVersion->version;
    }
});
```

This provides BOTH the denormalized `amount` (for backward compatibility) and the resolved `resolved_amount` (for accuracy).

#### 2.2 CaseRuleBuilder uses resolved amount

**File:** `frontend/src/components/rules/CaseRuleBuilder.tsx:427-433`

Replace:
```tsx
value: selected?.amount ?? 0,
```

With:
```tsx
value: selected?.resolved_amount ?? selected?.amount ?? 0,
```

#### 2.3 Frontend OfficialFee type extension

**File:** `frontend/src/api/fees.ts`

Add to `OfficialFee` interface:
```typescript
export interface OfficialFee {
    id: string;
    fee_code: string;
    name_ar: string;
    name_en: string | null;
    amount: number;
    resolved_amount?: number;
    resolved_version?: number;
}
```

---

### Phase 3: Action Registry Fix (CRITICAL — Immediate)

**Goal:** Remove duplicate entries.

#### 3.1 Remove duplicate ACTION_METADATA entries

**File:** `frontend/src/types/enterprise-rule-engine.ts:312-313`

Delete lines 312-313:
```typescript
{ value: 'pause_execution', label: 'إيقاف مؤقت', ... },  // DELETE
{ value: 'resume_execution', label: 'استئناف', ... },    // DELETE
```

#### 3.2 Add missing actions to ActionType union

**File:** `frontend/src/types/enterprise-rule-engine.ts:43-54`

Add to the union:
```typescript
| 'show' | 'hide' | 'enable' | 'disable'
```

---

### Phase 4: Field Effect Key Normalization (HIGH — Week 2)

**Goal:** Ensure field effects from routing decisions use canonical keys.

#### 4.1 Normalize effect field_ids in handleApplyFieldEffects

**File:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx:231-327`

Add a field_id resolution step at the beginning of `handleApplyFieldEffects`:
```tsx
const resolveEffectFieldId = (effectFieldId: string): string => {
    const field = allVersionFields.find(
        (f: WorkflowField) => f.id === effectFieldId || f.register_field_id === effectFieldId
    );
    return field ? (field.register_field_id ?? `custom_${field.id}`) : effectFieldId;
};

// In the loop:
const canonicalId = resolveEffectFieldId(effect.field_id);
```

---

### Phase 5: Eliminate Duplicate Action Application (MEDIUM — Week 2)

**Goal:** Remove duplicate `applyFieldControlActions` call.

#### 5.1 Remove VisibilityResolver duplicate application

**File:** `backend/app/Services/WorkflowExecutionService.php:297`

Remove:
```php
$fieldStates = $this->visibilityResolver->applyFieldControlActions($fieldStates, $allActions);
```

`buildFieldStates` already handles all field control actions. The `VisibilityResolver` call is redundant.

**Risk:** Verify that `VisibilityResolver::applyFieldControlActions` doesn't handle any actions that `buildFieldStates` misses. Cross-reference the two switch statements.

---

### Phase 6: Unified Rule Priority (MEDIUM — Week 3)

**Goal:** Merge enterprise and workflow rules into a single priority-ordered execution.

#### 6.1 Combined rule loading

**File:** `backend/app/Services/EnterpriseRuleEngine.php:34-50`

Instead of two separate queries, normalize both rule types into a common format and sort by a unified priority:

```php
$allRules = collect();

// Enterprise rules (priority from validation_rules.priority)
$enterpriseRules->each(fn($r) => $allRules->push([
    'source' => 'enterprise',
    'rule' => $r,
    'priority' => $r->priority ?? 5000,
    'sort_order' => $r->sort_order ?? 0,
]));

// Workflow rules (priority derived from sort_order)
$workflowRules->each(fn($r) => $allRules->push([
    'source' => 'workflow',
    'rule' => $r,
    'priority' => 5000 - $r->sort_order, // lower sort = higher priority
    'sort_order' => $r->sort_order ?? 0,
]));

$sorted = $allRules->sortByDesc('priority')->sortBy('sort_order');
```

**Risk:** This changes execution order for existing rules. Requires regression testing with all existing rule configurations.

---

## MIGRATION RISKS

| Risk | Phase | Probability | Impact | Mitigation |
|------|-------|-------------|--------|------------|
| BC Math change alters discount results | 1 | HIGH | MEDIUM | Run parallel comparison: old float vs new BC Math for all existing receipts |
| Fee resolution change alters builder display | 2 | MEDIUM | LOW | Add `resolved_amount` alongside `amount`; don't remove `amount` |
| Removing duplicate actions breaks existing rules | 3 | LOW | LOW | Duplicate entries produce identical actions; removal is safe |
| Field key normalization breaks routing effects | 4 | MEDIUM | MEDIUM | Add comprehensive logging for field_id resolution |
| Removing duplicate action application changes field states | 5 | LOW | LOW | Idempotent operations; verify with test suite |
| Unified priority changes rule execution order | 6 | HIGH | HIGH | Requires full regression test with production rule configurations |

---

## IMPLEMENTATION PHASES

### Phase 1 — Immediate (Days 1-3)
- [ ] Fix ACTION_METADATA duplicates (RC-3)
- [ ] Add `fee_version_id` to set_fee effect (RC-4)
- [ ] Replace float arithmetic in `apply_discount` (RC-2)
- [ ] Replace float comparison in conditions (RC-2)

### Phase 2 — Week 1 (Days 4-7)
- [ ] Replace `FormulaEvaluator` with `FeeEngine::calculate` for financial formulas (RC-2)
- [ ] Add `resolved_amount` to `listActive` endpoint (RC-1)
- [ ] Update `CaseRuleBuilder` to use `resolved_amount` (RC-1)
- [ ] Extend frontend `OfficialFee` type (RC-1)

### Phase 3 — Week 2 (Days 8-14)
- [ ] Normalize field effect keys in routing handler (AV-6)
- [ ] Remove duplicate `applyFieldControlActions` call (AV-7)
- [ ] Add `show`/`hide` to `ActionType` union (AV-10)
- [ ] Write integration tests for all financial actions

### Phase 4 — Week 3 (Days 15-21)
- [ ] Design unified priority rule execution (AV-8)
- [ ] Implement combined rule loading
- [ ] Regression test with production rule configurations
- [ ] Deploy with feature flag for unified priority

### Phase 5 — Verification (Days 22-28)
- [ ] Run full determinism test: 10 identical executions
- [ ] Run fee resolution test: verify all fee codes resolve correctly
- [ ] Run round-trip test: create → save → reload → save → reload for all rule types
- [ ] Run field effect test: verify all 10 action types produce correct UI state
- [ ] Performance benchmark: compare execution time before/after

---

## TESTING REQUIREMENTS

### Unit Tests Needed

| Test | Covers | Phase |
|------|--------|-------|
| `FeeEngine::calculate` with large amounts | RC-2 | 1 |
| `EnterpriseRuleEngine::apply_discount` with BC Math | RC-2 | 1 |
| `EnterpriseRuleEngine::evaluateSimpleCondition` numeric comparison | RC-2 | 1 |
| `set_fee` includes `fee_version_id` in effect | RC-4 | 1 |
| `ACTION_METADATA` has no duplicates | RC-3 | 1 |
| `listActive` returns `resolved_amount` | RC-1 | 2 |
| Field effect key normalization | AV-6 | 3 |
| Rule round-trip for all 3 rule types | AV-9 | 3 |

### Integration Tests Needed

| Test | Covers | Phase |
|------|--------|-------|
| Full execution: fee selection → resolution → total | RC-1, RC-2 | 2 |
| Determinism: 10 identical executions | All | 5 |
| Field effect chain: rule → engine → transform → frontend | AV-6, AV-7 | 3 |
| Routing decision field effects with UUID keys | AV-6 | 3 |

---

## NON-GOALS

This plan explicitly does NOT include:
- Database schema changes (no new tables, no column renames)
- API versioning (all changes are backward-compatible additions)
- Frontend framework migration
- Event store schema changes
- Migration of existing rule data
