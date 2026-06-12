# FORMULA FIELD ID CHANGING - DEBUGGING GUIDE

**Issue:** Field IDs in formulas change after save/reload

**Symptom:**
- Create rule with formula: `{{21156cb0-...}} + ({{custom_382ec189-...}}* 50000)`
- Save rule
- Reopen rule
- Formula changed to: `{{21156cb0-...}} + ({{custom_6dfce350-...}}* 50000)` ❌

The field ID `custom_382ec189-...` changed to `custom_6dfce350-...`!

---

## 🔍 ROOT CAUSE INVESTIGATION

### Possible Causes:

1. **Field IDs are not stable** - Workflow fields get new IDs on each save
2. **register_field_id is changing** - The register field reference changes
3. **Fields array is reloaded differently** - Different field objects on reload
4. **Formula is being modified on save** - Backend or frontend is changing the formula

---

## 🧪 DEBUGGING STEPS

### Step 1: Open Browser Console

1. Open the workflow designer page
2. Press F12 to open Developer Tools
3. Go to Console tab
4. Clear console

### Step 2: Create/Edit Rule with Formula

1. Click on "قاعدة متقدمة" (Advanced Rule)
2. Add a calculate action
3. Click on fields to insert them into the formula

### Step 3: Check Console Output

You should see logs like:

```
[ENTERPRISE RULE BUILDER] Rule loaded: {...}
[ENTERPRISE RULE BUILDER] Fields count: 5
[ENTERPRISE RULE BUILDER] Fields: [{...}, {...}]
[FORMULA ASSISTANT] Building fields list, input fields count: 5
[FORMULA ASSISTANT] Field mapped: {...}
[FORMULA ASSISTANT] Available fields count: 2
```

### Step 4: Save the Rule

1. Click "حفظ القاعدة" (Save Rule)
2. Check console for any errors

### Step 5: Reopen the Rule

1. Close the rule editor
2. Click on the same rule to edit it again
3. Check console output AGAIN

### Step 6: Compare Field IDs

**CRITICAL:** Compare these between Step 3 and Step 5:

```
[ENTERPRISE RULE BUILDER] Fields: [...]
```

Look for:
- `workflow_field_id` - Should be the SAME
- `register_field_id` - Should be the SAME  
- `fieldKey` - Should be the SAME

**If ANY of these changed, that's the bug!**

---

## 📋 INFORMATION TO COLLECT

Please provide the following console output:

### Before Save (Initial Creation):
```
[ENTERPRISE RULE BUILDER] Fields: [
  {
    workflow_field_id: "XXX",
    register_field_id: "YYY",
    fieldKey: "custom_XXX" or "YYY",
    label: "..."
  },
  ...
]
```

### After Save (Reopened):
```
[ENTERPRISE RULE BUILDER] Fields: [
  {
    workflow_field_id: "AAA",  ← Did this change?
    register_field_id: "BBB",  ← Did this change?
    fieldKey: "custom_AAA" or "BBB",  ← Did this change?
    label: "..."
  },
  ...
]
```

### Formula Value:
```
[FORMULA ASSISTANT] Current action.value: "{{...}} + ({{...}}* 50000)"
```

---

## 🎯 EXPECTED BEHAVIOR

Field IDs should be **STABLE**:

| Field Property | Should Change? |
|---------------|----------------|
| workflow_field_id (f.id) | ❌ NO - UUID is permanent |
| register_field_id | ❌ NO - Set once, never changes |
| fieldKey (register_field_id ?? custom_id) | ❌ NO - Derived from stable values |

**If any of these change, it's a DATA INTEGRITY BUG.**

---

## 🔧 LIKELY CAUSES

### 1. Workflow Fields Are Being Recreated

**Symptom:** `workflow_field_id` changes on each save

**Cause:** Backend is deleting and recreating fields instead of updating

**Fix:** Backend should UPDATE fields, not DELETE+CREATE

### 2. register_field_id Is Being Cleared

**Symptom:** `register_field_id` becomes null, switches to `custom_<id>`

**Cause:** Backend validation or migration is clearing register_field_id

**Fix:** Ensure register_field_id is preserved on updates

### 3. Fields Array Order Changed

**Symptom:** Same fields but different order

**Cause:** Fields not sorted consistently

**Fix:** Always sort by sort_order or ID

---

## 📝 NEXT STEPS

1. ✅ Run the debugging steps above
2. ✅ Collect console output (before and after save)
3. ✅ Compare field IDs
4. ✅ Report which IDs changed
5. ⏳ We'll fix the root cause based on findings

---

## 🚨 CRITICAL: DO NOT

- ❌ Do NOT manually edit formulas in database
- ❌ Do NOT delete and recreate fields
- ❌ Do NOT create new workflow versions for testing

This could make the bug worse or corrupt data.

---

**Status:** 🔍 **INVESTIGATING**  
**Priority:** 🔴 **CRITICAL**  
**Action Required:** Run debugging steps and provide console output
