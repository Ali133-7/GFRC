# Validation Overlap Audit

**Date:** 2026-06-07
**Scope:** ValidationRule dual-path execution (legacy ValidationEngine vs. execute_validation action)
**Status:** Active — awaiting architectural decision

---

## 1. How the legacy ValidationEngine selects rules

**Entry point:** `WorkflowExecutionController::submitStep()`  
**Filter:** `ValidationEngine::validate()` (`backend/app/Services/ValidationEngine.php:20-24`)

```php
$rules = ValidationRule::where('workflow_version_id', $workflowVersionId)
    ->where('is_active', true)
    ->whereNull('rule_config')
    ->orderBy('sort_order')
    ->get();
```

The legacy engine runs **before** `WorkflowExecutionService::submitStep()`. If any legacy rule fails with `response_type = 'error'`, the controller returns 422 and blocks submission entirely.

**Key discriminator:** `rule_config IS NULL`

---

## 2. Is there a field that distinguishes legacy vs. rule-triggered?

**No explicit field exists.**

The `ValidationRule` model (`backend/app/Models/ValidationRule.php`) defines these fillable fields:

| Field | Purpose | Relevant to dual-path? |
|-------|---------|------------------------|
| `rule_config` | Enterprise JSON config (conditions + actions) | **Implicit discriminator** |
| `validation_type` | `duplicate_check`, `sql`, `query_builder`, etc. | No — used by both paths |
| `response_type` | `error` / `warning` / `confirm` | No — semantic only |
| `is_active` | Boolean enable flag | No — both paths check this |
| `sort_order` | Execution priority | No |
| `priority` | Enterprise priority | No |

The migration `2026_06_03_000008_add_enterprise_rule_config_to_validation_rules.php` added only:
- `rule_config` (jsonb, nullable)
- `priority` (integer)
- `category` (string, default 'validation')

There is **no** `trigger_mode`, `execution_mode`, `is_dynamic`, or similar enum/flag.

### The implicit discriminator today

| `rule_config` value | Legacy engine? | Enterprise engine? |
|---------------------|----------------|--------------------|
| `NULL` | ✅ Included (`whereNull`) | ❌ Excluded (`whereNotNull`) |
| `NOT NULL` (enterprise config) | ❌ Excluded | ✅ Included |
| `[]` (empty object) | ❌ Excluded | ✅ Included (empty conditions = no match) |

---

## 3. Can the same rule run in BOTH paths?

**Yes — absolutely.**

### Path A: Legacy Engine (guaranteed run)
```php
// WorkflowExecutionController::submitStep()
$legacyResult = $this->validationEngine->validate(
    $execution->version->id,
    $data['values'],
    ['execution_id' => $execution->id, 'step_index' => $data['step_index']]
);
```
Any `ValidationRule` with `rule_config IS NULL` and `is_active = true` is evaluated here.

### Path B: execute_validation action (conditional run)
```php
// EnterpriseRuleEngine::executeActions()
case 'execute_validation':
    $validationRuleId = $action['validation_rule_id'] ?? null;
    // ... looks up rule in $context['validation_rules'] by ID only
```

`WorkflowExecutionService::submitStep()` loads **all** active rules into context:

```php
$validationRules = \App\Models\ValidationRule::where('workflow_version_id', $version->id)
    ->where('is_active', true)
    ->get();  // <-- NO rule_config filter
```

**Critical gaps:**
1. `execute_validation` matches only by `id` — no `rule_config` check.
2. `WorkflowExecutionService` passes **all** active rules, including legacy ones.
3. There is **zero guard** preventing a legacy rule from being targeted by `execute_validation`.

### Concrete double-execution scenario

1. Admin creates `ValidationRule` with:
   - `validation_type = 'duplicate_check'`
   - `rule_config = null` (legacy)
   - `response_type = 'error'`
   - `is_active = true`

2. **Legacy path** runs first → checks duplicate → fails → controller blocks with 422.

3. If controller somehow allowed it through (e.g., rule temporarily passes), `submitStep()` continues.

4. **Enterprise path** → a `WorkflowRule` with `execute_validation` action targeting the same `ValidationRule` → runs the exact same `duplicate_check` again.

5. If data changed between (2) and (4) (e.g., concurrent insert), the two runs may return **different results**.

### Race condition severity

| Scenario | Legacy result | Enterprise result | User experience |
|----------|---------------|-------------------|-----------------|
| Duplicate inserted between paths | Passed | Failed | Confusing: legacy allowed, enterprise blocked |
| Rule is `warning` in legacy, `error` via action | Warning (non-blocking) | Error (blocking) | Inconsistent severity |
| Same rule run twice | First check | Second check | Wasted compute + inconsistent UX |

---

## 4. Root cause

The system conflates **two orthogonal concepts** into a single nullable column:

| Concept | Current representation | Problem |
|---------|------------------------|---------|
| "Is this an enterprise rule?" | `rule_config IS NOT NULL` | Overloaded — also acts as "is this legacy?" |
| "Can this rule be triggered by `execute_validation`?" | **No representation** | Falls through to implicit behaviour |

`rule_config` was designed to distinguish enterprise rules from legacy rules. It was **never** intended to act as a gate for `execute_validation` targeting.

---

## 5. Options

### Option A: Add `trigger_mode` enum to `ValidationRule`

```php
// Migration
$table->enum('trigger_mode', ['legacy', 'rule_action', 'both'])
      ->default('legacy')
      ->after('is_active');
```

| `trigger_mode` | Legacy engine | execute_validation action |
|----------------|---------------|---------------------------|
| `legacy` | ✅ | ❌ |
| `rule_action` | ❌ | ✅ |
| `both` | ✅ | ✅ |

**Pros:**
- Explicit and self-documenting
- Backward compatible (`legacy` default preserves existing behaviour)
- Admin UI can show a dropdown

**Cons:**
- Requires migration
- All existing rules need migration to `legacy`
- Slightly more complex admin form

### Option B: Database constraint (mutual exclusivity)

Add a CHECK constraint or application-level validation:

```sql
-- A rule with rule_config IS NULL cannot be targeted by execute_validation
-- Enforced by filtering $context['validation_rules'] to exclude rule_config IS NULL
```

In `WorkflowExecutionService::submitStep()`:
```php
$validationRules = ValidationRule::where('workflow_version_id', $version->id)
    ->where('is_active', true)
    ->whereNotNull('rule_config')  // <-- only enterprise rules
    ->get();
```

**Pros:**
- Zero schema changes
- Immediate fix

**Cons:**
- Prevents legitimate use cases where an admin wants a legacy-style rule to also be triggerable on-demand
- Hides intent — "why can't I target this rule?"

### Option C: Hybrid (recommended)

1. **Immediate fix (no migration):** Filter `$context['validation_rules']` to exclude `rule_config IS NULL` rules. This prevents accidental double-execution today.

2. **Phase 2 (next sprint):** Add `trigger_mode` enum for explicit control. Default existing rules to `legacy`. Allow admins to opt into `rule_action` or `both`.

---

## 6. Recommendation

**Adopt Option C.**

- **Today:** Change `WorkflowExecutionService::submitStep()` to only load enterprise-capable validation rules into context:
  ```php
  $validationRules = ValidationRule::where('workflow_version_id', $version->id)
      ->where('is_active', true)
      ->whereNotNull('rule_config')
      ->get();
  ```

- **Next sprint:** Add `trigger_mode` column + migration + admin UI support. This gives full flexibility without surprise double-execution.

---

## 7. Files affected by any change

| File | Role |
|------|------|
| `backend/app/Services/ValidationEngine.php:20-24` | Legacy rule filter (`whereNull('rule_config')`) |
| `backend/app/Services/WorkflowExecutionService.php:148-151` | Context loading (needs filter) |
| `backend/app/Services/EnterpriseRuleEngine.php:1094-1125` | `execute_validation` action |
| `backend/app/Models/ValidationRule.php` | Model (needs `trigger_mode` if Option A/C) |
| `backend/database/migrations/...` | Migration (if Option A/C) |
| `backend/app/Http/Controllers/Api/V1/ValidationRuleController.php` | CRUD (needs `trigger_mode` validation) |
| `frontend/src/types/...` | TypeScript types (if `trigger_mode` added) |
