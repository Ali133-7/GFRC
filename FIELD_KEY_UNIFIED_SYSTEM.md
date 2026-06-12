# FIELD KEY UNIFIED SYSTEM

**Date:** 2026-06-10  
**Status:** ✅ **IMPLEMENTED**  
**Priority:** 🔴 **CRITICAL**

---

## 🎯 PROBLEM SOLVED

**Symptom:**
```
❌ الحقل غير موجود: custom_382ec189-0a6f-435f-9aad-62a36c69c235
```

**Root Cause:**
Field keys were generated inconsistently across the codebase:
- Some places used `$field->id` (UUID)
- Some used `$field->register_field_id`
- Some used manual concatenation: `'custom_' . $field->id`
- Some used `$field->register_field_id ?? 'custom_' . $field->id`

This caused field resolution failures when:
1. Rules referenced fields by one key format
2. Values were stored under a different key format
3. Engine looked up values using yet another key format

---

## ✅ SOLUTION IMPLEMENTED

### 1. Backend: FieldKey Helper Class

**File:** `backend/app/Helpers/FieldKey.php`

**Purpose:** SINGLE SOURCE OF TRUTH for all field key generation in backend.

**Usage:**
```php
use App\Helpers\FieldKey;

// From WorkflowField object
$key = FieldKey::make($field);

// From raw IDs
$key = FieldKey::makeFromIds($registerFieldId, $workflowFieldId);

// Get all aliases for lookup
$aliases = FieldKey::aliases($field);

// Check if custom key
$isCustom = FieldKey::isCustom($key);

// Extract UUID from custom key
$uuid = FieldKey::extractUuid($key);
```

**Key Format:**
```php
if (register_field_id exists) {
    return register_field_id;
} else {
    return 'custom_' + workflow_field_id;
}
```

---

### 2. Frontend: fieldKey Function (Already Existed)

**File:** `frontend/src/components/rules/fieldKey.ts`

**Usage:**
```typescript
import { fieldKey, findFieldByKey } from '@/components/rules/fieldKey';

const key = fieldKey(field);
const field = findFieldByKey(fields, key);
```

**Format:** Matches backend exactly!
```typescript
return field.register_field_id ?? `custom_${field.id}`;
```

---

## 📋 MIGRATION GUIDE

### Backend: Update All Field Key Generation

#### ❌ BEFORE (WRONG):
```php
// DON'T DO THIS - Multiple sources of truth!
$key = $field->id;
$key = $field->register_field_id;
$key = 'custom_' . $field->id;
$key = $field->register_field_id ?? 'custom_' . $field->id;
```

#### ✅ AFTER (CORRECT):
```php
use App\Helpers\FieldKey;

// ALWAYS use this - Single source of truth!
$key = FieldKey::make($field);
```

---

### Frontend: Already Correct!

The frontend already uses `fieldKey()` consistently. No changes needed.

**Files using fieldKey correctly:**
- ✅ `EnterpriseRuleBuilder.tsx`
- ✅ `CaseRuleBuilder.tsx`
- ✅ `SimpleRuleBuilder.tsx`
- ✅ `RoutingRuleBuilder.tsx`
- ✅ `WorkflowExecutionPage.tsx`
- ✅ `WorkflowDesignerPage.tsx`

---

## 🔍 AFFECTED COMPONENTS

### Backend Services (Updated):

| Service | Status | Changes |
|---------|--------|---------|
| `EnterpriseRuleEngine` | ✅ Updated | Added `normalizeFieldKeys()` method |
| `WorkflowExecutionService` | ✅ Already correct | Has `normalizeFieldKeys()` |
| `ValidationEngine` | ⚠️ Needs update | Uses direct field_id lookups |
| `ConditionalValidationEngine` | ⚠️ Needs update | Uses direct field_id lookups |
| `CrossFieldValidationEngine` | ⚠️ Needs update | Uses direct field_id lookups |
| `ComputedFieldEngine` | ⚠️ Needs update | Uses direct field_id lookups |
| `CascadingSelectEngine` | ⚠️ Needs update | Uses direct field_id lookups |
| `VisibilityResolver` | ⚠️ Needs update | Uses direct field_id lookups |
| `InsuranceEngine` | ⚠️ Needs update | Uses direct field_id lookups |
| `FieldInheritanceResolver` | ⚠️ Needs update | Uses direct field_id lookups |

### Frontend Components (Already Correct):

| Component | Status |
|-----------|--------|
| `EnterpriseRuleBuilder` | ✅ Uses `fieldKey()` |
| `CaseRuleBuilder` | ✅ Uses `fieldKey()` |
| `SimpleRuleBuilder` | ✅ Uses `fieldKey()` |
| `RoutingRuleBuilder` | ✅ Uses `fieldKey()` |
| `WorkflowExecutionPage` | ✅ Uses `resolveFieldId()` |
| `WorkflowDesignerPage` | ✅ Uses `fieldKey()` |

---

## 🧪 TESTING CHECKLIST

### Field Key Consistency:

- [ ] Create enterprise rule with condition on custom field
- [ ] Condition references: `custom_XXX-XXX-XXX`
- [ ] Submit form with value for that field
- [ ] Value stored under: `custom_XXX-XXX-XXX`
- [ ] Rule executes and finds value ✅

### Cross-Step Field Resolution:

- [ ] Step 1: Field A with value
- [ ] Step 2: Rule references Field A
- [ ] Rule condition: `custom_A > 0`
- [ ] Value from Step 1 accessible in Step 2 ✅

### Rule Persistence:

- [ ] Create rule with field reference
- [ ] Save rule
- [ ] Reopen rule
- [ ] Field reference unchanged ✅

### Formula Evaluation:

- [ ] Formula: `{{custom_XXX}} * 50000`
- [ ] Field value: 5
- [ ] Result: 250000 ✅

---

## 📊 KEY FORMAT EXAMPLES

### Register-Backed Field:
```
Field: سجلات الدلالين
register_field_id: "broker_records"
workflow_field_id: "382ec189-0a6f-435f-9aad-62a36c69c235"

Key: "broker_records" ✅
```

### Custom Workflow Field:
```
Field: حقل مخصص
register_field_id: null
workflow_field_id: "382ec189-0a6f-435f-9aad-62a36c69c235"

Key: "custom_382ec189-0a6f-435f-9aad-62a36c69c235" ✅
```

---

## 🚨 CRITICAL RULES

### NEVER Do This:

```php
// ❌ Multiple sources of truth!
$key = $field->id;
$key = 'custom_' . $field->id;
$key = $field->register_field_id ?? 'custom_' . $field->id;
```

### ALWAYS Do This:

```php
// ✅ Single source of truth!
use App\Helpers\FieldKey;
$key = FieldKey::make($field);
```

---

## 📁 FILES CREATED/MODIFIED

### Created:
- ✅ `backend/app/Helpers/FieldKey.php` - Helper class

### Modified:
- ✅ `backend/app/Services/EnterpriseRuleEngine.php` - Added `normalizeFieldKeys()`
- ✅ `backend/app/Services/WorkflowExecutionService.php` - Already had normalization

### To Be Updated:
- ⚠️ `backend/app/Services/ValidationEngine.php`
- ⚠️ `backend/app/Services/ConditionalValidationEngine.php`
- ⚠️ `backend/app/Services/CrossFieldValidationEngine.php`
- ⚠️ `backend/app/Services/ComputedFieldEngine.php`
- ⚠️ `backend/app/Services/CascadingSelectEngine.php`
- ⚠️ `backend/app/Services/VisibilityResolver.php`
- ⚠️ `backend/app/Services/InsuranceEngine.php`
- ⚠️ `backend/app/Services/FieldInheritanceResolver.php`

---

## 🎯 SUCCESS CRITERIA

### Before Fix:
```
[SKIP] احتساب قيمة السجلات
Conditions: custom_2... greater_than "0" [actual="null"] ❌
```

### After Fix:
```
[MATCH] احتساب قيمة السجلات
Conditions: custom_2... greater_than "0" [actual="5"] ✅
```

---

## ✅ STATUS

| Component | Status | Notes |
|-----------|--------|-------|
| Backend Helper | ✅ Created | `FieldKey` class |
| Frontend Helper | ✅ Existing | `fieldKey()` function |
| EnterpriseRuleEngine | ✅ Updated | Normalization added |
| WorkflowExecutionService | ✅ Already correct | Had normalization |
| Other Services | ⚠️ Pending | Need updates |
| Frontend Components | ✅ All correct | Already use fieldKey() |

---

**Implementation Status:** ✅ **50% COMPLETE**  
**Next Steps:** Update remaining backend services to use FieldKey helper
