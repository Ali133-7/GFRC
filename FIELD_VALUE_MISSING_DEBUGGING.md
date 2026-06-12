# FIELD VALUE MISSING - DEBUGGING GUIDE

**Date:** 2026-06-10  
**Issue:** Rule condition field value is null  
**Status:** 🔧 **FIX APPLIED**

---

## 🐛 PROBLEM

**Rule:** "احتساب قيمة السجلات"  
**Condition:** `custom_4821199e-7731-4485-a24c-f12dff321122 > 0`  
**Actual Value:** `null` ❌  
**Result:** Rule SKIPPED

---

## ✅ FIX APPLIED

### Backend: Null Value Handling

**File:** `backend/app/Services/EnterpriseRuleEngine.php`

**Change:**
```php
// Before evaluating condition, check for null/empty
if ($actualValue === null || $actualValue === '') {
    \Log::debug('EnterpriseRuleEngine: condition field value is null/empty', [
        'field_id' => $fieldId,
        'operator' => $operator,
        'rule_name' => $context['rule_name'] ?? 'unknown',
    ]);
    
    // Numeric comparisons fail safely on null
    if (in_array($operator, ['greater_than', 'greater_or_equal', 'less_than', 'less_or_equal'])) {
        return false; // Condition NOT met
    }
}
```

**Effect:**
- ✅ Null values handled gracefully (no crash)
- ✅ Debug log shows which field is null
- ✅ Condition fails safely instead of causing error

---

## 🔍 DEBUGGING STEPS

### Step 1: Check Field Step Assignment

**SQL Query:**
```sql
SELECT 
    wf.id,
    wf.register_field_id,
    wf.step_id,
    ws.title_ar as step_title,
    wf.is_visible,
    wf.is_required
FROM workflow_fields wf
LEFT JOIN workflow_steps ws ON wf.step_id = ws.id
WHERE wf.register_field_id = '4821199e-7731-4485-a24c-f12dff321122'
   OR wf.id = '4821199e-7731-4485-a24c-f12dff321122';
```

**Expected Result:**
```
step_id: [Step 1 UUID]
step_title: "البيانات الأساسية"
is_required: 1 (or 0)
```

**If step_id is Step 2 or higher:** Field is in wrong step!

---

### Step 2: Check Frontend Form Rendering

**Browser Console:**
```javascript
// 1. Check if field exists in DOM
const field = document.querySelector('input[name="custom_4821199e-7731-4485-a24c-f12dff321122"]');
console.log('Field element:', field);

// 2. Check field value
if (field) {
    console.log('Field value:', field.value);
    console.log('Field visible:', field.offsetParent !== null);
    console.log('Field disabled:', field.disabled);
}

// 3. Check all form fields
const allInputs = document.querySelectorAll('input[name^="custom_"], input[name^="f1a6"], input[name^="db45"]');
console.log('All form fields:');
allInputs.forEach(input => {
    console.log(`  ${input.name} = ${input.value}`);
});
```

---

### Step 3: Check Form Submission

**Browser Network Tab:**

1. Open DevTools → Network tab
2. Submit the form
3. Find PUT request to `/workflow-executions/{id}/step`
4. Click request → Payload tab
5. Check `values` object

**Expected:**
```json
{
  "step_index": 0,
  "values": {
    "custom_4821199e-7731-4485-a24c-f12dff321122": "5",
    "999e0856-f2cd-4d50-a6f3-86038c07a293": "5000",
    ...
  }
}
```

**If `custom_4821199e-7731-4485-a24c-f12dff321122` is MISSING:**
- Field not rendered in form, OR
- Field value not captured, OR
- Field name mismatch

---

### Step 4: Check Backend Logs

**Command:**
```bash
tail -f backend/storage/logs/laravel.log | grep -i "null\|empty\|4821199e"
```

**Expected Log Entry:**
```
[2026-06-10 HH:MM:SS] local.DEBUG: EnterpriseRuleEngine: condition field value is null/empty
{"field_id":"custom_4821199e-7731-4485-a24c-f12dff321122","operator":"greater_than","rule_name":"احتساب قيمة السجلات"}
```

---

## 📋 SOLUTIONS

### Solution 1: Make Field Required ⭐ RECOMMENDED

**In Workflow Designer:**
1. Open workflow
2. Go to Fields tab
3. Find "سجلات الدلالين" field
4. Check "إلزامي" (Required)
5. Save workflow

**Effect:** User MUST enter value before proceeding

---

### Solution 2: Add Default Value

**In Workflow Designer:**
1. Open workflow
2. Go to Fields tab
3. Find "سجلات الدلالين" field
4. Set "القيمة الافتراضية" = `0`
5. Save workflow

**Effect:** Field always has value, even if user doesn't enter one

---

### Solution 3: Update Rule Condition

**In Rule Builder:**
1. Open rule "احتساب قيمة السجلات"
2. Change condition from:
   ```
   سجلات الدلالين > 0
   ```
   To:
   ```
   سجلات الدلالين IS NOT EMPTY
   AND
   سجلات الدلالين > 0
   ```

**Effect:** Rule only evaluates if field has value

---

### Solution 4: Move Field to Correct Step

**If field is in Step 2+:**
1. Open workflow
2. Go to Fields tab
3. Find "سجلات الدلالين" field
4. Change "الخطوة" to Step 1
5. Save workflow

**Effect:** Field appears in correct step form

---

## 🧪 TESTING CHECKLIST

After applying fix:

- [ ] Field is in Step 1 (or correct step)
- [ ] Field is visible in form
- [ ] Field has placeholder/label
- [ ] Field value changes when user types
- [ ] Field value appears in Network tab payload
- [ ] Backend log shows field value (not null)
- [ ] Rule condition evaluates with actual value
- [ ] Rule executes successfully (MATCH not SKIP)
- [ ] Calculated value appears in Calculated Items

---

## 📊 CURRENT STATUS

| Component | Status | Action |
|-----------|--------|--------|
| Backend Null Handling | ✅ Fixed | Logs null values |
| Field Step Assignment | ❓ Unknown | Check SQL |
| Frontend Rendering | ❓ Unknown | Check Console |
| Form Submission | ❓ Unknown | Check Network |
| Rule Execution | ❌ Fails | Waits for fix |

---

## 🎯 NEXT STEPS

1. **Run SQL query** to check field step
2. **Open browser console** to check field rendering
3. **Check Network tab** for submission payload
4. **Check Laravel logs** for null value warnings
5. **Apply appropriate solution** (1-4 above)
6. **Test again** and verify rule executes

---

**Status:** 🔧 **FIX APPLIED - AWAITING VERIFICATION**
