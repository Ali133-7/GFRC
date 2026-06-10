# Validation Engine Audit

**Date:** 2026-06-07
**Scope:** `ValidationEngine` current implementation, result formats, gaps, and Phase 8 prerequisites
**Status:** Complete — awaiting architectural decision before rebuild

---

## 1. Currently Supported Validation Types

Source: `backend/app/Services/ValidationEngine.php:60` (`runValidation()` match expression)

| Type | Handler | Line | Description |
|------|---------|------|-------------|
| `duplicate_check` | `checkDuplicate()` | 108 | Queries `records` table for non-soft-deleted rows in `target_register_id` where `data->>register_field_name` matches submitted value(s). Returns `true` (**failed**) if `count > 0`. |
| `exists` | `checkExists()` | 143 | Same query pattern as `duplicate_check`, but returns `true` (**failed**) if record is **NOT** found (value must exist). |
| `multi_field` | `checkMultiField()` | 178 | **Alias** — delegates directly to `checkDuplicate()`. Identical logic, semantically intended for multiple fields. |
| `register_search` | `checkRegisterSearch()` | 186 | **Alias** — delegates directly to `checkDuplicate()`. |
| `query_builder` | `checkQueryBuilder()` | 194 | Executes dynamically built query conditions (with `and`/`or` nesting, operators `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `in`) against `records` table. Returns `true` if any match found. |
| `sql` | `checkSql()` | 268 | Runs raw SQL query with `{{field_id}}` placeholder replacement, parses condition string like `"count = 0"`, returns `true` if condition is **NOT** met. |
| `field_existence_check` | `checkFieldExistence()` | 327 | **Routing-oriented check.** Evaluates `trigger_conditions` against submitted values, then looks up record in `target_register_id`. Returns rich routing decision (`found`/`not_found`/`skipped`/`error`) instead of simple boolean. |

**Any other `validation_type` falls through to `default => false`, causing `runValidation()` to return `passed`.**

---

## 2. Validation Types Defined but NOT Implemented

### Backend definitions

There is **no enum or constant list** in PHP that contains types beyond the seven implemented above.

- **Database layer:** `backend/database/migrations/2026_06_03_000001_create_validation_rules_table.php:20`  
  `validation_type` is a plain `string(validation_type, 50)` — not an enum. The migration comment lists the original six types and omits `field_existence_check` (added later).

- **API validation:** `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php:711,753`  
  Restricts `validation_type` to exactly the seven implemented types:
  ```
  duplicate_check, exists, multi_field, register_search, query_builder, sql, field_existence_check
  ```

- **TypeScript types:** `frontend/src/types/workflow.ts:288` defines `validation_type: string` (no enum constraint).

### `cross_register_check`

| Location | Finding |
|----------|---------|
| `ValidationEngine.php` | Not present in the `match` statement. |
| Database migrations | Zero references. |
| `WorkflowVersionController.php` | Not in `in:` validation lists. |
| `ValidationRule.php` | No constant or method. |
| Entire backend codebase (`grep -ri cross_register_check`) | **Zero matches.** |
| `FINAL_IMPLEMENTATION_REPORT.md:117` | Only mention: *"core engine needs `duplicate_check` + `cross_register_check` hardening"* |

**Conclusion:** `cross_register_check` is a documented future requirement with **zero code or schema definition.** It is the only type that exists on paper but not in code.

---

## 3. How `duplicate_check` Results Are Processed End-to-End

### A. `checkDuplicate()` → boolean
`backend/app/Services/ValidationEngine.php:108`

```php
$query = DB::table('records')
    ->where('register_id', $rule->target_register_id)
    ->whereNull('deleted_at');

foreach ($conditions as $cond) {
    $query->whereRaw("data->>? = ?", [$cond[0], $cond[2]]);
}

return $query->count() > 0;  // true = duplicate found = validation FAILED
```

### B. `runValidation()` → result array
`backend/app/Services/ValidationEngine.php:57`

**On fail (`checkDuplicate()` returns `true`):**
```php
[
    'rule_id'         => $rule->id,
    'rule_name'       => $rule->name,
    'validation_type' => $rule->validation_type,
    'status'          => 'failed',
    'response_type'   => $rule->response_type,   // 'error' | 'warning' | 'confirm'
    'message'         => $rule->getErrorMessage(),
    'confirm_message' => $rule->isConfirm() ? $rule->getConfirmMessage() : null,
]
```

**On pass:**
```php
[
    'rule_id'         => $rule->id,
    'rule_name'       => $rule->name,
    'validation_type' => $rule->validation_type,
    'status'          => 'passed',
]
```

### C. `validate()` → aggregated result
`backend/app/Services/ValidationEngine.php:18`

```php
[
    'passed'             => !$hasError,
    'has_warning'        => $hasWarning,
    'needs_confirmation' => $needsConfirmation,
    'results'            => $results,
]
```

- `$hasError` = any result with `status === 'failed'` and `response_type === 'error'`
- `$hasWarning` = any result with `status === 'failed'` and `response_type === 'warning'`
- `$needsConfirmation` = any result with `status === 'failed'` and `response_type === 'confirm'`

### D. Controller → HTTP response
`backend/app/Http/Controllers/Api/V1/WorkflowExecutionController.php:74`

```php
// 1. Run legacy engine BEFORE submitStep()
$legacyResult = $this->validationEngine->validate(
    $execution->version->id,
    $data['values'],
    ['execution_id' => $execution->id, 'step_index' => $data['step_index']]
);

// 2. Filter blocking errors
$legacyErrors = array_filter($legacyResult['results'], function ($r) {
    return $r['status'] === 'failed' && ($r['response_type'] ?? '') === 'error';
});

// 3. BLOCK if errors exist — submitStep() is NEVER called
if (!empty($legacyErrors)) {
    return $this->success([
        'validation_blocked' => true,
        'errors' => $allErrors,
        'execution' => $execution,
    ], 'تم منع الحفظ بسبب أخطاء التحقق', [], 422);
}
```

**Does `duplicate_check` block submission?**  
**Yes**, if `response_type = 'error'`. The controller returns HTTP **422** with:
```json
{
  "validation_blocked": true,
  "errors": [
    { "rule_id": "...", "message": "..." }
  ],
  "execution": { ... }
}
```

**Does `duplicate_check` with `response_type = 'warning'` block?**  
**No.** Warnings flow through. After `submitStep()` succeeds, the controller includes them in:
```php
'validation_warnings' => array_values(array_filter($legacyResult['results'], fn($r) => ...))
```

---

## 4. Result Format Reference

### `runValidation()` — non-field_existence_check

| Status | Keys |
|--------|------|
| `passed` | `rule_id`, `rule_name`, `validation_type`, `status` |
| `failed` | + `response_type`, `message`, `confirm_message` |
| `error` (exception) | `rule_id`, `rule_name`, `validation_type`, `status='error'`, `message` |

### `runValidation()` — `field_existence_check`

Returns a routing decision array directly from `checkFieldExistence()`, not the standard format:

| Status | Keys |
|--------|------|
| `found` | `rule_id`, `rule_name`, `validation_type`, `status`, `decision`, `existing_record`, `message`, `actions`, `target_workflow_id`, `target_step_id`, `field_effects`, `existing_record_data` |
| `not_found` | `rule_id`, `rule_name`, `validation_type`, `status`, `decision='continue_workflow'`, `message`, `field_effects` |
| `skipped` | `rule_id`, `rule_name`, `validation_type`, `status`, `reason` |
| `error` | `rule_id`, `rule_name`, `validation_type`, `status`, `message` |

### `validate()` — aggregated

```php
[
    'passed'             => bool,
    'has_warning'        => bool,
    'needs_confirmation' => bool,
    'results'            => array,  // array of runValidation() results
]
```

---

## 5. Other Validation-Related Services

### A. `ConditionalValidationEngine`
`backend/app/Services/ConditionalValidationEngine.php`

Runs **before** `ValidationEngine` inside `WorkflowExecutionService::submitStep()` (line 133). Handles field-level Laravel-style rules:
- `required`, `min`, `max`, `numeric`, `email`, `date`, `in`, `regex`, `confirmed`
- Cross-field: `gte_field`, `lte_field`, `equals_field`, `different_field`

### B. `CrossFieldValidationEngine`
`backend/app/Services/CrossFieldValidationEngine.php`

Runs **before** `ValidationEngine` inside `WorkflowExecutionService::submitStep()` (line 134). Handles:
- `gte`, `gt`, `lte`, `lt`, `equals`, `not_equals`, `before`, `after`, `requires`, `excludes`

### C. `EnterpriseRuleEngine::execute_validation` action
`backend/app/Services/EnterpriseRuleEngine.php`

Dynamic validation triggered by workflow rules. Results are checked in `WorkflowExecutionService::submitStep()` (lines 183–198). Failed `error`-type results throw `ValidationBlockedException` (422 with `blocks` array).

### D. `ValidationEngine::simulate()`
`backend/app/Services/ValidationEngine.php:508`

UI-preview method. Same logic as `validate()` but enriches each result with rule metadata and returns counts (`passed_count`, `failed_count`).

---

## 6. Phase 8 Gap Analysis

| Requirement | Current State | Gap |
|-------------|---------------|-----|
| `duplicate_check` | ✅ Implemented | Works but uses legacy controller blocking (not `submitStep()`). No issues. |
| `cross_register_check` | ❌ **Not implemented** | Needs new handler method + DB schema (if any). |
| `dynamic_search` | ❌ **Not implemented** | Needs new validation type + debounced API endpoint. |
| `exists` | ✅ Implemented | Alias-ish of `duplicate_check` with inverted logic. |
| `not_exists` | ❌ **Not implemented** | `exists` returns `true` when record NOT found. A true `not_exists` type does not exist. |
| `field_states mutation` | ⚠️ **Risk** | `field_existence_check` mutates routing decisions and field effects. Must ensure Phase 8 rules do not touch field_states. |

### Phase 8 Architectural Constraints

Per project mandate:
> **ValidationEngine produces `validation_results` only. The decision to block is in `submitStep()`.**

Current state **violates** this for `field_existence_check`:
- `checkFieldExistence()` returns routing decisions (`target_workflow_id`, `target_step_id`, `field_effects`) directly.
- `WorkflowVersionController::checkFieldExistence()` (line 889) consumes these routing decisions.
- This conflates **validation** with **routing**.

**Recommendation:** New validation types (`cross_register_check`, `dynamic_search`, `not_exists`) must return standard `passed`/`failed`/`error` results only. Routing decisions must be separated into a dedicated routing action or service.

---

## 7. Files Requiring Changes for Phase 8

| File | Change Needed |
|------|---------------|
| `backend/app/Services/ValidationEngine.php` | Add `cross_register_check`, `dynamic_search`, `not_exists` handlers |
| `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php` | Add `cross_register_check` and `dynamic_search` to `in:` validation lists |
| `backend/app/Services/WorkflowExecutionService.php` | Wire new validation results into `ValidationBlockedException` logic |
| `backend/app/Http/Controllers/Api/V1/WorkflowExecutionController.php` | Ensure new types block correctly (should work automatically via legacy engine) |
| `backend/routes/api.php` | Add debounced `dynamic_search` endpoint (if required) |
| `frontend/src/types/workflow.ts` | Add new validation types to TypeScript union |
