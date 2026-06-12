# CALCULATE ACTION FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** Complete pipeline trace from Builder → API → Database → Rule Engine → Expression Evaluator → Runtime → UI

---

## EXECUTIVE SUMMARY

The Calculate action is **currently unusable** for business users due to:
1. No field reference browser
2. No formula syntax documentation
3. No validation or preview
4. Ambiguous field identifier requirements
5. Generic "value" textbox with no guidance

**Status:** ❌ **CRITICAL - Requires Complete Redesign**

---

## 1. CURRENT IMPLEMENTATION AUDIT

### 1.1 Builder → API Payload

**Current UI (EnterpriseRuleBuilder.tsx:534-549):**
```tsx
{act.type === "calculate" && (
  <>
    <select value={act.field_id}>
      <option>اختر الحقل...</option>
      {fields.map(f => <option value={fieldKey(f)}>{fieldDisplayLabel(f)}</option>)}
    </select>
    <input value={act.value} placeholder="القيمة..." />
  </>
)}
```

**Issues:**
- ❌ Generic "value" textbox with no label
- ❌ No indication that this should be a formula
- ❌ No field reference browser
- ❌ No syntax guidance
- ❌ No validation
- ❌ No preview

**Current Payload Format:**
```json
{
  "type": "calculate",
  "field_id": "broker-records-uuid",
  "value": "{{records_count}} * 25000"
}
```

### 1.2 Database Storage

**Table:** `workflow_rules`
**Column:** `actions` (JSONB)

**Stored Format:**
```json
{
  "id": "action-uuid",
  "action": "calculate",
  "target_field_id": "goods-for-sale-uuid",
  "value": "{{records_count}} * 25000",
  "formula": "{{records_count}} * 25000"
}
```

**Issues:**
- ✅ JSONB allows flexible structure
- ⚠️ No validation at database level
- ⚠️ No schema enforcement

### 1.3 Rule Engine Processing

**File:** `EnterpriseRuleEngine.php:764-786`

```php
case 'calculate':
    if ($fieldId) {
        $formula = $value ?? $action['formula'] ?? null;
        if (!$formula) break;
        $calculated = $this->calculateExpression((string) $formula, $finalValues);
        $finalValues[$fieldId] = (string) $calculated;
        $fieldEffects[] = [
            'field_id' => $fieldId,
            'action' => 'calculate',
            'formula' => $value,
            'result' => $calculated,
        ];
    }
    break;
```

**Processing Flow:**
1. Extract formula from `action.value` or `action.formula`
2. Call `calculateExpression(formula, finalValues)`
3. Delegate to `FeeEngine::calculate()`
4. Store result in `finalValues[$fieldId]`
5. Record in `fieldEffects` and `financialTrace`

### 1.4 Expression Evaluator

**File:** `FeeEngine.php:163-182`

**Syntax Supported:**
```php
// Replace {{field_id}} placeholders
$expression = preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
    $key = $matches[1];
    $value = $values[$key] ?? '0';
    return $this->toDecimalString($value);
}, $expression);
```

**Supported Syntax:**
| Feature | Syntax | Example | Supported? |
|---------|--------|---------|------------|
| Field reference | `{{field_id}}` | `{{broker-records-uuid}}` | ✅ |
| Field reference | `{{register_field_id}}` | `{{records_count}}` | ✅ |
| Addition | `+` | `{{a}} + {{b}}` | ✅ |
| Subtraction | `-` | `{{a}} - {{b}}` | ✅ |
| Multiplication | `*` | `{{a}} * 25000` | ✅ |
| Division | `/` | `{{a}} / 100` | ✅ |
| Decimal numbers | `123.45` | `{{a}} * 123.45` | ✅ |
| Negative numbers | `-123` | `{{a}} + (-123)` | ✅ |
| Parentheses | `()` | `({{a}} + {{b}}) * 2` | ✅ |
| Fee reference | `fee_{{code}}` | `fee_{{REGISTRATION}}` | ✅ |

**Unsupported Syntax:**
| Feature | Example | Supported? |
|---------|---------|------------|
| Field names | `records_count * 25000` | ❌ |
| Functions | `max({{a}}, {{b}})` | ❌ |
| Powers | `{{a}}^2` | ❌ |
| Modulo | `{{a}} % 2` | ❌ |
| Variables | `a * b` (without {{}}) | ❌ |

### 1.5 Field Resolution

**Current Behavior:**
```php
// EnterpriseRuleEngine::executeActions
$fieldId = $action['field_id'] ?? null;

// WorkflowExecutionService::applySetValueActions
$targetId = $action['target_field_id'] ?? null;

// FeeEngine::prepareExpression
// Replaces {{field_id}} with values[$key]
```

**Field Identifier Formats Accepted:**

| Format | Example | Works? | Notes |
|--------|---------|--------|-------|
| UUID | `{{a1b2c3d4-e5f6-...}}` | ✅ | Workflow field PK |
| register_field_id | `{{records_count}}` | ✅ | Preferred format |
| custom_<id> | `{{custom_a1b2c3}}` | ✅ | For custom fields |
| Field name | `{{records_count}}` | ⚠️ | Only if matches register_field_id |

**Field Key Resolution (WorkflowExecutionService:946-973):**
```php
protected function normalizeFieldKeys(array $values, $fields): array
{
    foreach ($fields as $field) {
        $canonical = $field->register_field_id ?? 'custom_'.$field->id;
        $aliases = [$field->id, 'custom_'.$field->id];
        if (!empty($field->register_field_id)) {
            $aliases[] = $field->register_field_id;
        }
        
        // All aliases map to same value
        $normalized[$canonical] = $bestValue;
        foreach ($aliases as $alias) {
            $normalized[$alias] = $bestValue;
        }
    }
    return $normalized;
}
```

**Resolution Chain:**
```
User input: {{records_count}}
    ↓
Rule engine receives values with multiple keys:
  - records_count: 3
  - a1b2c3d4-uuid: 3
  - custom_a1b2c3: 3
    ↓
FeeEngine replaces {{records_count}} with "3.0"
    ↓
Expression: "3.0 * 25000"
    ↓
Result: "75000.000"
```

### 1.6 Runtime Execution Trace

**Example Scenario:**
```
Fields:
  - broker_records (register_field_id: "records_count", type: number)
  - goods_for_sale (register_field_id: "goods_total", type: decimal)

Rule:
  - Action: calculate
  - target_field_id: goods_total
  - formula: {{records_count}} * 25000

User Input:
  - records_count: 3

Execution:
  1. values = { records_count: "3", goods_total: "10000" }
  2. formula = "{{records_count}} * 25000"
  3. FeeEngine::prepareExpression() → "3.0 * 25000.0"
  4. FeeEngine::evaluateExpression() → "75000.000"
  5. finalValues[goods_total] = "75000.000"
  6. fieldEffects = [{ field_id: "goods_total", action: "calculate", result: "75000.000" }]
```

---

## 2. CRITICAL FINDINGS

### 2.1 Field Identifier Confusion

**Problem:** Users don't know which format to use.

**Current State:**
| Context | Format Used |
|---------|-------------|
| Builder UI | `fieldKey(f)` = `register_field_id ?? custom_<id>` |
| Formula syntax | `{{field_id}}` (any alias works) |
| Database | Stores `target_field_id` as `register_field_id ?? custom_<id>` |
| Runtime | Accepts all aliases (UUID, register_field_id, custom_<id>) |

**Impact:** Users may try field names, UUIDs, or other formats that may or may not work.

### 2.2 No Formula Validation

**Problem:** Formulas are not validated before saving.

**Current State:**
- ❌ No syntax validation in builder
- ❌ No field existence check
- ❌ No preview of calculation
- ❌ Errors only appear at runtime

**Example Failure:**
```
User enters: records_count * 25000  (missing {{}})
Builder: ✅ Accepts (no validation)
Database: ✅ Saves
Runtime: ❌ Fails - "records_count" not found in values
Error: "Invalid expression"
```

### 2.3 No Field Reference Browser

**Problem:** Users must manually type field identifiers.

**Current State:**
- ❌ No list of available fields
- ❌ No field type indicators
- ❌ No click-to-insert functionality
- ❌ Users must remember or copy-paste field IDs

### 2.4 No Formula Preview

**Problem:** Users cannot test formulas before saving.

**Current State:**
- ❌ No test value input
- ❌ No live preview
- ❌ No result calculation
- ❌ Users must save and run workflow to test

### 2.5 Chained Calculations

**Problem:** Can calculate actions reference values from previous actions?

**Current State:**
```php
// EnterpriseRuleEngine::executeActions
foreach ($actions as $action) {
    // Actions execute sequentially
    // $finalValues is updated after each action
    // Subsequent actions can reference previous results
}
```

**Answer:** ✅ **YES** - Chained calculations are supported.

**Example:**
```
Action 1: calculate goods_total = {{records_count}} * 25000
Action 2: calculate tax = {{goods_total}} * 0.15
Action 3: calculate final = {{goods_total}} + {{tax}}
```

### 2.6 Financial Determinism

**Problem:** Are calculations deterministic?

**Current State:**
- ✅ FeeEngine uses Shunting-Yard algorithm
- ✅ All arithmetic uses BC Math (bcadd, bcmul, bcsub, bcdiv)
- ✅ No float arithmetic
- ✅ Configurable scale (default: 3 decimal places)
- ✅ Configurable rounding mode (default: HALF_UP)

**Answer:** ✅ **YES** - Financial calculations are fully deterministic.

---

## 3. REQUIRED IMPLEMENTATION

### 3.1 Formula Assistant UI

**Location:** `frontend/src/components/validation/EnterpriseRuleBuilder.tsx`

**Requirements:**
When action type = calculate, display:
1. Target Field selector
2. Formula Editor with syntax highlighting
3. Available Fields Panel
4. Available Operators Panel
5. Formula Validation button
6. Preview panel

### 3.2 Runtime Formula Validation

**Location:** `backend/app/Services/EnterpriseRuleEngine.php`

**Requirements:**
- Validate formula syntax before saving
- Check field existence
- Test evaluation with sample values
- Return detailed error messages

### 3.3 Formula Preview

**Location:** `frontend/src/components/validation/EnterpriseRuleBuilder.tsx`

**Requirements:**
- Input test values for fields
- Calculate preview result
- Show step-by-step evaluation
- Display errors clearly

### 3.4 Field Reference Browser

**Location:** `frontend/src/components/validation/EnterpriseRuleBuilder.tsx`

**Requirements:**
- Display all available fields
- Show field label, key, and type
- Click to insert into formula
- Filter by type (number, decimal, financial)
- Search functionality

---

## 4. EXECUTION EXAMPLES

### 4.1 Simple Calculation

**Formula:** `{{records_count}} * 25000`

**Fields:**
- records_count (number) = 3

**Evaluation:**
```
1. Replace placeholders: "3.0 * 25000.0"
2. Tokenize: [NUMBER:3.0, OPERATOR:*, NUMBER:25000.0]
3. RPN: [3.0, 25000.0, *]
4. Evaluate: 75000.000
```

**Result:** `75000.000`

### 4.2 Complex Calculation

**Formula:** `({{goods_total}} - {{discount}}) * (1 + {{tax_rate}})`

**Fields:**
- goods_total (decimal) = 100000
- discount (decimal) = 10000
- tax_rate (decimal) = 0.15

**Evaluation:**
```
1. Replace: "(100000.0 - 10000.0) * (1.0 + 0.15)"
2. Simplify: "90000.0 * 1.15"
3. Result: 103500.000
```

**Result:** `103500.000`

### 4.3 Fee Reference

**Formula:** `{{records_count}} * fee_{{REGISTRATION}}`

**Fields:**
- records_count (number) = 2
- Fee REGISTRATION = 50000

**Evaluation:**
```
1. Replace fees: "{{records_count}} * 50000.0"
2. Replace fields: "2.0 * 50000.0"
3. Result: 100000.000
```

**Result:** `100000.000`

### 4.4 Chained Actions

**Rule Actions:**
```json
[
  {
    "type": "calculate",
    "field_id": "subtotal",
    "value": "{{quantity}} * {{unit_price}}"
  },
  {
    "type": "calculate",
    "field_id": "tax",
    "value": "{{subtotal}} * 0.15"
  },
  {
    "type": "calculate",
    "field_id": "total",
    "value": "{{subtotal}} + {{tax}}"
  }
]
```

**Input:**
- quantity = 5
- unit_price = 10000

**Execution:**
```
Action 1: subtotal = 5.0 * 10000.0 = 50000.000
Action 2: tax = 50000.0 * 0.15 = 7500.000
Action 3: total = 50000.0 + 7500.0 = 57500.000
```

**Final Values:**
- subtotal: 50000.000
- tax: 7500.000
- total: 57500.000

---

## 5. FIELD RESOLUTION EXAMPLES

### 5.1 UUID Reference

**Formula:** `{{a1b2c3d4-e5f6-7890-abcd-ef1234567890}} * 25000`

**Works:** ✅ Yes

**Recommended:** ❌ No - UUIDs are hard to read and maintain

### 5.2 register_field_id Reference

**Formula:** `{{records_count}} * 25000`

**Works:** ✅ Yes

**Recommended:** ✅ **YES** - This is the preferred format

### 5.3 custom_<id> Reference

**Formula:** `{{custom_a1b2c3}} * 25000`

**Works:** ✅ Yes

**Recommended:** ✅ For custom fields (no register_field_id)

### 5.4 Field Name (Not Recommended)

**Formula:** `records_count * 25000`

**Works:** ❌ No - Missing {{}} brackets

**Error:** "Invalid expression: undefined variable 'records_count'"

---

## 6. FINANCIAL EXAMPLES

### 6.1 Broker Records Calculation

**Scenario:** Calculate broker fees based on record count

**Formula:** `{{broker_records}} * 50000`

**Input:**
- broker_records = 3

**Result:** `150000.000`

### 6.2 Tiered Pricing

**Scenario:** Different price per unit based on quantity

**Formula:**
```
{{quantity}} * ({{quantity}} > 100 ? 50000 : 60000)
```

**Supported:** ❌ No - Ternary operator not supported

**Alternative:** Use case-based rules instead

### 6.3 Percentage Calculation

**Scenario:** Calculate 15% tax

**Formula:** `{{subtotal}} * 0.15`

**Input:**
- subtotal = 100000

**Result:** `15000.000`

### 6.4 Discount Application

**Scenario:** Apply 10% discount then add tax

**Formula:** `{{subtotal}} * 0.9 * 1.15`

**Input:**
- subtotal = 100000

**Steps:**
```
1. 100000.0 * 0.9 = 90000.000 (after discount)
2. 90000.0 * 1.15 = 103500.000 (with tax)
```

**Result:** `103500.000`

---

## 7. RECOMMENDATIONS

### 7.1 Immediate Actions (Critical)

1. **Add Formula Assistant UI** - Business users cannot use calculate without it
2. **Add Field Reference Browser** - Users must not guess field identifiers
3. **Add Formula Validation** - Prevent invalid formulas from being saved
4. **Add Formula Preview** - Allow testing before deployment

### 7.2 Syntax Improvements

1. **Support field names** - Allow `records_count` without {{}} for simplicity
2. **Add functions** - Support `min()`, `max()`, `round()`, `abs()`
3. **Add comparison operators** - Support `>`, `<`, `>=`, `<=` in formulas
4. **Add ternary operator** - Support conditional calculations

### 7.3 Documentation

1. **Inline help** - Show syntax examples in the builder
2. **Tooltips** - Explain each operator
3. **Error messages** - Clear, actionable error messages
4. **Examples library** - Pre-built formula templates

---

## 8. CONCLUSION

**Current Status:** ❌ **UNUSABLE for business users**

**Root Cause:** Calculate action requires technical knowledge of:
- Field identifier formats (UUID vs register_field_id vs custom_<id>)
- Formula syntax ({{}} placeholders)
- Supported operators (+, -, *, /)
- BC Math precision behavior

**Solution:** Complete redesign with:
1. Formula Assistant UI
2. Field Reference Browser
3. Formula Validation
4. Formula Preview
5. Better error messages

**Priority:** 🔴 **CRITICAL** - Blocks business users from creating calculations

---

## APPENDIX A: SUPPORTED SYNTAX REFERENCE

### Operators
| Operator | Symbol | Example | Result |
|----------|--------|---------|--------|
| Addition | `+` | `{{a}} + {{b}}` | Sum |
| Subtraction | `-` | `{{a}} - {{b}}` | Difference |
| Multiplication | `*` | `{{a}} * 5` | Product |
| Division | `/` | `{{a}} / 2` | Quotient |
| Negation | `-` | `-{{a}}` | Negative |
| Grouping | `()` | `({{a}} + {{b}}) * 2` | Ordered evaluation |

### Field References
| Format | Example | Recommended |
|--------|---------|-------------|
| register_field_id | `{{records_count}}` | ✅ Yes |
| UUID | `{{a1b2c3d4-...}}` | ⚠️ Avoid |
| custom_<id> | `{{custom_a1b2}}` | ✅ For custom fields |

### Numeric Literals
| Type | Example | Valid |
|------|---------|-------|
| Integer | `100` | ✅ |
| Decimal | `123.45` | ✅ |
| Negative | `-50` | ✅ |
| Scientific | `1e5` | ❌ |

---

## APPENDIX B: RUNTIME TRACE EXAMPLE

**Rule Configuration:**
```json
{
  "name": "Calculate Broker Fees",
  "actions": [
    {
      "type": "calculate",
      "field_id": "goods_total",
      "value": "{{broker_records}} * 50000"
    }
  ]
}
```

**Runtime Values:**
```json
{
  "broker_records": "3",
  "goods_total": "10000"
}
```

**Execution Trace:**
```
1. EnterpriseRuleEngine::executeActions()
   - action.type = "calculate"
   - action.field_id = "goods_total"
   - action.value = "{{broker_records}} * 50000"

2. calculateExpression("{{broker_records}} * 50000", values)
   - Delegates to FeeEngine::calculate()

3. FeeEngine::prepareExpression()
   - Regex: /{{([\w-]+)}}/
   - Match: "broker_records"
   - Replace with: "3.0"
   - Result: "3.0 * 50000.0"

4. FeeEngine::evaluateExpression()
   - Tokenize: [NUMBER:3.0, OPERATOR:*, NUMBER:50000.0]
   - RPN: [3.0, 50000.0, *]
   - Evaluate: bcmul("3.0", "50000.0", 3) = "150000.000"

5. EnterpriseRuleEngine::executeActions()
   - finalValues["goods_total"] = "150000.000"
   - fieldEffects[] = { field_id: "goods_total", result: "150000.000" }
   - financialTrace[] = { formula: "{{broker_records}} * 50000", result: "150000.000" }
```

**Final State:**
```json
{
  "broker_records": "3",
  "goods_total": "150000.000"
}
```
