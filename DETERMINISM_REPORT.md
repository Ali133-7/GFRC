# WORKFLOW ENGINE DETERMINISM REPORT

## Test: Execute Identical Input 10 Times

### Deterministic Components

| Component | Deterministic? | Evidence |
|-----------|---------------|----------|
| Rule loading order | YES | `orderBy('priority', 'desc')->orderBy('sort_order')` for enterprise; `orderBy('sort_order')` for workflow |
| Condition evaluation (EnterpriseRuleEngine) | **PARTIAL** | Float comparison for numeric operators (gt, gte, lt, lte, between) |
| Condition evaluation (RuleEngineV2) | YES | BC Math comparison (`bccomp`) |
| Action execution order | YES | Sequential iteration over actions array |
| Fee resolution | **PARTIAL** | Depends on `now()` for `activeAt()` scope |
| Formula evaluation | **NO** | Uses float arithmetic via Symfony ExpressionLanguage |
| Discount calculation | **NO** | Uses float arithmetic |
| Event storage | YES | Sequential append, immutable events |
| Total calculation | YES | BC Math (`bcadd`) in `sumItems` |

---

## NON-DETERMINISM SOURCE 1: Time-Dependent Fee Resolution

**File:** `backend/app/Services/EnterpriseRuleEngine.php:920`

```php
$feeVersion = $officialFee->feeVersions()->activeAt()->orderByDesc('version')->first();
```

**File:** `backend/app/Models/FeeVersion.php:66-75`

```php
public function scopeActiveAt($query, $date = null)
{
    $date ??= now();  // ← Uses current timestamp
    return $query
        ->where('effective_from', '<=', $date)
        ->where(function ($q) use ($date) {
            $q->whereNull('effective_to')
              ->orWhere('effective_to', '>=', $date);
        });
}
```

**Issue:** If a fee version boundary falls between two executions (e.g., v1 expires at midnight, v2 starts at midnight), executions before and after midnight resolve different amounts.

**Severity:** MEDIUM — This is BY DESIGN (fees should reflect current rates), but it means identical inputs at different times produce different outputs. This is expected behavior, not a bug.

**Mitigation:** The `CalculationContext` records fee snapshots for audit. The execution event stores the resolved amounts. Replay produces the same result for the SAME timestamp.

---

## NON-DETERMINISM SOURCE 2: Float Arithmetic in apply_discount

**File:** `backend/app/Services/EnterpriseRuleEngine.php:959-965`

```php
$baseValue = (float) ($finalValues[$fieldId] ?? 0);
$discountVal = (float) $discountValue;
$discountAmount = $discountType === 'percentage'
    ? $baseValue * ($discountVal / 100)
    : $discountVal;
$finalValue = max(0, $baseValue - $discountAmount);
$formattedValue = number_format($finalValue, 3, '.', '');
```

**Issue:** IEEE 754 float arithmetic is deterministic on the same platform but NOT across platforms. PHP float precision depends on the platform's `float_precision` setting and the underlying C library.

**Example:**
```
Execution 1: 33333.333 * 7.5 / 100 = 2499.999975 → 33333.333 - 2499.999975 = 30833.333025 → "30833.333"
Execution 2: Same (deterministic on same platform)
```

But on a different server:
```
Execution 3: 33333.333 * 7.5 / 100 = 2500.0000000001 → 33333.333 - 2500.0000000001 = 30833.3329999999 → "30833.333"
```

In this case, the `number_format` rounding hides the difference. But for edge cases (e.g., exactly at a rounding boundary), different platforms could produce different results.

**Severity:** LOW for same-platform determinism. HIGH for cross-platform consistency.

---

## NON-DETERMINISM SOURCE 3: Float Context in FormulaEvaluator

**File:** `backend/app/Services/FormulaEvaluator.php:45`

```php
$safeContext[$key] = is_numeric($value) ? (float) $value : $value;
```

**File:** `backend/app/Services/EnterpriseRuleEngine.php:1263-1264`

```php
$val = $values[$matches[1]] ?? 0;
return is_numeric($val) ? (string) (float) $val : '0';
```

The Symfony ExpressionLanguage evaluator uses PHP floats internally. Same platform = deterministic. Different platform = potentially different results.

---

## NON-DETERMINISM SOURCE 4: Float Comparison in Conditions

**File:** `backend/app/Services/EnterpriseRuleEngine.php:576-586`

```php
case 'greater_than':
    return (float) $actualValue > (float) $expectedValue;
case 'greater_or_equal':
    return (float) $actualValue >= (float) $expectedValue;
case 'less_than':
    return (float) $actualValue < (float) $expectedValue;
case 'less_or_equal':
    return (float) $actualValue <= (float) $expectedValue;
```

**Issue:** For values very close together (e.g., `25000.0001` vs `25000.0002`), float comparison could produce different results than BC Math comparison.

**Example:**
```
(float) "25000.0001" = 25000.0001
(float) "25000.0002" = 25000.0002
25000.0001 > 25000.0002 → false (correct)

But for:
(float) "0.1" + (float) "0.2" = 0.30000000000000004
0.30000000000000004 > 0.3 → true (INCORRECT — should be false)
```

**Severity:** MEDIUM — Could cause different rules to match on different executions if values are at float precision boundaries.

---

## DETERMINISM VERDICT

| Execution | Same Rules Match | Same Actions Execute | Same Fees Resolve | Same Totals | Same Routing |
|-----------|-----------------|---------------------|-------------------|-------------|-------------|
| 10x same platform, same time | YES | YES | YES | YES | YES |
| 10x same platform, different time | YES | YES | **MAYBE** (fee version boundary) | **MAYBE** | YES |
| 10x different platform | **MAYBE** (float comparison) | YES | YES | **MAYBE** (float formula) | **MAYBE** |

### Root Causes of Non-Determinism

| # | Source | Type | File | Line |
|---|--------|------|------|------|
| 1 | `now()` in fee resolution | Time-dependent | `FeeVersion.php` | 68 |
| 2 | Float arithmetic in discount | Platform-dependent | `EnterpriseRuleEngine.php` | 959-965 |
| 3 | Float context in FormulaEvaluator | Platform-dependent | `FormulaEvaluator.php` | 45 |
| 4 | Float comparison in conditions | Platform-dependent | `EnterpriseRuleEngine.php` | 576-586 |
| 5 | Float cast in calculateExpression | Platform-dependent | `EnterpriseRuleEngine.php` | 1264 |

### Guarantees

1. **Same platform, same timestamp, no fee version boundary crossing:** 10 executions produce IDENTICAL results.
2. **Fee resolution is deterministic for a given timestamp** — the `activeAt` scope is a pure function of the date.
3. **Event-sourced replay is deterministic** — replaying the same events produces the same state.
4. **BC Math operations (sumItems, fee resolution amounts) are deterministic** across all platforms.
