# REAL-TIME RULE ENGINE AUDIT

## Executive Summary

This document outlines the architectural changes required to implement **Real-Time Rule Execution** in Workflow Engine V2.

**Current State:**
- Rules execute only during: Next Step, Review, Submission
- No immediate feedback on field changes
- Stale values possible during workflow progression

**Target State:**
- Rules execute immediately on ANY field change
- Full support for ALL rule types (Simple, Case, Enterprise, Validation, Routing, Financial)
- Dependency graph for efficient execution
- Loop protection and cycle detection
- Execution status tracking (IDLE → EVALUATING → CALCULATING → READY/ERROR)
- Next button blocked until calculations complete

---

## Architecture Overview

### Current Architecture

```
User Input
    ↓
[Wait for Next/Submit]
    ↓
WorkflowExecutionService::submitStep()
    ↓
EnterpriseRuleEngine::execute()
    ↓
Field Effects + Financial Calculations
    ↓
UI Update
```

**Problems:**
1. ❌ No real-time execution
2. ❌ No dependency tracking
3. ❌ No loop protection
4. ❌ No execution status tracking
5. ❌ All rules evaluated on every submission (inefficient)

### Target Architecture

```
User Input (Field Change)
    ↓
DependencyResolver::getAffectedRules(fieldId)
    ↓
RealTimeRuleEngine::execute(rules, values)
    ↓
┌─────────────────────────────────────┐
│  Loop Detector (Cycle Detection)    │
│  Execution Status: EVALUATING       │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│  Rule Executor                      │
│  - Simple Rules                     │
│  - Case Rules                       │
│  - Enterprise Rules                 │
│  - Validation Rules                 │
│  - Routing Rules                    │
│  - Financial Rules                  │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│  Action Executor                    │
│  - Field Effects (show/hide/etc)    │
│  - Financial Actions (set_fee/etc)  │
│  - Validation Recalculation         │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│  Financial Recalculator             │
│  - Subtotal                         │
│  - Discounts                        │
│  - Fees                             │
│  - Taxes                            │
│  - Insurance                        │
│  - Total                            │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│  Execution Status: READY/ERROR      │
│  Next Button Enabled/Disabled       │
└─────────────────────────────────────┘
    ↓
UI Update (React State)
```

---

## Affected Files

### Backend Files

| File | Changes | Priority |
|------|---------|----------|
| `app/Models/ValidationRule.php` | Add `realtime_enabled` column | HIGH |
| `app/Models/WorkflowRule.php` | Add `realtime_enabled` column | HIGH |
| `app/Services/RealTimeRuleEngine.php` | **NEW** - Core real-time execution | HIGH |
| `app/Services/DependencyResolver.php` | **NEW** - Dependency graph | HIGH |
| `app/Services/LoopDetector.php` | **NEW** - Cycle detection | HIGH |
| `app/Services/ExecutionStateManager.php` | **NEW** - Status tracking | HIGH |
| `app/Services/FinancialRecalculator.php` | **NEW** - Financial recalculation | HIGH |
| `app/Services/EnterpriseRuleEngine.php` | Modify to support real-time | HIGH |
| `app/Http/Controllers/Api/V1/WorkflowExecutionController.php` | Add real-time endpoints | HIGH |
| `database/migrations/*` | Add migrations for new columns | HIGH |

### Frontend Files

| File | Changes | Priority |
|------|---------|----------|
| `src/hooks/useRealTimeRules.ts` | **NEW** - Real-time rule hook | HIGH |
| `src/hooks/useExecutionStatus.ts` | **NEW** - Execution status hook | HIGH |
| `src/components/execution/RealTimeRuleExecutor.tsx` | **NEW** - Real-time executor component | HIGH |
| `src/components/execution/ExecutionStatusIndicator.tsx` | **NEW** - Status indicator | HIGH |
| `src/pages/workflows/WorkflowExecutionPage.tsx` | Integrate real-time execution | HIGH |
| `src/components/validation/ValidationRuleBuilder.tsx` | Add `realtime_enabled` checkbox | MEDIUM |
| `src/components/rules/SimpleRuleBuilder.tsx` | Add `realtime_enabled` checkbox | MEDIUM |
| `src/components/rules/CaseRuleBuilder.tsx` | Add `realtime_enabled` checkbox | MEDIUM |

---

## Dependency Graph Design

### Graph Structure

```typescript
interface DependencyNode {
    fieldId: string;
    dependentRules: string[]; // rule_ids
    dependentFields: string[]; // field_ids that depend on this field
}

interface DependencyGraph {
    nodes: Map<string, DependencyNode>;
    edges: Map<string, Set<string>>; // field_id → Set<rule_id>
}
```

### Graph Construction

```php
// Pseudo-code for dependency graph construction
class DependencyResolver {
    public function buildGraph(array $rules): DependencyGraph {
        $graph = new DependencyGraph();
        
        foreach ($rules as $rule) {
            // Extract condition field IDs
            $conditionFields = $this->extractConditionFields($rule);
            
            // Extract action target field IDs
            $actionFields = $this->extractActionFields($rule);
            
            // Add edges: condition_field → rule_id
            foreach ($conditionFields as $fieldId) {
                $graph->addEdge($fieldId, $rule->id);
            }
            
            // Add edges: rule_id → action_field
            foreach ($actionFields as $fieldId) {
                $graph->addEdge($rule->id, $fieldId);
            }
        }
        
        return $graph;
    }
    
    public function getAffectedRules(string $changedFieldId): array {
        // BFS/DFS to find all affected rules
        return $this->graph->getDependentRules($changedFieldId);
    }
}
```

### Example

**Rules:**
1. IF `record_count > 0` THEN `goods_for_sale = record_count * 25000`
2. IF `goods_for_sale > 100000` THEN `fee = 5000`

**Graph:**
```
record_count → Rule1 → goods_for_sale → Rule2 → fee
```

**When `record_count` changes:**
1. Execute Rule1
2. Update `goods_for_sale`
3. Execute Rule2 (because `goods_for_sale` changed)
4. Update `fee`

---

## Execution Pipeline

### 1. Field Change Detection

```typescript
// Frontend
const handleFieldChange = (fieldId: string, value: any) => {
    setExecutionStatus('EVALUATING');
    setNextButtonDisabled(true);
    
    // Trigger real-time execution
    realTimeExecutor.execute(fieldId, value);
};
```

### 2. Dependency Resolution

```php
// Backend
$affectedRules = $dependencyResolver->getAffectedRules($changedFieldId);
$affectedRules = array_filter($affectedRules, fn($r) => $r->realtime_enabled);
```

### 3. Loop Detection

```php
// Before execution
$hasCycle = $loopDetector->detectCycle($affectedRules, $changedFieldId);
if ($hasCycle) {
    throw new RuleDependencyCycleException();
}
```

### 4. Rule Execution

```php
// Execute affected rules only
$results = $realTimeRuleEngine->execute($affectedRules, $values);
```

### 5. Field Effects Application

```php
// Apply field effects
foreach ($results as $result) {
    $this->applyFieldEffects($result->fieldEffects);
}
```

### 6. Financial Recalculation

```php
// Recalculate financials
$financials = $financialRecalculator->recalculate($values);
```

### 7. Status Update

```php
// Update execution status
$executionStatus = 'READY';
setNextButtonDisabled(false);
```

---

## Loop Protection

### Cycle Detection Algorithm

```php
class LoopDetector {
    public function detectCycle(string $startFieldId, array $rules): bool {
        $visited = [];
        $recursionStack = [];
        
        return $this->hasCycleDFS($startFieldId, $rules, $visited, $recursionStack);
    }
    
    private function hasCycleDFS(
        string $fieldId,
        array $rules,
        array &$visited,
        array &$recursionStack
    ): bool {
        $visited[$fieldId] = true;
        $recursionStack[$fieldId] = true;
        
        // Get rules that depend on this field
        $dependentRules = $this->getRulesDependingOnField($fieldId, $rules);
        
        foreach ($dependentRules as $rule) {
            // Get fields that this rule affects
            $affectedFields = $this->getAffectedFields($rule);
            
            foreach ($affectedFields as $affectedField) {
                if (!isset($visited[$affectedField])) {
                    if ($this->hasCycleDFS($affectedField, $rules, $visited, $recursionStack)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$affectedField])) {
                    return true; // Cycle detected
                }
            }
        }
        
        unset($recursionStack[$fieldId]);
        return false;
    }
}
```

### Publication Validation

```php
// Prevent publishing workflows with cycles
public function publishVersion(string $versionId): JsonResponse {
    $rules = $this->getRulesForVersion($versionId);
    
    $hasCycle = $this->loopDetector->detectCyclesInWorkflow($rules);
    
    if ($hasCycle) {
        return $this->error('RULE_DEPENDENCY_CYCLE_ERROR', 422);
    }
    
    // Proceed with publication
}
```

---

## Performance Impact

### Optimization Strategies

1. **Incremental Execution**
   - Only execute affected rules
   - Skip unrelated rules

2. **Memoization**
   - Cache rule evaluation results
   - Invalidate cache only when dependencies change

3. **Batched Updates**
   - Group multiple field changes
   - Execute once per batch

4. **Debouncing**
   - Wait for user to stop typing
   - Execute after 300ms delay

### Expected Performance

| Scenario | Current | Target |
|----------|---------|--------|
| 100 rules, 1 field change | All 100 | ~5-10 affected |
| 1000 rules, 1 field change | All 1000 | ~10-50 affected |
| 10000 rules, 1 field change | All 10000 | ~50-200 affected |
| Execution time (1000 rules) | 500ms | <50ms |

---

## Migration Impact

### Database Changes

```sql
-- Add realtime_enabled column
ALTER TABLE validation_rules ADD COLUMN realtime_enabled BOOLEAN DEFAULT false;
ALTER TABLE workflow_rules ADD COLUMN realtime_enabled BOOLEAN DEFAULT false;

-- Add execution_status column
ALTER TABLE workflow_executions ADD COLUMN execution_status VARCHAR(20) DEFAULT 'IDLE';
```

### Backward Compatibility

- Existing rules: `realtime_enabled = false` (default)
- No breaking changes to existing workflows
- Real-time execution opt-in only

---

## Testing Strategy

### Unit Tests

```php
// DependencyResolverTest
test('getAffectedRules returns only dependent rules');
test('buildGraph creates correct edges');

// LoopDetectorTest
test('detectCycle returns true for circular dependency');
test('detectCycle returns false for linear dependency');

// RealTimeRuleEngineTest
test('execute runs only affected rules');
test('execute updates field effects');
test('execute recalculates financials');
```

### Integration Tests

```typescript
// Real-time execution test
test('field change triggers immediate rule execution', async () => {
    await page.fill('[data-field="record_count"]', '5');
    
    // Wait for real-time execution
    await page.waitForSelector('[data-status="READY"]');
    
    // Verify field updated
    const goodsValue = await page.inputValue('[data-field="goods_for_sale"]');
    expect(goodsValue).toBe('125000'); // 5 * 25000
});
```

### Performance Tests

```php
// PerformanceTest
test('1000 rules execute in < 100ms', function() {
    $start = microtime(true);
    $this->engine->execute($rules, $values);
    $duration = (microtime(true) - $start) * 1000;
    
    $this->assertLessThan(100, $duration);
});
```

---

## Acceptance Criteria

### Scenario 1: Financial Calculation
- ✅ `record_count` changes from 0 to 5
- ✅ `goods_for_sale` updates to `125000` immediately
- ✅ No button press required

### Scenario 2: Visibility
- ✅ `registration_type` changes to `renewal`
- ✅ Renewal fields become visible immediately
- ✅ Other fields hidden immediately

### Scenario 3: Fees
- ✅ `category` changes to `premium`
- ✅ Premium fee applied immediately
- ✅ Total recalculated immediately

### Scenario 4: Discounts
- ✅ Discount percentage changes
- ✅ All totals recalculate immediately
- ✅ UI shows updated values

### Scenario 5: Performance
- ✅ 1000 rules active
- ✅ Only affected rules execute
- ✅ Execution completes in < 100ms

### Scenario 6: Loop Protection
- ✅ Circular dependency detected
- ✅ Error shown to user
- ✅ Workflow cannot be published

### Scenario 7: Next Button Control
- ✅ Next button disabled during execution
- ✅ Next button enabled when READY
- ✅ User cannot proceed with stale values

---

## Implementation Phases

### Phase 1: Core Infrastructure (Week 1)
- [ ] DependencyResolver
- [ ] LoopDetector
- [ ] ExecutionStateManager
- [ ] Database migrations

### Phase 2: Real-Time Engine (Week 2)
- [ ] RealTimeRuleEngine
- [ ] FinancialRecalculator
- [ ] API endpoints

### Phase 3: Frontend Integration (Week 3)
- [ ] useRealTimeRules hook
- [ ] useExecutionStatus hook
- [ ] RealTimeRuleExecutor component
- [ ] ExecutionStatusIndicator component

### Phase 4: Testing & Optimization (Week 4)
- [ ] Unit tests
- [ ] Integration tests
- [ ] Performance tests
- [ ] Optimization

---

## Risk Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Performance degradation | HIGH | Incremental execution, memoization |
| Infinite loops | HIGH | Loop detection, max execution depth |
| Stale values | MEDIUM | Execution status tracking, button blocking |
| Browser performance | MEDIUM | Debouncing, batched updates |
| Backward compatibility | LOW | Default `realtime_enabled = false` |

---

## Conclusion

Real-time rule execution is a **critical feature** for modern workflow engines. This implementation provides:

1. ✅ Immediate feedback on field changes
2. ✅ Efficient dependency-based execution
3. ✅ Loop protection and cycle detection
4. ✅ Execution status tracking
5. ✅ Full support for all rule types
6. ✅ Performance optimization for 1000+ rules

**Next Steps:**
1. Review and approve architecture
2. Implement Phase 1 (Core Infrastructure)
3. Test and iterate
4. Deploy with feature flag

---

**Document Version:** 1.0
**Last Updated:** 2026-06-11
**Author:** Workflow Engine Team
