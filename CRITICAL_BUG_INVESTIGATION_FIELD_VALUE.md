# CRITICAL BUG INVESTIGATION - Field Value Not Submitted

**Date:** 2026-06-10  
**Severity:** 🔴 **CRITICAL**  
**Status:** 🔍 **INVESTIGATING**

---

## 🐛 PROBLEM IDENTIFIED

**Symptom from Execution Trace:**
```
[SKIP] احتساب قيمة السجلات⚡ Enterprise
Conditions: custom_4821199e-7731-4485-a24c-f12dff321122 greater_than "0" [actual="null"]
```

**Field State:**
```
custom_4821199e-7731-4485-a24c-f12dff321122: visible=Y, required=N, readonly=N
```

**Modified Values:**
```
❌ custom_4821199e-7731-4485-a24c-f12dff321122 = NOT PRESENT!
```

---

## 🔍 ROOT CAUSE ANALYSIS

### Log Evidence:

**Latest normalizeFieldKeys call:**
```json
{
  "input_keys": [
    "f1a68a5d-b086-43c8-919c-87de19cc247b",
    "db4566e7-8dc6-436f-979b-137723abb505",
    "custom_a1020691-ca08-4cbe-96d4-aa40e60ed8ea",
    "custom_3fea84d9-991e-4159-a667-42882d1d0e65",
    "999e0856-f2cd-4d50-a6f3-86038c07a293",
    "001240db-86e4-4b45-9a22-b96c4d340de8",
    "21156cb0-8961-463f-ae10-ce7b3cff7367",
    "c754fc8a-1f24-48f2-bc1a-50796108aa75"
  ],
  "output_keys": [...16 fields...],
  "fields_count": 16
}
```

**CRITICAL FINDING:** `custom_4821199e-7731-4485-a24c-f12dff321122` is **NOT** in `input_keys`!

---

## 🎯 DIAGNOSIS

### The Problem:

The field `custom_4821199e-7731-4485-a24c-f12dff321122` (سجلات الدلالين):

1. ✅ **Is visible** in the form (`visible=Y`)
2. ✅ **Is not readonly** (`readonly=N`)
3. ❌ **Was NOT submitted** by the user (not in input_keys)
4. ❌ **Is NOT required** (`required=N`) - so form allows empty submission
5. ❌ **Value is null** - condition fails

### Why User Didn't Submit Value:

**Scenario A: User Didn't See the Field**
- Field might be in a different step
- Field might be hidden by CSS/display issue
- Field might be below the fold (not scrolled to)

**Scenario B: User Saw But Didn't Enter Value**
- Field is not required, so user skipped it
- Field placeholder might be confusing
- User might not know what value to enter

**Scenario C: Frontend Form Issue**
- Field rendering bug
- Field value not captured on change
- Field name/key mismatch in form submission

---

## 🔧 INVESTIGATION STEPS

### Step 1: Check Which Step Contains the Field

```sql
SELECT step_id, label, is_visible, is_required
FROM workflow_fields
WHERE register_field_id = '4821199e-7731-4485-a24c-f12dff321122'
   OR id = '4821199e-7731-4485-a24c-f12dff321122';
```

**Expected:** Field should be in Step 1 (current step being executed)
**If in Step 2+:** Field won't be visible in current step form!

---

### Step 2: Check Frontend Form Rendering

**In Browser Console:**
```javascript
// Check if field is rendered
document.querySelector('input[name="custom_4821199e-7731-4485-a24c-f12dff321122"]');
// Should return the input element

// Check field value
document.querySelector('input[name="custom_4821199e-7731-4485-a24c-f12dff321122"]')?.value;
// Should show the value user entered
```

---

### Step 3: Check Form Submission Payload

**In Browser Network Tab:**
1. Find the PUT request to `/workflow-executions/{id}/step`
2. Check request payload
3. Look for `custom_4821199e-7731-4485-a24c-f12dff321122` key

**Expected:**
```json
{
  "step_index": 0,
  "values": {
    "custom_4821199e-7731-4485-a24c-f12dff321122": "5",
    ...
  }
}
```

**If Missing:** Frontend didn't include field in submission

---

## ✅ IMMEDIATE FIXES

### Fix 1: Make Field Required (Temporary)

**In Rule Condition or Field Settings:**
```
Field: سجلات الدلالين
Required: YES ✅
```

This forces user to enter a value before proceeding.

---

### Fix 2: Add Default Value

**In Field Configuration:**
```
Default Value: 0
```

This ensures field always has a value, even if user doesn't enter one.

---

### Fix 3: Update Rule Condition

**Change condition to handle null:**
```
IF custom_4821199e-7731-4485-a24c-f12dff321122 IS NOT NULL 
AND custom_4821199e-7731-4485-a24c-f12dff321122 > 0
THEN ...
```

**Backend Implementation:**
```php
// In EnterpriseRuleEngine condition evaluation
if ($actualValue === null || $actualValue === '') {
    return false; // Condition fails gracefully
}
```

---

### Fix 4: Frontend Field Capture

**Ensure frontend captures field value:**

In `WorkflowExecutionPage.tsx`:
```typescript
// Check if field is in step fields
const stepFields = useMemo(() => {
  return version.fields.filter(f => f.step_id === currentStep?.id);
}, [version.fields, currentStep]);

// Ensure all visible fields are rendered
{stepFields.filter(f => getFieldState(f).isVisible).map(field => (
  <div key={fieldKey(field)}>
    <input
      name={fieldKey(field)}
      value={values[fieldKey(field)] ?? ''}
      onChange={(e) => handleFieldChange(fieldKey(field), e.target.value)}
      // ...
    />
  </div>
))}
```

---

## 📋 VERIFICATION CHECKLIST

After applying fixes:

- [ ] Field is in correct step (Step 1)
- [ ] Field is visible in form
- [ ] Field has placeholder or label
- [ ] Field value changes on user input
- [ ] Field value is in submission payload
- [ ] Backend receives field value
- [ ] normalizeFieldKeys includes field in output_keys
- [ ] Rule condition evaluates with actual value (not null)
- [ ] Rule executes successfully

---

## 🎯 RECOMMENDED ACTION PLAN

### Immediate (Today):

1. **Check field step assignment:**
   ```sql
   SELECT step_id FROM workflow_fields WHERE id = '4821199e-7731-4485-a24c-f12dff321122';
   ```

2. **If field is in wrong step:** Move to Step 1 or update rule to reference correct step

3. **Make field required OR add default value:** Prevent null submissions

4. **Test with browser console:** Verify field renders and captures value

### Short-term (This Week):

1. Add null-check in condition evaluation
2. Add field validation (min value = 0)
3. Add user guidance (placeholder, help text)

### Long-term (Next Sprint):

1. Implement field dependency tracking
2. Add rule condition builder with field validation
3. Add execution trace with field value debugging

---

## 📊 CURRENT STATE SUMMARY

| Component | Status | Issue |
|-----------|--------|-------|
| Field Visibility | ✅ Visible | Not the problem |
| Field Editable | ✅ Editable | Not the problem |
| Field in Form | ❓ Unknown | Need to verify |
| Value Captured | ❌ NO | Value not in submission |
| Value Normalized | N/A | No value to normalize |
| Rule Executes | ❌ NO | Condition fails (null > 0 = false) |

---

**Next Step:** Check which step contains the field and verify frontend form rendering!
