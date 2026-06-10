# FINANCIAL ACTION AUDIT

## Action Types Audited

| Action | Created | Stored | Loaded | Executed | Result Returned | Frontend | Total Recalc |
|--------|---------|--------|--------|----------|----------------|----------|--------------|
| set_fee | PASS | PASS | PASS | **FAIL-1** | PASS | PASS | PASS |
| apply_discount | PASS | PASS | PASS | **FAIL-2** | PASS | PASS | **FAIL-3** |
| calculate | PASS | PASS | PASS | **FAIL-4** | PASS | PASS | PASS |
| override_value | PASS | PASS | PASS | PASS | PASS | PASS | N/A |
| set_value | PASS | PASS | PASS | PASS | PASS | PASS | N/A |

---

## FAIL-1: set_fee — Fee Version Not Recorded in Field Effect

**File:** `backend/app/Services/EnterpriseRuleEngine.php:937-943`

The `set_fee` handler resolves the fee version but does NOT include `fee_version_id` in the field effect:

```php
$fieldEffects[] = [
    'field_id' => $fieldId,
    'action' => 'set_fee',
    'fee_code' => $feeCode,
    'amount' => $amount,
    'fee_name' => $officialFee->name_ar ?? $feeCode,
    // MISSING: 'fee_version_id' => $feeVersion->id,
];
```

Compare with `RuleEngineV2::resolveAction` (line 376) which DOES record `fee_version_id`:
```php
$resolved['fee_version_id'] = $feeVersion?->id;
```

**Impact:** The transformation in `WorkflowExecutionService` cannot propagate `fee_version_id` to the action, so `calculateItems` receives `fee_version_id = null` for enterprise rule fee actions. This breaks the fee version audit trail.

**Fix:** Add `'fee_version_id' => $feeVersion->id` to the field effect array.

---

## FAIL-2: apply_discount — Float Arithmetic Violates BC Math Policy

**File:** `backend/app/Services/EnterpriseRuleEngine.php:955-986`

```php
$baseValue = (float) ($finalValues[$fieldId] ?? 0);      // ← FLOAT
$discountVal = (float) $discountValue;                     // ← FLOAT
$discountAmount = $discountType === 'percentage'
    ? $baseValue * ($discountVal / 100)                    // ← FLOAT MULTIPLICATION
    : $discountVal;
$finalValue = max(0, $baseValue - $discountAmount);        // ← FLOAT SUBTRACTION
$formattedValue = number_format($finalValue, 3, '.', '');  // ← Formatted after float drift
```

**Expected:** All monetary calculations MUST use BC Math (`bcmul`, `bcsub`, `bcadd`).
**Actual:** Uses PHP float arithmetic, introducing IEEE 754 drift.

**Example:** `25000 * (15 / 100) = 3750.0` (correct in this case)
**But:** `33333.333 * (7.5 / 100) = 2499.999975` (float) vs `2500.000` (BC Math)

**Fix:** Replace with:
```php
$baseValue = (string) ($finalValues[$fieldId] ?? '0');
$discountVal = (string) $discountValue;
if ($discountType === 'percentage') {
    $discountAmount = bcmul($baseValue, bcdiv($discountVal, '100', $ctx->scale()), $ctx->scale());
} else {
    $discountAmount = $discountVal;
}
$finalValue = bcsub($baseValue, $discountAmount, $ctx->scale());
if (bccomp($finalValue, '0', $ctx->scale()) < 0) $finalValue = '0.000';
```

---

## FAIL-3: apply_discount — Total Not Recalculated with BC Math

**File:** `backend/app/Services/WorkflowExecutionService.php:324-328`

The discount trace uses the float-computed `discount_amount` from the engine:
```php
foreach ($financialTrace as $t) {
    if ($t['step'] === 'discount') {
        $discountApplied = bcadd($discountApplied, (string) ($t['discount_amount'] ?? '0'), $this->ctx->scale());
    }
}
```

The `discount_amount` in the trace was computed with float arithmetic (FAIL-2), so even though `bcadd` is used here, the INPUT is already tainted.

---

## FAIL-4: calculate — FormulaEvaluator Uses Float Context

**File:** `backend/app/Services/EnterpriseRuleEngine.php:1257-1284`

```php
protected function calculateExpression(string $expression, array $values): string
{
    $formulaEvaluator = app(FormulaEvaluator::class);
    $formula = preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
        $val = $values[$matches[1]] ?? 0;
        return is_numeric($val) ? (string) (float) $val : '0';  // ← FLOAT CAST
    }, $expression);

    $context = [];
    foreach ($values as $key => $value) {
        $context[$key] = is_numeric($value) ? (float) $value : 0;  // ← FLOAT CONTEXT
    }

    return (string) $formulaEvaluator->evaluate($formula, $context);
}
```

**File:** `backend/app/Services/FormulaEvaluator.php:45`

```php
$safeContext[$key] = is_numeric($value) ? (float) $value : $value;  // ← FLOAT
```

The Symfony ExpressionLanguage evaluator operates on floats internally. This is an architectural violation — the fee engine uses BC Math exclusively, but the formula evaluator uses floats.

**Impact:** For large amounts (e.g., 500,000 * 0.15), float precision loss can produce results that differ from BC Math by 0.001 or more.

---

## Action: set_value

| Stage | File | Line | Status |
|-------|------|------|--------|
| Created (builder) | `SimpleRuleBuilder.tsx` | 89-90 | PASS — `{action:'set_value', target_field_id, value}` |
| Stored (DB) | `WorkflowRule.actions` | — | PASS — JSON column, cast to array |
| Loaded (engine) | `EnterpriseRuleEngine.php` | 689-710 | PASS — `convertWorkflowActions` preserves all keys |
| Executed | `EnterpriseRuleEngine.php` | 734-740 | PASS — `$finalValues[$fieldId] = $value` |
| Effect generated | `EnterpriseRuleEngine.php` | 738 | PASS — `{field_id, action:'set_value', value}` |
| Transformed | `WorkflowExecutionService.php` | 276-277 | PASS — `resolved_value = effect.value` |
| Applied to snapshot | `WorkflowExecutionService.php` | 1003-1004 | PASS — `$modified[$targetId] = resolved_value` |
| Frontend display | `WorkflowExecutionPage.tsx` | 160-161 | PASS — `modified_values` merged into state |

---

## Action: override_value

| Stage | File | Line | Status |
|-------|------|------|--------|
| Created (builder) | `CaseRuleBuilder.tsx` | 34 | PASS — Listed in ALL_ACTIONS |
| Executed | `EnterpriseRuleEngine.php` | 734-740 | PASS — Same handler as set_value |
| Applied to snapshot | `WorkflowExecutionService.php` | 1011-1012 | PASS |

---

## Summary of Financial Integrity Violations

| # | Violation | Severity | Affected Action | File |
|---|-----------|----------|-----------------|------|
| 1 | `fee_version_id` not in field effect | HIGH | set_fee | `EnterpriseRuleEngine.php:937` |
| 2 | Float arithmetic in discount | CRITICAL | apply_discount | `EnterpriseRuleEngine.php:959-965` |
| 3 | Float-tainted discount in BC trace | HIGH | apply_discount | `WorkflowExecutionService.php:324` |
| 4 | Float context in formula evaluation | HIGH | calculate | `EnterpriseRuleEngine.php:1264` + `FormulaEvaluator.php:45` |
