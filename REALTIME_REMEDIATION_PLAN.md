# REAL-TIME RULE ENGINE - REMEDIATION PLAN

## Executive Summary

This document provides a detailed remediation plan to fix all critical architectural flaws identified in the Real-Time Rule Engine audit.

**Priority:** CRITICAL  
**Estimated Effort:** 2-3 weeks  
**Risk if not fixed:** Financial discrepancies, performance issues, potential infinite loops

---

## Phase 1: Financial Consistency (3-4 days)

### 1.1 Fix FinancialRecalculator to Use BC Math

**File:** `backend/app/Services/FinancialRecalculator.php`

**Current (WRONG):**
```php
foreach ($financialValues as $fieldId => $amount) {
    if (is_numeric($amount)) {
        $subtotal += (float) $amount;  // ❌ FLOAT
    }
}
$total = $subtotal - $discounts + $fees + $taxes + $insurance;  // ❌ FLOAT
```

**Fixed (CORRECT):**
```php
protected function recalculate(array $values, array $validationResults, array $workflowResults): array
{
    $financialValues = [];
    $fieldEffects = [];
    
    // Extract field effects
    foreach ($workflowResults as $result) {
        if (isset($result['field_effects']) && is_array($result['field_effects'])) {
            foreach ($result['field_effects'] as $effect) {
                $fieldEffects[] = $effect;
            }
        }
    }
    
    // Apply financial field effects
    foreach ($fieldEffects as $effect) {
        $action = $effect['action'] ?? null;
        $fieldId = $effect['field_id'] ?? null;
        $amount = $effect['amount'] ?? null;
        
        if ($fieldId && $amount) {
            switch ($action) {
                case 'set_fee':
                case 'calculate':
                case 'set_value':
                    // Ensure BC Math format
                    $financialValues[$fieldId] = $this->toDecimalString($amount);
                    break;
                
                case 'apply_discount':
                    $financialValues[$fieldId] = $this->toDecimalString($amount);
                    break;
            }
        }
    }
    
    // Recalculate totals using BC Math
    $subtotal = '0.000';
    $discounts = '0.000';
    $fees = '0.000';
    $taxes = '0.000';
    $insurance = '0.000';
    
    foreach ($financialValues as $fieldId => $amount) {
        // Classify amount type
        if (strpos($fieldId, 'discount') !== false) {
            $discounts = bcadd($discounts, (string) $amount, 3);
        } elseif (strpos($fieldId, 'fee') !== false) {
            $fees = bcadd($fees, (string) $amount, 3);
        } else {
            $subtotal = bcadd($subtotal, (string) $amount, 3);
        }
    }
    
    // Calculate total
    $total = bcadd(
        bcsub($subtotal, $discounts, 3),
        bcadd($fees, bcadd($taxes, $insurance, 3), 3),
        3
    );
    
    return [
        'financial_values' => $financialValues,
        'subtotal' => $subtotal,
        'discounts' => $discounts,
        'fees' => $fees,
        'taxes' => $taxes,
        'insurance' => $insurance,
        'total' => $total,
    ];
}

protected function toDecimalString(mixed $value): string
{
    if (is_string($value)) {
        return bcadd($value, '0', 3);
    }
    if (is_numeric($value)) {
        return bcadd((string) $value, '0', 3);
    }
    return '0.000';
}
```

**Tests Required:**
- [ ] Test BC Math precision (25000.000 + 5000.000 = 30000.000)
- [ ] Test financial consistency with main engine
- [ ] Test discount calculations
- [ ] Test total calculations

---

### 1.2 Unify Calculation Engine

**File:** `backend/app/Services/FinancialRecalculator.php`

**Approach:** Instead of duplicating calculation logic, delegate to existing engines.

```php
protected function recalculate(array $values, array $validationResults, array $workflowResults): array
{
    // Use existing WorkflowExecutionService calculation logic
    // This ensures real-time calculations match submission calculations
    
    $calculationService = app(WorkflowExecutionService::class);
    
    // Extract field effects and apply them
    $modifiedValues = $values;
    foreach ($workflowResults as $result) {
        if (isset($result['field_effects'])) {
            foreach ($result['field_effects'] as $effect) {
                if (isset($effect['amount']) && isset($effect['field_id'])) {
                    $modifiedValues[$effect['field_id']] = $effect['amount'];
                }
            }
        }
    }
    
    // Use existing calculateItems method
    $calculatedItems = $calculationService->calculateItems(
        $this->stepFields,
        $modifiedValues,
        $this->allActions,
        $this->allFields
    );
    
    // Sum items using BC Math
    $total = '0.000';
    foreach ($calculatedItems as $item) {
        $total = bcadd($total, $item['amount'], 3);
    }
    
    return [
        'financial_values' => $modifiedValues,
        'calculated_items' => $calculatedItems,
        'total' => $total,
    ];
}
```

---

## Phase 2: Performance Optimization (4-5 days)

### 2.1 Cache Dependency Graph

**File:** `backend/app/Services/DependencyResolver.php`

**Add:**
```php
use Illuminate\Support\Facades\Cache;

class DependencyResolver
{
    protected ?array $cachedGraph = null;
    protected ?string $cachedGraphVersion = null;
    
    public function buildGraph(Collection $validationRules, Collection $workflowRules): void
    {
        // Create cache key based on rule IDs and updated_at timestamps
        $ruleIds = $validationRules->pluck('id')->merge($workflowRules->pluck('id'))->sort()->implode('-');
        $ruleVersions = $validationRules->merge($workflowRules)
            ->sortBy('id')
            ->pluck('updated_at')
            ->implode('-');
        
        $cacheKey = "dependency_graph_{$ruleIds}_{$ruleVersions}";
        
        // Try to load from cache
        if ($this->cachedGraphVersion === $cacheKey && $this->cachedGraph !== null) {
            return; // Already built
        }
        
        // Try cache
        $cached = Cache::remember($cacheKey, 3600, function () use ($validationRules, $workflowRules) {
            $this->buildGraphInternal($validationRules, $workflowRules);
            return [
                'fieldToRules' => $this->fieldToRules,
                'ruleToFields' => $this->ruleToFields,
                'fieldDependencies' => $this->fieldDependencies,
            ];
        });
        
        $this->fieldToRules = $cached['fieldToRules'];
        $this->ruleToFields = $cached['ruleToFields'];
        $this->fieldDependencies = $cached['fieldDependencies'];
        $this->cachedGraphVersion = $cacheKey;
    }
    
    protected function buildGraphInternal(Collection $validationRules, Collection $workflowRules): void
    {
        // Existing build logic
    }
}
```

**Cache Invalidation:**
```php
// In ValidationRule and WorkflowRule models
protected static function boot()
{
    parent::boot();
    
    static::saved(function ($rule) {
        Cache::tags(['dependency_graph'])->flush();
    });
    
    static::deleted(function ($rule) {
        Cache::tags(['dependency_graph'])->flush();
    });
}
```

---

### 2.2 Cache Rules

**File:** `backend/app/Services/RealTimeRuleEngine.php`

**Add:**
```php
use Illuminate\Support\Facades\Cache;

public function execute(string $workflowVersionId, string $changedFieldId, array $values, string $executionId): array
{
    // Cache rules
    $cacheKey = "rules_{$workflowVersionId}";
    
    [$validationRules, $workflowRules] = Cache::remember($cacheKey, 300, function () use ($workflowVersionId) {
        return [
            ValidationRule::where('workflow_version_id', $workflowVersionId)
                ->where('is_active', true)
                ->get(),
            WorkflowRule::where('workflow_version_id', $workflowVersionId)
                ->where('is_active', true)
                ->get(),
        ];
    });
    
    // Rest of execution logic
}
```

---

### 2.3 Filter by realtime_enabled

**File:** `backend/app/Services/DependencyResolver.php`

**Fix:**
```php
public function getRealTimeAffectedRules(string $fieldId, Collection $validationRules, Collection $workflowRules): array
{
    $allAffected = $this->getAffectedRules($fieldId);
    
    // Get realtime_enabled rule IDs
    $realtimeValidationIds = $validationRules
        ->filter(fn($r) => $r->realtime_enabled)
        ->pluck('id')
        ->toArray();
    
    $realtimeWorkflowIds = $workflowRules
        ->filter(fn($r) => $r->realtime_enabled)
        ->pluck('id')
        ->toArray();
    
    $realtimeIds = array_merge($realtimeValidationIds, $realtimeWorkflowIds);
    
    // Filter to only realtime_enabled rules
    return array_values(array_intersect($allAffected, $realtimeIds));
}
```

---

## Phase 3: Execution Correctness (4-5 days)

### 3.1 Support Case Rules

**File:** `backend/app/Services/RealTimeRuleEngine.php`

**Fix:**
```php
foreach ($realtimeWorkflowRules as $rule) {
    $ruleConfig = [
        'conditions' => $rule->condition_logic ? [$rule->condition_logic] : [],
        'actions' => $rule->actions ?? [],
        'cases' => $rule->cases ?? [],  // ✅ PASS CASES
        'else_actions' => $rule->default_actions ?? [],  // ✅ PASS ELSE ACTIONS
    ];
    
    $result = $this->enterpriseEngine->evaluateRule(
        $rule->id,
        $rule->name,
        $rule->rule_type,
        $ruleConfig['conditions'],
        $ruleConfig['actions'],
        $ruleConfig['else_actions'],
        $ruleConfig['cases'],  // ✅ PASS CASES
        $values,
        $values,
        [],
        []
    );
    
    $workflowResults[] = $result;
}
```

---

### 3.2 Implement Cascading Execution

**File:** `backend/app/Services/RealTimeRuleEngine.php`

**Add:**
```php
public function execute(string $workflowVersionId, string $changedFieldId, array $values, string $executionId): array
{
    $this->executionStateManager->startEvaluation($executionId);
    
    // Load rules
    $validationRules = $this->getValidationRules($workflowVersionId);
    $workflowRules = $this->getWorkflowRules($workflowVersionId);
    
    // Build dependency graph
    $this->dependencyResolver->buildGraph($validationRules, $workflowRules);
    
    // Multi-pass execution until stable
    $maxIterations = 10;  // Prevent infinite loops
    $iteration = 0;
    $hasChanges = true;
    $currentValues = $values;
    
    while ($hasChanges && $iteration < $maxIterations) {
        $iteration++;
        $previousValues = $currentValues;
        
        // Get affected rules
        $affectedRuleIds = $this->dependencyResolver->getAffectedRules($changedFieldId);
        $realtimeValidationRules = $validationRules->filter(fn($r) => $r->realtime_enabled && in_array($r->id, $affectedRuleIds));
        $realtimeWorkflowRules = $workflowRules->filter(fn($r) => $r->realtime_enabled && in_array($r->id, $affectedRuleIds));
        
        // Execute rules
        $validationResults = [];
        foreach ($realtimeValidationRules as $rule) {
            $result = $this->validationEngine->runValidation($rule, $currentValues, []);
            $validationResults[] = $result;
        }
        
        $workflowResults = [];
        foreach ($realtimeWorkflowRules as $rule) {
            $result = $this->evaluateWorkflowRule($rule, $currentValues);
            $workflowResults[] = $result;
            
            // Apply field effects to current values for next iteration
            if (isset($result['field_effects'])) {
                foreach ($result['field_effects'] as $effect) {
                    if (isset($effect['amount']) && isset($effect['field_id'])) {
                        $currentValues[$effect['field_id']] = $effect['amount'];
                    }
                }
            }
        }
        
        // Check if values changed
        $hasChanges = json_encode($previousValues) !== json_encode($currentValues);
    }
    
    if ($iteration >= $maxIterations) {
        throw new \RuntimeException('Maximum execution iterations reached - possible infinite loop');
    }
    
    // Set state to CALCULATING
    $this->executionStateManager->startCalculation($executionId);
    
    // Recalculate financials with final values
    $financialResults = $this->financialRecalculator->recalculate($currentValues, $validationResults, $workflowResults);
    
    // Set state to READY
    $this->executionStateManager->markReady($executionId);
    
    return [
        'success' => true,
        'validation_results' => $validationResults,
        'workflow_results' => $workflowResults,
        'financial_results' => $financialResults,
        'affected_rule_count' => count($realtimeValidationRules) + count($realtimeWorkflowRules),
        'iterations' => $iteration,
    ];
}
```

---

### 3.3 Add Runtime Loop Protection

**File:** `backend/app/Services/RealTimeRuleEngine.php`

**Add:**
```php
protected function executeWithLoopProtection(string $workflowVersionId, string $changedFieldId, array $values, string $executionId): array
{
    $maxIterations = 10;
    $iteration = 0;
    $valueHistory = [];
    
    while ($iteration < $maxIterations) {
        $iteration++;
        
        // Execute rules
        $result = $this->executeInternal($workflowVersionId, $changedFieldId, $values, $executionId);
        
        // Check for value oscillation
        $valueSignature = json_encode($result['financial_results']['financial_values'] ?? []);
        
        if (in_array($valueSignature, $valueHistory)) {
            throw new \RuntimeException('Infinite loop detected - values oscillating');
        }
        
        $valueHistory[] = $valueSignature;
        
        // If no changes, we're done
        if (!$result['has_changes']) {
            break;
        }
        
        $values = $result['updated_values'];
    }
    
    if ($iteration >= $maxIterations) {
        throw new \RuntimeException('Maximum execution iterations reached');
    }
    
    return $result;
}
```

---

## Phase 4: Concurrency & Determinism (3-4 days)

### 4.1 Add Request Cancellation

**File:** `frontend/src/hooks/useRealTimeRules.ts`

**Add:**
```typescript
export function useRealTimeRules(options: UseRealTimeRulesOptions): UseRealTimeRulesReturn {
  const [abortController, setAbortController] = useState<AbortController | null>(null);
  
  const execute = useCallback(async (fieldId: string, value: any, values: Record<string, any>) => {
    // Cancel previous request
    if (abortController) {
      abortController.abort();
    }
    
    const controller = new AbortController();
    setAbortController(controller);
    
    try {
      // ... debounce logic ...
      
      const result = await workflowExecutionApi.executeRealTime(
        executionId, fieldId, value, values,
        { signal: controller.signal }
      );
      
      // ... handle result ...
    } catch (err: any) {
      if (err.name === 'AbortError') {
        return; // Request was cancelled, ignore
      }
      // ... handle error ...
    }
  }, [/* dependencies */]);
  
  return { /* ... */ };
}
```

---

### 4.2 Ensure Deterministic Execution

**File:** `backend/app/Services/RealTimeRuleEngine.php`

**Fix:**
```php
// Sort rules for deterministic execution
$realtimeValidationRules = $realtimeValidationRules->sortBy([
    ['priority', 'desc'],
    ['id', 'asc'],
]);

$realtimeWorkflowRules = $realtimeWorkflowRules->sortBy([
    ['priority', 'desc'],
    ['id', 'asc'],
]);
```

---

## Phase 5: Testing & Documentation (3-4 days)

### 5.1 Add Comprehensive Tests

**File:** `tests/Feature/RealTimeRuleEngineTest.php`

**Create:**
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealTimeRuleEngineTest extends TestCase
{
    public function test_realtime_execution_uses_bc_math(): void
    {
        // Test that real-time calculations match submission calculations
    }
    
    public function test_dependency_graph_cached(): void
    {
        // Test that dependency graph is cached
    }
    
    public function test_cascading_execution(): void
    {
        // Test that dependent rules execute in sequence
    }
    
    public function test_loop_detection(): void
    {
        // Test that infinite loops are detected and prevented
    }
    
    public function test_case_rules_execute(): void
    {
        // Test that case-based rules work in real-time
    }
    
    public function test_deterministic_execution(): void
    {
        // Test that same inputs produce same outputs
    }
    
    public function test_concurrent_requests(): void
    {
        // Test that concurrent requests don't cause race conditions
    }
}
```

---

## Summary

| Phase | Tasks | Duration | Priority |
|-------|-------|----------|----------|
| 1 | Financial Consistency | 3-4 days | CRITICAL |
| 2 | Performance Optimization | 4-5 days | CRITICAL |
| 3 | Execution Correctness | 4-5 days | CRITICAL |
| 4 | Concurrency & Determinism | 3-4 days | HIGH |
| 5 | Testing & Documentation | 3-4 days | HIGH |
| **TOTAL** | | **17-22 days** | |

---

## Approval Required

**Before proceeding with remediation:**

1. [ ] Review critical findings with stakeholders
2. [ ] Approve remediation plan
3. [ ] Allocate resources (2-3 weeks)
4. [ ] Schedule deployment window
5. [ ] Prepare rollback plan

---

**Document Prepared By:** System Audit  
**Date:** 2026-06-11  
**Status:** PENDING APPROVAL
