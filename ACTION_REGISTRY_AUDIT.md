# ACTION REGISTRY AUDIT

## CRITICAL FINDING: Duplicate Action Definitions

### CONFIRMED: `pause_execution` and `resume_execution` are DUPLICATED

**File:** `frontend/src/types/enterprise-rule-engine.ts:286-329`

The `ACTION_METADATA` array contains DUPLICATE entries:

| Line | Value | Label |
|------|-------|-------|
| 308 | `pause_execution` | إيقاف مؤقت |
| 309 | `resume_execution` | استئناف |
| 312 | `pause_execution` | إيقاف مؤقت |
| 313 | `resume_execution` | استئناف |

**Exact code (lines 308-313):**
```typescript
{ value: 'pause_execution', label: 'إيقاف مؤقت', label_en: 'Pause Execution', icon: '⏸️', category: 'control', description_ar: 'إيقاف التنفيذ مؤقتاً' },
{ value: 'resume_execution', label: 'استئناف', label_en: 'Resume Execution', icon: '▶️', category: 'control', description_ar: 'استئناف التنفيذ' },
// TODO: Phase 2 — Workflow Administration Actions (not implemented)
// ...
{ value: 'pause_execution', label: 'إيقاف مؤقت', label_en: 'Pause Execution', icon: '⏸️', category: 'control', description_ar: 'إيقاف التنفيذ مؤقتاً' },
{ value: 'resume_execution', label: 'استئناف', label_en: 'Resume Execution', icon: '▶️', category: 'control', description_ar: 'استئناف التنفيذ' },
```

**Root Cause:** Copy-paste error. The entries at lines 308-309 were defined, then commented-out Phase 2 entries were added (lines 314-319), and then lines 312-313 DUPLICATED the pause/resume entries after the TODO comment block.

**Impact:**
1. React renders two `<option>` elements with the same `key` (`pause_execution` and `resume_execution`)
2. Console warning: `Encountered two children with the same key, 'pause_execution'`
3. Action dropdown shows "إيقاف مؤقت" and "استئناف" TWICE
4. If a user selects the second instance, the saved action is identical to the first — no functional bug, but confusing UX

---

## Action Registry Completeness Audit

### ActionType Union (enterprise-rule-engine.ts:43-54)

| ActionType | In ACTION_METADATA | In EnterpriseRuleEngine Backend | In SimpleRuleBuilder | In CaseRuleBuilder |
|------------|-------------------|-------------------------------|---------------------|-------------------|
| set_value | YES | YES | YES | YES |
| override_value | YES | YES | NO | YES |
| calculate | YES | YES | YES | YES |
| set_fee | YES | YES | YES | YES |
| apply_discount | YES | YES | NO | YES |
| set_visibility | YES | YES | NO | YES |
| set_required | YES | YES | YES | YES |
| set_optional | YES | YES | NO | NO |
| set_readonly | YES | YES | YES | YES |
| set_editable | YES | YES | NO | YES |
| set_lock | YES | YES | NO | YES |
| unlock | YES | YES | NO | NO |
| set_options | YES | YES | NO | YES |
| append_options | YES | YES | NO | NO |
| remove_options | YES | YES | NO | NO |
| set_field_type | YES | YES | NO | YES |
| clear_value | YES | YES | NO | NO |
| copy_value | YES | YES | NO | NO |
| route_to_step | YES | YES | NO | NO |
| route_to_workflow | YES | YES | NO | NO |
| switch_mode | YES | YES | NO | NO |
| pause_execution | YES (x2) | YES | NO | NO |
| resume_execution | YES (x2) | YES | NO | NO |
| skip_step | YES | YES | NO | YES |
| generate_reference | YES | YES | NO | NO |
| execute_validation | YES | YES | NO | NO |
| show_message | YES | YES | NO | NO |
| show_warning | YES | YES | NO | NO |
| show_error | YES | YES | NO | NO |
| show_confirmation | YES | YES | NO | NO |
| audit_log | YES | YES | NO | NO |

### Backend-Only Actions (Not in ActionType Union)

| Action | In Backend | In Frontend Type | Notes |
|--------|-----------|-----------------|-------|
| show | YES (EnterpriseRuleEngine.php:743) | NO (not in ActionType) | Only in SimpleRuleBuilder ACTION_TYPES |
| hide | YES (EnterpriseRuleEngine.php:754) | NO (not in ActionType) | Only in SimpleRuleBuilder ACTION_TYPES |
| enable | YES (EnterpriseRuleEngine.php:1044) | NO | Not in any builder |
| disable | YES (EnterpriseRuleEngine.php:1059) | NO | Not in any builder |

**Issue:** `show` and `hide` are implemented in the backend and available in `SimpleRuleBuilder` but NOT in the `ActionType` union. This means the EnterpriseRuleBuilder cannot use `show`/`hide` — only `set_visibility`.

---

## Action Key Generation Audit

### EnterpriseRuleBuilder
**File:** `frontend/src/components/validation/EnterpriseRuleBuilder.tsx:28-30`
```typescript
function generateId() {
    return Math.random().toString(36).substring(2, 11);
}
```

Used for: conditions (line 122), actions (line 169), cases (line 185), case conditions (line 187), case actions (line 214).

**Issue:** `Math.random()` is not cryptographically unique. In rapid succession (e.g., adding multiple actions quickly), collisions are theoretically possible but extremely unlikely with 9-character base-36 IDs (~46 billion combinations).

**Severity:** LOW — No practical collision risk.

### SimpleRuleBuilder / CaseRuleBuilder
Actions do NOT have IDs. They use array index as React key:
```tsx
{actions.map((a, i) => (
    <div key={i} ...>
```

**Severity:** LOW — Index keys cause issues only when items are reordered/removed mid-list.

### CaseRuleBuilder Sortable Items
**File:** `frontend/src/components/rules/CaseRuleBuilder.tsx:76`
```tsx
const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: `case-${index}` });
```

Uses `case-${index}` as sortable ID. After drag reorder, indices change, which could cause brief React reconciliation issues.

**Severity:** LOW — `arrayMove` updates state atomically.

---

## Summary

| Finding | Severity | Location |
|---------|----------|----------|
| `pause_execution` duplicated in ACTION_METADATA | **HIGH** | `enterprise-rule-engine.ts:308,312` |
| `resume_execution` duplicated in ACTION_METADATA | **HIGH** | `enterprise-rule-engine.ts:309,313` |
| `show`/`hide` missing from ActionType union | MEDIUM | `enterprise-rule-engine.ts:43-54` |
| `enable`/`disable` not available in any builder | LOW | N/A |
| `Math.random()` for ID generation | LOW | `EnterpriseRuleBuilder.tsx:28-30` |
