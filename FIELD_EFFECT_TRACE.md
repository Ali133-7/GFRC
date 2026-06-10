# FIELD EFFECT APPLICATION AUDIT

## Field Effect Actions Audited

| Action | Generated | Returned | Created | Merged | State Updated | React Updated | UI Updated |
|--------|-----------|----------|---------|--------|---------------|---------------|------------|
| show | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| hide | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| set_visibility | PASS | PASS | PASS | PASS | PASS | **FAIL-1** | **FAIL-1** |
| set_required | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| set_readonly | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| set_editable | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| set_lock | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| unlock | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| set_value | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| override_value | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

---

## Field Effect Chain

### Stage 1: Generation (EnterpriseRuleEngine)

**File:** `backend/app/Services/EnterpriseRuleEngine.php:718-1252`

Every action in `executeActions` generates a field effect:
```php
$fieldEffects[] = ['field_id' => $fieldId, 'action' => 'show'];
```

The `field_id` used here is the **raw field_id from the rule action**, which could be:
- A UUID (workflow_fields.id) — used by EnterpriseRuleBuilder
- A `register_field_id` — used by SimpleRuleBuilder/CaseRuleBuilder via `fieldKey()`
- A `custom_<id>` — used by SimpleRuleBuilder/CaseRuleBuilder for custom fields

### Stage 2: Return to WorkflowExecutionService

**File:** `backend/app/Services/EnterpriseRuleEngine.php:1243-1251`

Field effects are returned in the result array:
```php
return [
    'field_effects' => $fieldEffects,
    ...
];
```

### Stage 3: Transformation to Legacy Action Format

**File:** `backend/app/Services/WorkflowExecutionService.php:254-282`

```php
$fieldIdToCanonical = [];
foreach ($fields as $field) {
    $canonical = $field->register_field_id ?? 'custom_'.$field->id;
    $fieldIdToCanonical[$field->id] = $canonical;
    $fieldIdToCanonical[$canonical] = $canonical;
    $fieldIdToCanonical['custom_'.$field->id] = $canonical;
    if (!empty($field->register_field_id)) {
        $fieldIdToCanonical[$field->register_field_id] = $canonical;
    }
}

$canonicalFieldId = $fieldIdToCanonical[$effect['field_id'] ?? ''] ?? ($effect['field_id'] ?? '');
```

This maps UUID → canonical, `custom_<id>` → canonical, `register_field_id` → canonical.

**Status:** PASS — All identifier formats are resolved to canonical.

### Stage 4: Field State Building

**File:** `backend/app/Services/WorkflowExecutionService.php:892-994`

`buildFieldStates` initializes states from field definitions, then applies actions:
```php
foreach ($actions as $action) {
    $targetId = $action['target_field_id'] ?? null;
    switch ($action['action'] ?? '') {
        case 'hide': $states[$targetId]['is_visible'] = false; break;
        case 'show': $states[$targetId]['is_visible'] = true; break;
        ...
    }
}
```

### Stage 5: VisibilityResolver Application

**File:** `backend/app/Services/WorkflowExecutionService.php:297`

```php
$fieldStates = $this->visibilityResolver->applyFieldControlActions($fieldStates, $allActions);
```

**File:** `backend/app/Services/VisibilityResolver.php:120-186`

This applies the SAME actions AGAIN to field states. This is a **DUPLICATE APPLICATION** — the states were already modified by `buildFieldStates`.

**Impact:** No functional bug (idempotent operations), but wasted computation and potential for inconsistency if the two methods diverge.

### Stage 6: API Response

**File:** `backend/app/Services/WorkflowExecutionService.php:406`

```php
'field_states' => $fieldStates,
```

### Stage 7: Frontend State Update

**File:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx:163-178`

```tsx
if (data.field_states) {
    setFieldStates(prev => ({ ...prev, ...data.field_states }));
    const newlyShown = new Set<string>();
    for (const [fid, state] of Object.entries(data.field_states)) {
        const wasHidden = fieldStates[fid]?.is_visible === false;
        const nowVisible = (state as any).is_visible === true;
        if (wasHidden && nowVisible) {
            newlyShown.add(fid);
        }
    }
    setShownFieldIds(prev => { ... });
}
```

### Stage 8: UI Rendering

**File:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx:931-966`

```tsx
{stepFields
    .filter((field) => getFieldState(field).isVisible)
    .map((field) => { ... })}
```

`getFieldState` (line 467-480) reads from `fieldStates[fid]`:
```tsx
const state = fieldStates[fid];
return {
    isVisible: state ? state.is_visible ?? field.is_visible : field.is_visible,
    ...
};
```

---

## FAIL-1: set_visibility — Inconsistent Value Normalization

**Backend (EnterpriseRuleEngine.php:791):**
```php
$isVisible = in_array($value, ['visible', 'show', 'true', true, '1', 1], true);
```

**Backend (WorkflowExecutionService.php:929):**
```php
$val = $action['value'] ?? $action['resolved_value'] ?? 'visible';
$states[$targetId]['is_visible'] = in_array($val, ['visible', 'show', 'true', true, '1', 1], true);
```

**Backend (VisibilityResolver.php:144):**
```php
$val = $action['value'] ?? $action['resolved_value'] ?? 'visible';
$fieldStates[$targetId]['is_visible'] = in_array($val, ['visible', 'show', 'true', true, '1', 1], true);
```

**Frontend (WorkflowExecutionPage.tsx:291):**
```tsx
is_visible: effect.value === 'visible' || effect.value === 'show' || String(effect.value) === 'true' || String(effect.value) === '1',
```

**Issue:** The frontend `handleApplyFieldEffects` does NOT handle `effect.value === true` (boolean) or `effect.value === 1` (number). The backend uses strict comparison (`true` in `in_array`), but the frontend uses string comparison. If the backend sends `value: true` (boolean), the frontend would NOT recognize it as visible because `true === 'visible'` is false, `true === 'show'` is false, `String(true) === 'true'` is TRUE.

**Status:** Actually PASS — `String(true) === 'true'` catches the boolean case. But the normalization is fragile and inconsistent across the codebase.

---

## Field Effect Key Mismatch Risk

**Issue:** The `handleApplyFieldEffects` function in `WorkflowExecutionPage.tsx:231-327` processes effects from routing decisions (BranchHandler), NOT from the main step submission. The field_ids in these effects come directly from the enterprise engine (UUID format), but the frontend `fieldStates` uses canonical keys (`register_field_id` or `custom_<id>`).

**File:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx:237-316`

```tsx
case "show":
    newFieldStates[effect.field_id] = { ... };  // ← effect.field_id is UUID
    newlyShown.add(effect.field_id);             // ← UUID added to shownFieldIds
```

But `getFieldState` (line 468) uses:
```tsx
const fid = resolveFieldId(field);  // ← returns register_field_id or custom_<id>
const state = fieldStates[fid];     // ← looks up by canonical key
```

**Result:** If `effect.field_id` is a UUID and `fieldStates` uses canonical keys, the effect is stored under the UUID key but looked up by canonical key → **effect is invisible**.

**Severity:** HIGH — Field effects from routing decisions (BranchHandler) may not apply to the UI.

---

## Duplicate Application of Field Control Actions

**File:** `backend/app/Services/WorkflowExecutionService.php:296-297`

```php
$fieldStates = $this->buildFieldStates($fields, $allActions);
$fieldStates = $this->visibilityResolver->applyFieldControlActions($fieldStates, $allActions);
```

`buildFieldStates` (line 892-994) already applies all field control actions. Then `applyFieldControlActions` (VisibilityResolver.php:120-186) applies them AGAIN.

**Impact:** Idempotent for most actions. But `append_options` (in buildFieldStates) appends to existing options, and if called twice, would append the same options twice. However, `applyFieldControlActions` does NOT handle `append_options`, so this specific case is safe.

**Severity:** LOW — No functional bug currently, but architectural debt.
