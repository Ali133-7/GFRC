# RULE EXECUTION CONSISTENCY MATRIX

## Rule Types and Execution Paths

### 1. Simple Rules (workflow_rules, rule_type='simple')

| Stage | File | Line | Status | Notes |
|-------|------|------|--------|-------|
| Condition Match | `EnterpriseRuleEngine.php` | 224-259 | PASS | Uses `evaluateRule` → `evaluateConditions` → `evaluateConditionLogic` |
| Action Execution | `EnterpriseRuleEngine.php` | 230-244 | PASS | Actions converted via `convertWorkflowActions` before execution |
| Field Effects | `EnterpriseRuleEngine.php` | 718-1252 | PASS | Generated in `executeActions` switch |
| Financial Effects | `EnterpriseRuleEngine.php` | 895-986 | **PARTIAL** | set_fee works; apply_discount uses float |
| Execution State | `WorkflowExecutionService.php` | 256-282 | PASS | Effects transformed to legacy action format |
| Frontend Update | `WorkflowExecutionPage.tsx` | 157-178 | PASS | `modified_values` + `field_states` applied |

### 2. Case Rules (workflow_rules, rule_type='case_based')

| Stage | File | Line | Status | Notes |
|-------|------|------|--------|-------|
| Condition Match | `EnterpriseRuleEngine.php` | 112-168 | PASS | Trigger value matched against case values |
| Case Selection | `EnterpriseRuleEngine.php` | 119-168 | PASS | First matching case breaks; compound conditions supported |
| Action Execution | `EnterpriseRuleEngine.php` | 134 | PASS | Case actions converted via `convertWorkflowActions` |
| Default Fallback | `EnterpriseRuleEngine.php` | 172-202 | PASS | Default actions executed when no case matches |
| Field Effects | `EnterpriseRuleEngine.php` | 146 | PASS | From `execResult['field_effects']` |
| Financial Effects | `EnterpriseRuleEngine.php` | 135-137 | **PARTIAL** | Same float issues as simple rules |
| Frontend Update | `WorkflowExecutionPage.tsx` | 157-178 | PASS | Same path as simple rules |

### 3. Enterprise Rules (validation_rules with rule_config)

| Stage | File | Line | Status | Notes |
|-------|------|------|--------|-------|
| Condition Match | `EnterpriseRuleEngine.php` | 62-106 | PASS | Loaded from `validation_rules` table, ordered by priority DESC |
| Group Evaluation | `EnterpriseRuleEngine.php` | 517-537 | PASS | Unlimited nesting of AND/OR groups |
| Action Execution | `EnterpriseRuleEngine.php` | 323 | PASS | Actions in enterprise format `{type, field_id, value}` |
| Else Actions | `EnterpriseRuleEngine.php` | 337-346 | PASS | Executed when conditions don't match |
| Field Effects | `EnterpriseRuleEngine.php` | 325 | PASS | From `execResult['field_effects']` |
| Financial Effects | `EnterpriseRuleEngine.php` | 88-90 | **PARTIAL** | Same float issues |
| Conflict Resolution | `EnterpriseRuleEngine.php` | 103-105 | PASS | `first_match` breaks; `highest_priority` relies on ORDER BY |
| Frontend Update | `WorkflowExecutionPage.tsx` | 157-178 | PASS | Same path |

### 4. Validation Rules (execute_validation action)

| Stage | File | Line | Status | Notes |
|-------|------|------|--------|-------|
| Trigger | `EnterpriseRuleEngine.php` | 1184-1226 | PASS | `execute_validation` action type |
| Rule Lookup | `EnterpriseRuleEngine.php` | 1190-1196 | PASS | Finds rule in context `validation_rules` |
| Validation Run | `EnterpriseRuleEngine.php` | 1213-1214 | PASS | Delegates to `ValidationEngine::runValidation` |
| Block Check | `WorkflowExecutionService.php` | 222-239 | PASS | Error effects block; warnings flow through |
| Frontend Display | `WorkflowExecutionPage.tsx` | 764-792 | PASS | Via `BranchHandler` component |

### 5. Routing Rules (route_to_step, route_to_workflow, switch_mode)

| Stage | File | Line | Status | Notes |
|-------|------|------|--------|-------|
| Route Decision | `EnterpriseRuleEngine.php` | 1074-1100 | PASS | Returns routing decision in `executeActions` |
| Collection | `EnterpriseRuleEngine.php` | 91-97 | PASS | Added to `routingDecisions` array |
| Response | `WorkflowExecutionController.php` | 116 | PASS | `routing_decisions` in API response |
| Frontend Handler | `WorkflowExecutionPage.tsx` | 188-191 | PASS | `BranchHandler` component renders UI |
| Field Effect Application | `WorkflowExecutionPage.tsx` | 231-327 | PASS | `handleApplyFieldEffects` processes effects |

---

## Execution Order Anomaly

**File:** `backend/app/Services/EnterpriseRuleEngine.php:34-260`

Enterprise rules and workflow rules are processed in SEPARATE loops:

```
1. Enterprise rules (validation_rules with rule_config)
   ORDER BY priority DESC, sort_order ASC

2. Workflow rules (workflow_rules: simple + case_based)
   ORDER BY sort_order ASC
```

**Issue:** Enterprise rules ALWAYS execute before workflow rules, regardless of priority. A workflow rule with effective priority 10000 would execute AFTER an enterprise rule with priority 1.

**Impact:** If a workflow rule modifies a field value, and an enterprise rule's condition depends on that field, the enterprise rule sees the PRE-modification value (because it already executed).

---

## Condition Evaluation Inconsistency

**File:** `backend/app/Services/EnterpriseRuleEngine.php:576-586`

Numeric comparisons use `(float)` cast:
```php
case 'greater_than':
    return (float) $actualValue > (float) $expectedValue;
```

**File:** `backend/app/Services/RuleEngineV2.php:321-331`

BC Math comparison:
```php
protected function bcCompare(mixed $a, mixed $b): int
{
    $aStr = (string) $a;
    $bStr = (string) $b;
    if (is_numeric($aStr) && is_numeric($bStr)) {
        return bccomp($aStr, $bStr, $this->getContext()->scale());
    }
    return strcmp($aStr, $bStr);
}
```

**Issue:** `EnterpriseRuleEngine` uses float comparison for `gt`, `gte`, `lt`, `lte`, `between`. `RuleEngineV2` uses BC Math. For values near boundaries (e.g., `25000.0005` vs `25000.000`), the two engines could produce different results.

---

## Summary Matrix

| Rule Type | Conditions | Match | Actions | Field Effects | Financial | Execution State | Frontend |
|-----------|-----------|-------|---------|---------------|-----------|----------------|----------|
| Simple | PASS | PASS | PASS | PASS | **PARTIAL** | PASS | PASS |
| Case | PASS | PASS | PASS | PASS | **PARTIAL** | PASS | PASS |
| Enterprise | PASS | PASS | PASS | PASS | **PARTIAL** | PASS | PASS |
| Validation | PASS | PASS | PASS | PASS | N/A | PASS | PASS |
| Routing | PASS | PASS | PASS | PASS | N/A | PASS | PASS |

**PARTIAL** = Float arithmetic violations in financial actions (apply_discount, calculate)
