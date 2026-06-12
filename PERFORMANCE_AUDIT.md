# PERFORMANCE FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** N+1 queries, rule execution complexity, field evaluation complexity, branching complexity, fee lookup complexity

---

## EXECUTIVE SUMMARY

The system has several performance bottlenecks that will become critical at scale. The most significant issues are N+1 queries in rule loading, unindexed database queries, and duplicated computation in rule engines.

---

## 1. N+1 QUERY ANALYSIS

### 1.1 N+1 Query Patterns

| Location | Query Pattern | N+1 Risk | Impact |
|----------|--------------|----------|--------|
| EnterpriseRuleEngine::execute() | Loads validation_rules + workflow_rules per execution | ✅ | 2 queries per step submission |
| FeeEngine::resolveActive() | Queries fee_versions + official_fees per fee code | ✅ | N queries for N fees |
| WorkflowExecutionService::calculateItems() | Calls resolveActive() for each field with fee_code | ✅ | N queries for N fee fields |
| FieldInheritanceResolver::resolveProperty() | Queries register_field per property | ✅ | 6+ queries per field |
| CascadingSelectEngine::resolveOptions() | Traverses cascade chain per field | ✅ | N queries for N cascading fields |
| DynamicOptionSource::resolveOptions() | Queries DB/API/service per field | ✅ | N queries for N dynamic fields |
| ValidationEngine::validate() | Loads validation_rules per version | ✅ | 1 query per step submission |
| ReceiptService::syncItems() | Queries RegisterField per item | ✅ | N queries for N items |

### 1.2 Issues

#### PER-001: FeeEngine::resolveActive() called per fee without caching [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `resolveActive()` queries the database every time it's called
- **Impact:** With 10 fee fields per step and 10 steps per execution, that's 100 fee resolution queries per execution
- **Solution:** Add request-level caching with `resolveMany()` or memoization

#### PER-002: FieldInheritanceResolver makes 6+ queries per field [HIGH]
- **Severity:** High
- **Root Cause:** `WorkflowFieldSchemaBuilder::resolveField()` calls `resolveProperty()` individually for each property
- **Impact:** With 20 fields per workflow version, that's 120+ queries per schema build
- **Solution:** Use `resolve()` method that resolves all properties at once

#### PER-003: EnterpriseRuleEngine loads rules every step submission [HIGH]
- **Severity:** High
- **Root Cause:** `EnterpriseRuleEngine::execute()` loads validation_rules and workflow_rules from database on every call
- **Impact:** Rules are loaded N times for N step submissions
- **Solution:** Cache rules per workflow version

---

## 2. RULE EXECUTION COMPLEXITY

### 2.1 Complexity Analysis

| Component | Complexity | Factors | Notes |
|-----------|-----------|---------|-------|
| EnterpriseRuleEngine::execute() | O(R × C × A) | R=rules, C=conditions per rule, A=actions per rule | Nested condition groups multiply C |
| RuleEngineV2::evaluate() | O(R × C × A) | Same | Duplicated logic |
| ConditionalBranchingEngine::evaluateCaseRule() | O(C × P) | C=cases, P=priority sorting | Sorts cases every evaluation |
| ValidationEngine::validate() | O(V × Q) | V=validation rules, Q=query complexity | SQL validation is O(1) but expensive |
| CrossFieldValidationEngine::validateAll() | O(F × V) | F=fields, V=validations per field | BC Math comparisons per validation |
| ConditionalValidationEngine::validateAll() | O(F × V) | Same | Duplicated logic |

### 2.2 Issues

#### PER-004: Cases sorted on every evaluation [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `ConditionalBranchingEngine::evaluateCaseRule()` sorts cases by priority every time
- **Impact:** Sorting is O(C log C) per evaluation
- **Solution:** Pre-sort cases at rule save time

#### PER-005: Duplicated validation engines [HIGH]
- **Severity:** High
- **Root Cause:** `ConditionalValidationEngine` and `CrossFieldValidationEngine` have near-identical validators
- **Impact:** Both engines run on every step submission, doubling validation work
- **Solution:** Consolidate into single validation engine

---

## 3. FIELD EVALUATION COMPLEXITY

### 3.1 Field Evaluation Pipeline

```
WorkflowFieldSchemaBuilder::buildForVersion()
    ↓ O(F) fields
For each field:
    resolveProperty() × 6          → O(6) queries (PER-002)
    resolveOptions()               → O(1) or O(C) for cascading
    resolveValidationRules()       → O(V) validations
    resolveComputedValue()         → O(D) dependencies
    resolveCascadingOptions()      → O(C) cascade depth
    resolveDynamicOptions()        → O(1) or O(API latency)
    resolveCrossFieldValidations   → O(X) cross-field rules
```

### 3.2 Issues

#### PER-006: Schema builder calls resolveProperty 7+ times per field [HIGH]
- **Severity:** High
- **Root Cause:** `resolveField()` calls `resolveProperty()` for each property individually, then `resolveOptions()` calls it again for field_type
- **Impact:** 7× redundant lookups per field
- **Solution:** Call `resolve()` once and cache result

---

## 4. BRANCHING COMPLEXITY

### 4.1 Branching Analysis

| Component | Complexity | Factors | Notes |
|-----------|-----------|---------|-------|
| CascadingSelectEngine::buildCascadeGraph() | O(F²) | F=fields | Builds adjacency matrix |
| CascadingSelectEngine::getCascadeChain() | O(C) | C=chain depth | No cycle detection → infinite loop risk |
| ComputedFieldEngine::recalculateChain() | O(F × D) | F=fields, D=dependency depth | BFS with visited set |
| WorkflowBranchController::processValidationResults() | O(R) | R=results | Linear scan for priority |

### 4.2 Issues

#### PER-007: Cascade chain has no cycle detection [HIGH]
- **Severity:** High
- **Root Cause:** `getCascadeChain()` uses while loop without visited set
- **Impact:** Circular parent reference causes infinite loop
- **Solution:** Add visited set with max depth limit

---

## 5. FEE LOOKUP COMPLEXITY

### 5.1 Fee Lookup Analysis

| Method | Queries | Complexity | Cached? |
|--------|---------|-----------|---------|
| FeeEngine::resolve() | 1 | O(1) | ❌ |
| FeeEngine::resolveActive() | 2 | O(1) | ❌ |
| FeeEngine::resolveMany() | N × 2 | O(N) | ❌ |
| RuleEngineV2::resolveAction (set_fee) | 2 | O(1) | ❌ |
| EnterpriseRuleEngine::executeActions (set_fee) | 2 | O(1) | ❌ |
| WorkflowExecutionService::calculateItems | N × 2 | O(N) | ❌ |

### 5.2 Issues

#### PER-008: No fee resolution caching anywhere [HIGH]
- **Severity:** High
- **Root Cause:** Every fee resolution queries the database
- **Impact:** At scale (1000 workflows × 10 fees each = 10,000 fee queries per batch)
- **Solution:** Add request-level caching with resolveMany()

---

## 6. SCALE ESTIMATES

### 6.1 Behavior with 1,000 Workflows

| Metric | Estimate | Bottleneck |
|--------|----------|------------|
| Rule loading per execution | 2,000 queries (2 per workflow) | EnterpriseRuleEngine |
| Fee resolution per execution | 10,000 queries (10 fees × 1000 workflows) | FeeEngine |
| Field schema build | 60,000 queries (20 fields × 6 properties × 1000 workflows) | FieldInheritanceResolver |
| Total queries per execution batch | ~72,000 | All engines |

### 6.2 Behavior with 10,000 Rules

| Metric | Estimate | Bottleneck |
|--------|----------|------------|
| Rule evaluation per step | O(10,000 × C × A) | EnterpriseRuleEngine |
| Rule loading | 1 query (batch load) | ✅ |
| Memory usage | ~50MB for rule objects | ⚠️ |

### 6.3 Behavior with 100,000 Executions

| Metric | Estimate | Bottleneck |
|--------|----------|------------|
| Event storage | 100,000 × 10 events = 1M rows | Database |
| Replay verification | O(1M events) per forensic report | EventReplayEngine |
| Hash chain verification | O(1M) per verification | EventStore |

---

## 7. BOTTLENECK IDENTIFICATION

### 7.1 Critical Bottlenecks

| Bottleneck | Location | Impact | Fix Complexity |
|------------|----------|--------|---------------|
| Fee resolution N+1 | FeeEngine::resolveActive() | Critical | Low (add caching) |
| Field property N+1 | FieldInheritanceResolver | High | Low (use resolve()) |
| Rule loading per step | EnterpriseRuleEngine::execute() | High | Low (cache rules) |
| Duplicated validation | ConditionalValidationEngine + CrossFieldValidationEngine | High | Medium (consolidate) |
| Duplicated rule engines | RuleEngineV2 + EnterpriseRuleEngine | High | High (consolidate) |
| No fee version overlap prevention | FeeVersion::saving | High | Medium (add constraint) |
| Cascade cycle risk | CascadingSelectEngine | Medium | Low (add cycle detection) |
| Report memory exhaustion | ReportService | Medium | Low (add pagination) |

### 7.2 Issues

#### PER-009: ReportService loads all records into memory [HIGH]
- **Severity:** High
- **Root Cause:** All report methods use `->get()` without pagination
- **Impact:** Memory exhaustion with thousands of receipts
- **Solution:** Add pagination or chunked processing

#### PER-010: CSV export does not escape special characters [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `exportCsv()` does not escape commas, quotes, or newlines
- **Impact:** Malformed CSV if field values contain special characters
- **Solution:** Use proper CSV escaping

---

## FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 1 |
| High | 7 |
| Medium | 3 |
| Low | 0 |

---

## RECOMMENDED FIXES PRIORITY

1. **PER-001:** Add fee resolution caching
2. **PER-002:** Use FieldInheritanceResolver::resolve() instead of individual resolveProperty() calls
3. **PER-003:** Cache rules per workflow version
4. **PER-005:** Consolidate validation engines
5. **PER-006:** Cache resolveProperty results in schema builder
6. **PER-007:** Add cascade cycle detection
7. **PER-008:** Add fee resolution caching (same as PER-001)
8. **PER-009:** Add pagination to reports
9. **PER-010:** Fix CSV escaping
10. **PER-004:** Pre-sort cases at rule save time
