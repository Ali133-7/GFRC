# FIELD SYSTEM FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** RegisterField, WorkflowField, Runtime Field State

---

## EXECUTIVE SUMMARY

The field system uses a three-layer inheritance model: RegisterField → WorkflowField → Runtime State. The architecture is sound but contains significant issues with metadata loss, stale values, key mismatches, and inconsistent field identification across the system.

---

## 1. INHERITANCE ANALYSIS

### 1.1 Inheritance Chain

```
System Default
    ↓ (lowest priority)
RegisterField (name, label, type, options, validation_rules)
    ↓
WorkflowField (label_override, default_value, is_required, is_visible, is_readonly,
               is_editable, is_locked, is_financial, is_insured, fee_code,
               calculation_formula, field_type, options, validation_rules,
               conditional_validation_rules, cross_field_validation_rules,
               computed_formula, cascade_config, option_source_config)
    ↓ (highest priority)
Runtime State (is_visible, is_required, is_readonly, is_editable, is_locked,
               field_type, options — modified by rule actions)
```

### 1.2 FieldInheritanceResolver Priority Chain

| Property | Override Source | Fallback | Verified |
|----------|----------------|----------|----------|
| field_type | WorkflowField | RegisterField | ✅ |
| is_required | WorkflowField | RegisterField | ✅ |
| is_visible | WorkflowField | RegisterField | ✅ |
| is_editable | WorkflowField | RegisterField | ✅ |
| is_locked | WorkflowField | RegisterField | ✅ |
| is_financial | WorkflowField | RegisterField | ✅ |
| is_insured | WorkflowField | RegisterField | ✅ |
| insurance_value | WorkflowField | RegisterField | ✅ |
| priority | WorkflowField | RegisterField | ✅ |
| options | WorkflowField | RegisterField | ✅ |
| validation_rules | WorkflowField | RegisterField | ✅ |

### 1.3 Issues

#### FS-001: FieldInheritanceResolver missing properties in getSystemDefault [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `getSystemDefault()` match expression does not include `conditional_validation_rules` or `cross_field_validation_rules`
- **Affected Files:** `backend/app/Services/FieldInheritanceResolver.php`
- **Impact:** These properties return `null` instead of `[]` when no override exists
- **Solution:** Add missing properties to match expression

#### FS-002: getRawOriginal usage is fragile [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `getWorkflowOverride()` uses `getRawOriginal()` to avoid accessor recursion
- **Impact:** If model column names change, this breaks silently
- **Solution:** Use explicit property access with documented column names

---

## 2. OVERRIDES ANALYSIS

### 2.1 Override Mechanism

| Override Type | Storage | Applied By | Verified |
|--------------|---------|-----------|----------|
| Label override | `label_override` column | `WorkflowField::getLabelAttribute()` | ✅ |
| Default value | `default_value` column | `WorkflowFieldSchemaBuilder` | ✅ |
| Required override | `is_required` column | `FieldInheritanceResolver` | ✅ |
| Visibility override | `is_visible` column | `VisibilityResolver` | ✅ |
| Readonly override | `is_readonly` column | `FieldInheritanceResolver` | ✅ |
| Editable override | `is_editable` column | `FieldInheritanceResolver` | ✅ |
| Lock override | `is_locked` column | `FieldInheritanceResolver` | ✅ |
| Financial override | `is_financial` column | `FieldInheritanceResolver` | ✅ |
| Type override | `field_type` column | `FieldInheritanceResolver` | ✅ |
| Options override | `options` JSON column | `CascadingSelectEngine` + `DynamicOptionSource` | ✅ |

### 2.2 Issues

#### FS-003: WorkflowField accessor shadows DB column [HIGH]
- **Severity:** High
- **Root Cause:** `getFieldTypeAttribute()` overrides the DB column `field_type` by resolving through `FieldInheritanceResolver`
- **Affected Files:** `backend/app/Models/WorkflowField.php`
- **Impact:** Direct access to `$field->field_type` returns resolved value, not raw DB value. This breaks any code that needs the raw value
- **Solution:** Rename accessor to `getResolvedFieldTypeAttribute()` or use `getRawOriginal('field_type')` internally

---

## 3. VISIBILITY ANALYSIS

### 3.1 Visibility Resolution

| Source | Method | Condition Format | Verified |
|--------|--------|-----------------|----------|
| WorkflowField | `condition_logic` JSON | ConditionLogic | ✅ |
| Rule action | `set_visibility`, `show`, `hide` | Action effect | ✅ |
| Step visibility | `WorkflowStep::condition_logic` | ConditionLogic | ✅ |

### 3.2 Issues

#### FS-004: VisibilityResolver conflates disabled with hidden [HIGH]
- **Severity:** High
- **Root Cause:** `applyFieldControlActions()` `disable` action sets `is_visible = false`
- **Affected Files:** `backend/app/Services/VisibilityResolver.php`
- **Impact:** A disabled field becomes hidden instead of visible-but-not-editable
- **Solution:** `disable` should set `is_editable = false`, not `is_visible = false`

---

## 4. REQUIRED STATE ANALYSIS

### 4.1 Required State Flow

| Stage | Source | Override | Verified |
|-------|--------|----------|----------|
| Definition | RegisterField.is_required | — | ✅ |
| Workflow binding | WorkflowField.is_required | FieldInheritanceResolver | ✅ |
| Runtime | Rule action `set_required` | VisibilityResolver | ✅ |
| Validation | ConditionalValidationEngine | Rule conditions | ✅ |

### 4.2 Issues

#### FS-005: Required state not enforced on locked fields [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `buildFieldStates()` sets `is_readonly = is_locked`, but `is_required` is independent
- **Impact:** A locked field can be marked as required, which is contradictory
- **Solution:** When `is_locked` is true, set `is_required = false`

---

## 5. READONLY STATE ANALYSIS

### 5.1 Readonly State Flow

| Source | Sets Readonly | Verified |
|--------|--------------|----------|
| WorkflowField.is_readonly | ✅ | ✅ |
| WorkflowField.is_locked | ✅ (implies readonly) | ✅ |
| Rule action `set_readonly` | ✅ | ✅ |
| Rule action `set_lock` | ✅ | ✅ |
| Rule action `set_editable(false)` | ✅ | ✅ |

### 5.2 Issues

#### FS-006: set_editable and set_readonly are not symmetric [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `set_editable` sets `is_readonly = false`, but `set_readonly` does not set `is_editable`
- **Affected Files:** `backend/app/Services/WorkflowExecutionService.php:1009-1015`
- **Impact:** Setting readonly=true then editable=true leaves readonly=true
- **Solution:** Make set_readonly and set_editable symmetric

---

## 6. LOCK STATE ANALYSIS

### 6.1 Lock State Flow

| Source | Sets Lock | Verified |
|--------|----------|----------|
| WorkflowField.is_locked | ✅ | ✅ |
| Rule action `set_lock` | ✅ | ✅ |
| Rule action `unlock` | ✅ | ✅ |

### 6.2 Issues

#### FS-007: Locked fields can still be modified by set_value actions [HIGH]
- **Severity:** High
- **Root Cause:** `sanitizeInput()` prevents locked field modification from user input, but rule actions (`set_value`, `calculate`, `set_fee`) can still modify locked fields
- **Affected Files:** `backend/app/Services/WorkflowExecutionService.php:901-905`, `applySetValueActions()`
- **Impact:** Rules can bypass lock state and modify locked field values
- **Solution:** Check lock state in `applySetValueActions()` and skip locked fields

---

## 7. DEFAULT VALUES ANALYSIS

### 7.1 Default Value Sources

| Source | Priority | Applied By | Verified |
|--------|----------|-----------|----------|
| RegisterField.default_value | Low | FieldInheritanceResolver | ✅ |
| WorkflowField.default_value | Medium | WorkflowFieldSchemaBuilder | ✅ |
| Rule action `set_value` | High | applySetValueActions | ✅ |

### 7.2 Issues

#### FS-008: Default values not applied at execution start [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `WorkflowExecutionService::start()` creates execution with empty `values_snapshot`
- **Affected Files:** `backend/app/Services/WorkflowExecutionService.php:74-110`
- **Impact:** Default values from field definitions are not pre-populated
- **Solution:** Apply default values when starting execution

---

## 8. OPTION PROPAGATION ANALYSIS

### 8.1 Option Resolution Chain

```
RegisterField.options (JSON)
    ↓
WorkflowField.options (JSON override)
    ↓
CascadingSelectEngine (parent-dependent filtering)
    ↓
DynamicOptionSource (DB/API/service resolution)
    ↓
Rule action set_options/append_options/remove_options
    ↓
Frontend rendering
```

### 8.2 Issues

#### FS-009: CascadingSelectEngine silently drops associative arrays [HIGH]
- **Severity:** High
- **Root Cause:** `resolveFromMapping()` only handles numerically-indexed arrays
- **Affected Files:** `backend/app/Services/CascadingSelectEngine.php`
- **Impact:** Cascading options with label/value objects are silently dropped
- **Solution:** Handle associative array format

#### FS-010: DynamicOptionSource returns empty array on all errors [MEDIUM]
- **Severity:** Medium
- **Root Cause:** All three resolvers (DB, API, Service) silently return `[]` on error
- **Affected Files:** `backend/app/Services/DynamicOptionSource.php`
- **Impact:** Debugging option resolution failures is impossible
- **Solution:** Log errors and return error indicator

---

## 9. LOST METADATA DETECTION

### 9.1 Metadata Loss Vectors

| Metadata | Lost When | Impact |
|----------|-----------|--------|
| `inheritance_source` | Version clone | Field falls back to register source |
| `expectation` | Validation rule clone | Rule loses expectation value |
| `conditional_validation_rules` | Field clone | Conditional validation lost |
| `cross_field_validation_rules` | Field clone | Cross-field validation lost |
| `computed_dependencies` | Field clone | Computed field dependencies lost |
| `cascade_config` | Field clone | Cascading select configuration lost |

#### FS-011: Version cloning loses enterprise field metadata [HIGH]
- **Severity:** High
- **Root Cause:** `replicateVersionContents()` does not copy enterprise-specific columns
- **Affected Files:** `backend/app/Http/Controllers/Api/WorkflowVersionController.php`
- **Impact:** Cloned workflows lose conditional validation, cross-field validation, computed fields, cascading selects, and dynamic options
- **Solution:** Add all enterprise columns to clone mapping

---

## 10. STALE VALUES DETECTION

### 10.1 Stale Value Vectors

| Value | Stale When | Impact |
|-------|-----------|--------|
| Fee amount | Fee version changes | Execution uses old amount |
| Field options | RegisterField.options updated | WorkflowField.options stale |
| Validation rules | RegisterField.validation_rules updated | WorkflowField.validation_rules stale |
| Field type | RegisterField.field_type changed | WorkflowField.field_type stale |

#### FS-012: No mechanism to detect stale inherited values [HIGH]
- **Severity:** High
- **Root Cause:** Once a WorkflowField is created, it does not track whether its inherited values are current
- **Impact:** Changes to RegisterField are not reflected in existing WorkflowFields
- **Solution:** Add `inherited_at` timestamp and refresh mechanism

---

## 11. KEY MISMATCH DETECTION

### 11.1 Field Key Convention

| Context | Key Format | Example |
|---------|-----------|---------|
| RegisterField | `name` column | `first_name` |
| WorkflowField (register) | `register_field_id` (UUID) | `abc-123-def` |
| WorkflowField (custom) | `custom_<id>` | `custom_abc-123` |
| Rule condition/action | `register_field_id` or `custom_<id>` | `abc-123-def` or `custom_abc-123` |
| Execution values | `register_field_id` or `custom_<id>` | Same as rules |
| Frontend fieldKey | `register_field_id ?? custom_<id>` | Same |

### 11.2 Issues

#### FS-013: Inconsistent field key resolution across engines [HIGH]
- **Severity:** High
- **Root Cause:** Some engines use `$field->register_field_id`, others use `$field->register_field_id ?? 'custom_'.$field->id`
- **Evidence:**
  - `InsuranceEngine::collectInsuranceSnapshots()` uses only `register_field_id`
  - `WorkflowExecutionService::sanitizeInput()` uses both formats
  - `EnterpriseRuleEngine::executeActions()` uses `field_id` from action
- **Impact:** Custom fields may be silently missed by insurance snapshots, fee calculations, and rule actions
- **Solution:** Standardize on `register_field_id ?? 'custom_'.$field->id` everywhere

#### FS-014: register_field_id nullable creates orphan references [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `workflow_fields.register_field_id` is nullable (migration 2026_06_02_000004)
- **Impact:** Custom fields have no reference to a register field, breaking any code that assumes register_field_id exists
- **Solution:** Document that custom fields use `custom_<id>` convention

---

## 12. FIELD IDENTIFIER CONSISTENCY

### 12.1 Identifier Usage Map

| Component | Uses UUID | Uses register_field_id | Uses custom_<id> | Uses name |
|-----------|-----------|----------------------|------------------|-----------|
| WorkflowExecutionService | ✅ | ✅ | ✅ | ❌ |
| EnterpriseRuleEngine | ❌ | ✅ | ✅ | ❌ |
| RuleEngineV2 | ❌ | ✅ | ✅ | ❌ |
| ValidationEngine | ❌ | ✅ | ❌ | ✅ (name) |
| WorkflowFieldSchemaBuilder | ✅ | ✅ | ✅ | ❌ |
| FieldInheritanceResolver | ✅ | ✅ | ❌ | ❌ |
| InsuranceEngine | ❌ | ✅ | ❌ | ❌ |
| Frontend fieldKey | ❌ | ✅ | ✅ | ❌ |

#### FS-015: ValidationEngine uses field name, not UUID [HIGH]
- **Severity:** High
- **Root Cause:** `ValidationEngine::checkDuplicate()` and other methods use field names from `target_fields` JSON
- **Affected Files:** `backend/app/Services/ValidationEngine.php`
- **Impact:** Field name changes break validation rules
- **Solution:** Use field UUIDs or register_field_id in validation rules

---

## FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 8 |
| Medium | 7 |
| Low | 0 |

---

## RECOMMENDED FIXES PRIORITY

1. **FS-007:** Prevent rule actions from modifying locked fields
2. **FS-003:** Fix WorkflowField accessor shadowing DB column
3. **FS-004:** Fix VisibilityResolver disable action
4. **FS-009:** Fix CascadingSelectEngine associative array handling
5. **FS-011:** Fix version cloning to copy all enterprise metadata
6. **FS-012:** Add stale value detection mechanism
7. **FS-013:** Standardize field key resolution
8. **FS-015:** Fix ValidationEngine to use field UUIDs
9. **FS-001:** Add missing properties to getSystemDefault
10. **FS-006:** Make set_editable and set_readonly symmetric
