# RULE ROUND-TRIP INTEGRITY AUDIT

## Test: Create → Save → Reload → Edit → Save → Reload

---

## Simple Rules (workflow_rules, rule_type='simple')

### Data Flow

| Stage | Direction | File | Transformation |
|-------|-----------|------|----------------|
| Builder state | In-memory | `SimpleRuleBuilder.tsx` | `{operator, conditions: [{field_id, operator, value}]}` |
| Save payload | Frontend → API | `SimpleRuleBuilder.tsx:110-127` | `condition_logic: {operator, conditions: [...]}`, `actions: [...]` |
| API validation | API → DB | `WorkflowVersionController` | Validates `rule_type`, `condition_logic`, `actions` |
| DB storage | DB | `WorkflowRule` model | JSON casts: `condition_logic`, `actions` |
| Reload | DB → Frontend | `workflowVersionApi.get` | JSON parsed back to objects |
| Editor normalization | Frontend | `normalizeConditions()` | Converts stored format → flat editor model |

### Round-Trip Vulnerabilities

#### V-1: Condition Value Type Coercion

**File:** `frontend/src/components/rules/SimpleRuleBuilder.tsx:276-280`

```typescript
const conds = cl.conditions.map((c: any) => ({
    field_id: c.field_id ?? "",
    operator: c.operator ?? "equals",
    value: c.value != null ? String(c.value) : "",  // ← ALWAYS converted to string
}));
```

**Issue:** If a condition value is stored as a number (e.g., `42`), it's loaded as string `"42"`. On re-save, it's sent as `"42"` (string). The backend stores it as-is in JSON. On next load, it's still `"42"`.

**Impact:** Type changes from `number` to `string` on first reload. Subsequent round-trips are stable.

**Severity:** LOW — String comparison in the engine (`(string) $actualValue === (string) $expectedValue`) handles this correctly.

#### V-2: NO_VALUE_OPERATORS Strip Value

**File:** `frontend/src/components/rules/SimpleRuleBuilder.tsx:115`

```typescript
value: NO_VALUE_OPERATORS.includes(c.operator) ? undefined : c.value,
```

When operator is `is_empty` or `is_not_empty`, value is set to `undefined`. On save, `undefined` is omitted from JSON. On reload, `c.value` is `null` → normalized to `""` by `String(c.value)`.

**Impact:** Value changes from `undefined` → `null` → `""` across round-trips. Stable after first cycle.

**Severity:** NONE — These operators don't use the value field.

#### V-3: Action Value Type

**File:** `frontend/src/types/workflow.ts:144`

```typescript
value?: string | number | boolean;
```

Actions can have `value` as string, number, or boolean. The builder always sends the current editor state. For `set_value`, the value comes from an `<input>` (always string). For `set_fee`, the value comes from a `<select>` (always string fee_code).

**Impact:** No type instability. Values are always strings after the first save.

**Severity:** NONE

### Simple Rule Round-Trip Verdict

| Field | Survives? | Notes |
|-------|-----------|-------|
| rule_type | YES | Always `"simple"` |
| condition_logic.operator | YES | `"and"` or `"or"` |
| condition_logic.conditions | YES | Array of `{field_id, operator, value}` |
| condition field_id | YES | Stored as `fieldKey(f)` = `register_field_id` or `custom_<id>` |
| condition operator | YES | String enum |
| condition value | **MODIFIED** | First reload: number → string. Stable after. |
| actions | YES | Array of `{action, target_field_id, value, fee_code}` |
| action target_field_id | YES | Stored as `fieldKey(f)` |
| action fee_code | YES | String |
| sort_order | YES | Integer |
| is_active | YES | Boolean |

---

## Case Rules (workflow_rules, rule_type='case_based')

### Data Flow

| Stage | Direction | File | Transformation |
|-------|-----------|------|----------------|
| Builder state | In-memory | `CaseRuleBuilder.tsx` | `{cases: [{value, actions, priority}], default_actions}` |
| Save payload | Frontend → API | `CaseRuleBuilder.tsx:616-628` | Priority auto-assigned: `(i + 1) * 100` |
| DB storage | DB | `WorkflowRule` model | JSON casts: `cases`, `default_actions`, `match_mode` |
| Reload | DB → Frontend | `workflowVersionApi.get` | JSON parsed |

### Round-Trip Vulnerabilities

#### V-4: Priority Reassignment

**File:** `frontend/src/components/rules/CaseRuleBuilder.tsx:580`

```typescript
const reordered = arrayMove(cases, oldIndex, newIndex).map((c, i) => ({ ...c, priority: (i + 1) * 100 }));
```

And on save (line 622):
```typescript
cases: cases.map((c, i) => ({ ...c, priority: (i + 1) * 100 })),
```

**Issue:** Priorities are ALWAYS reassigned as `100, 200, 300...` on save. If a user manually sets a priority to `150`, it gets overwritten to `100, 200, 300...` on next save.

**Impact:** Custom priority values don't survive round-trips.

**Severity:** MEDIUM — Priority affects execution order in the engine.

#### V-5: Case Value Type

Case values can be `string` or `string[]` (for multi-select). The builder handles both:
```typescript
value: string | string[];
```

On save, arrays are serialized to JSON. On reload, they're parsed back. **Stable.**

#### V-6: Default Actions with Fee

**File:** `frontend/src/components/rules/CaseRuleBuilder.tsx:427-433`

When a fee is selected in an action:
```typescript
onChange({
    ...action,
    fee_code: selected?.fee_code ?? e.target.value,
    fee_name: selected?.name_ar ?? "",
    value: selected?.amount ?? 0,
});
```

The `value` stores `selected?.amount` (a number). On save, this is sent as a number. On reload, it comes back as a number. **Stable.**

BUT: the `amount` comes from `OfficialFee.amount` (denormalized). If the fee amount changes between saves, the stored `value` becomes stale.

**Severity:** LOW — `value` is display-only for fee actions; execution resolves from `fee_code`.

### Case Rule Round-Trip Verdict

| Field | Survives? | Notes |
|-------|-----------|-------|
| rule_type | YES | Always `"case_based"` |
| trigger_field_id | YES | String |
| match_mode | YES | String enum |
| cases[].value | YES | String or string[] |
| cases[].actions | YES | Array of RuleAction |
| cases[].priority | **MODIFIED** | Always reassigned as `(i+1)*100` |
| default_actions | YES | Array of RuleAction |
| condition_logic | PASS | Set to `{operator: "and"}` (empty) |

---

## Enterprise Rules (validation_rules with rule_config)

### Data Flow

| Stage | Direction | File | Transformation |
|-------|-----------|------|----------------|
| Builder state | In-memory | `EnterpriseRuleBuilder.tsx` | `{conditions: ConditionNode[], actions: RuleAction[]}` |
| Save payload | Frontend → API | `EnterpriseRuleBuilder.tsx:232-247` | `rule_config: {conditions, actions, else_actions, cases}` |
| DB storage | DB | `ValidationRule` model | JSON cast: `rule_config` |
| Reload | DB → Frontend | `workflowVersionApi.getValidationRules` | JSON parsed |

### Round-Trip Vulnerabilities

#### V-7: Condition ID Regeneration

**File:** `frontend/src/components/validation/EnterpriseRuleBuilder.tsx:121-123`

```typescript
const [conditions, setConditions] = useState<ConditionNode[]>(
    rule?.conditions ?? [{ id: generateId(), type: "simple", ... }]
);
```

When loading an existing rule, `rule?.conditions` is used directly. The `id` field from the DB is preserved. **Stable.**

BUT: when the rule is saved, the conditions (with their IDs) are sent to the backend. The backend stores them in `rule_config`. On reload, the same IDs come back. **Stable.**

#### V-8: Action ID Preservation

Actions have `id: generateId()` when created. When loaded from DB, the `id` is preserved from `rule_config.actions[].id`. **Stable.**

#### V-9: Case-Based Enterprise Rules

When `useCases` is true, the save payload sets `conditions: []`:
```typescript
rule_config: {
    conditions: useCases ? [] : conditions,
    actions,
    else_actions: elseActions,
    cases: useCases ? cases : undefined,
},
```

On reload, if `rule_config.conditions` is `[]` and `rule_config.cases` is present, the builder should detect this and set `useCases = true`. 

**File:** `EnterpriseRuleBuilder.tsx:131`
```typescript
const [useCases, setUseCases] = useState(rule?.cases ? true : false);
```

**Issue:** If `rule.cases` exists but is an empty array `[]`, `rule?.cases` is truthy (empty array is truthy in JS), so `useCases = true`. This is correct.

If `rule.cases` is `undefined` (not saved with cases), `useCases = false`. Also correct.

**Severity:** NONE — Round-trip is stable.

### Enterprise Rule Round-Trip Verdict

| Field | Survives? | Notes |
|-------|-----------|-------|
| conditions | YES | With IDs preserved |
| conditions[].type | YES | `"simple"` or `"group"` |
| conditions[].field_id | YES | String |
| conditions[].operator | YES | ConditionOperator enum |
| conditions[].value | YES | Any type |
| actions | YES | With IDs preserved |
| actions[].type | YES | ActionType enum |
| actions[].field_id | YES | String |
| actions[].value | YES | Any type |
| actions[].fee_code | YES | String |
| else_actions | YES | Same as actions |
| cases | YES | With IDs preserved |
| category | YES | String enum |
| priority | YES | Integer |
| conflict_resolution | YES | String enum |

---

## Summary

| Rule Type | Round-Trip Stable? | Vulnerabilities |
|-----------|-------------------|-----------------|
| Simple | **YES** (after 1 cycle) | V-1 (value type coercion), V-2 (undefined stripping) |
| Case-Based | **MOSTLY** | V-4 (priority reassignment), V-6 (fee amount staleness) |
| Enterprise | **YES** | None critical |
