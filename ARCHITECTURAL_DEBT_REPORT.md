# ARCHITECTURAL DEBT REPORT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** Duplicated logic, competing sources of truth, anti-patterns, hidden coupling, technical debt

---

## EXECUTIVE SUMMARY

The codebase contains significant architectural debt accumulated through multiple development phases. The most critical issues are three competing rule engines, duplicated action handlers, competing formula evaluators, and overlapping routing implementations. This debt must be addressed before the system can be considered production-ready for government financial use.

---

## 1. DUPLICATED LOGIC

### 1.1 Critical Duplications

| Duplicated Logic | Files Involved | Lines Duplicated | Risk |
|-----------------|---------------|-----------------|------|
| Receipt event applicators (5 methods) | ReceiptService + EventReplayEngine | ~100 | Critical |
| Rule evaluation (simple + case-based) | RuleEngineV2 + EnterpriseRuleEngine | ~200 | Critical |
| Action handlers (12+ types) | EnterpriseRuleEngine + RuleEngineV2 + ConditionalBranchingEngine | ~400 | Critical |
| Field schema normalization | VisibilityResolver + WorkflowFieldSchemaBuilder | ~150 | High |
| Cross-field validators | ConditionalValidationEngine + CrossFieldValidationEngine | ~100 | High |
| Hash chain verification | EventReplayEngine (internal: execution vs. receipt) | ~80 | Medium |
| Placeholder resolution ({{field_id}}) | ComputedFieldEngine + ConditionalBranchingEngine + EnterpriseRuleEngine + ValidationEngine | ~50 | Medium |
| Financial value normalization | ReceiptService + InsuranceEngine + EnterpriseRuleEngine | ~40 | Medium |
| parseTypedValue / parseDate | VisibilityResolver + WorkflowFieldSchemaBuilder | ~30 | Low |
| Permission gating (cross-register) | ValidationEngine (checkCrossRegister + checkDynamicSearch) | ~20 | Low |
| Redirect/routing logic | WorkflowBranchController + WorkflowRoutingEngine | ~80 | High |
| Condition format handling | EnterpriseRuleEngine (3 formats) | ~100 | Medium |

### 1.2 Issues

#### AD-001: Three competing rule engines [CRITICAL]
- **Severity:** Critical
- **Root Cause:** RuleEngineV2, EnterpriseRuleEngine, and ConditionalBranchingEngine all evaluate rules with overlapping functionality
- **Debt Type:** Competing sources of truth
- **Solution:** Consolidate into single RuleEngine with strategy pattern

#### AD-002: Duplicated receipt event applicators [CRITICAL]
- **Severity:** Critical
- **Root Cause:** ReceiptService and EventReplayEngine have identical applicator methods
- **Debt Type:** Duplicated logic
- **Solution:** Extract shared applicators into ReceiptEventApplicator class

#### AD-003: Duplicated action handlers across three engines [CRITICAL]
- **Severity:** Critical
- **Root Cause:** 12+ action types handled independently in three engines
- **Debt Type:** Duplicated logic
- **Solution:** Create unified ActionExecutor class

---

## 2. COMPETING SOURCES OF TRUTH

### 2.1 Source of Truth Analysis

| Concept | Source 1 | Source 2 | Authoritative? |
|---------|----------|----------|---------------|
| Rule evaluation | RuleEngineV2 | EnterpriseRuleEngine | EnterpriseRuleEngine (but both run) |
| Formula evaluation | FeeEngine | FormulaEvaluator | FeeEngine (but both exist) |
| Field schema | VisibilityResolver | WorkflowFieldSchemaBuilder | WorkflowFieldSchemaBuilder (but both used) |
| Discount calculation | RuleEngineV2 | EnterpriseRuleEngine | RuleEngineV2 (uses CalculationContext) |
| Routing | WorkflowBranchController | WorkflowRoutingEngine | Neither (both used differently) |
| Field state | FieldStateProvider | WorkflowExecutionPage | WorkflowExecutionPage (FieldStateProvider unused) |
| Validation | ConditionalValidationEngine | CrossFieldValidationEngine | Both run independently |
| Fee resolution | FeeEngine::resolveActive | FeeEngine::resolve | resolveActive (but resolve exists) |
| Receipt replay | ReceiptService | EventReplayEngine | Both should be identical but aren't |

### 2.2 Issues

#### AD-004: Two formula evaluators with different precision models [CRITICAL]
- **Severity:** Critical
- **Root Cause:** FeeEngine uses BC Math, FormulaEvaluator uses float
- **Debt Type:** Competing sources of truth
- **Solution:** Remove FormulaEvaluator or rewrite to use BC Math

#### AD-005: Two routing implementations with different behavior [HIGH]
- **Severity:** High
- **Root Cause:** WorkflowBranchController and WorkflowRoutingEngine both handle redirects
- **Debt Type:** Competing sources of truth
- **Solution:** Consolidate into single WorkflowRoutingService

---

## 3. ANTI-PATTERNS

### 3.1 Anti-Pattern Catalog

| Anti-Pattern | Location | Impact | Severity |
|-------------|----------|--------|----------|
| God class | EnterpriseRuleEngine (1362 lines) | Hard to maintain, test, understand | High |
| God class | WorkflowExecutionService (1650 lines) | Same | High |
| God class | ValidationEngine (817 lines) | Same | Medium |
| Feature envy | WorkflowFieldSchemaBuilder depends on 7 other engines | Tight coupling | High |
| Shotgun surgery | Adding new action type requires changes in 3+ engines | Maintenance burden | Critical |
| Divergent change | RuleEngineV2 and EnterpriseRuleEngine change for same reasons | Maintenance burden | Critical |
| Middle man | FieldInheritanceResolver delegates to getRawOriginal | Fragile indirection | Low |
| Data clump | Field state properties passed together everywhere | Could be value object | Low |
| Speculative generality | Phase 2 action types defined but not implemented | Confusion | Medium |
| Inappropriate intimacy | Services access model internals via getRawOriginal | Fragile | Medium |
| Refused bequest | RuleEngineV2 injected but evaluate() never called | Dead code | High |
| Parallel inheritance hierarchies | Three rule engines with parallel action handling | Maintenance nightmare | Critical |

### 3.2 Issues

#### AD-006: EnterpriseRuleEngine is a god class [HIGH]
- **Severity:** High
- **Root Cause:** 1362 lines handling rule loading, condition evaluation, action execution, format conversion, simulation, and financial tracing
- **Solution:** Split into RuleLoader, ConditionEvaluator, ActionExecutor, RuleSimulator

#### AD-007: WorkflowExecutionService is a god class [HIGH]
- **Severity:** High
- **Root Cause:** 1650 lines handling execution lifecycle, rule evaluation, fee calculation, field state building, receipt generation, and event management
- **Solution:** Split into ExecutionLifecycle, StepProcessor, FeeCalculator, ReceiptGenerator

---

## 4. HIDDEN COUPLING

### 4.1 Coupling Analysis

| Coupling | From | To | Type | Risk |
|----------|------|----|------|------|
| Field key convention | All engines | WorkflowExecutionService | Implicit | High |
| Condition format | All engines | EnterpriseRuleEngine | Implicit | High |
| Action format | All builders | EnterpriseRuleEngine | Implicit | Medium |
| Fee resolution | All engines | FeeEngine | Explicit | Low |
| Calculation context | All financial engines | CalculationContext | Explicit | Low |
| Event format | All services | EventStore | Explicit | Low |
| Field state shape | Backend → Frontend | WorkflowExecutionPage | Implicit | High |

### 4.2 Issues

#### AD-008: Field key convention is implicit across all engines [HIGH]
- **Severity:** High
- **Root Cause:** No canonical definition of field key format; each engine resolves keys independently
- **Impact:** Inconsistent key resolution leads to silent field mismatches
- **Solution:** Create FieldKeyResolver service with single canonical implementation

#### AD-009: Condition format detection is heuristic-based [HIGH]
- **Severity:** High
- **Root Cause:** EnterpriseRuleEngine detects condition format by checking for specific keys
- **Impact:** Malformed data could be misclassified
- **Solution:** Standardize on single format with explicit type discriminator

---

## 5. TECHNICAL DEBT

### 5.1 Debt Catalog

| Debt | Location | Impact | Effort to Fix | Priority |
|------|----------|--------|--------------|----------|
| publish() writes to dropped column | WorkflowVersion model | Broken publishing | 5 min | Critical |
| Dead code: RuleEngineV2::evaluate() | RuleEngineV2 | Confusion | 30 min | High |
| Duplicate API clients | frontend/src/api/client.ts + services/apiClient.ts | Inconsistent behavior | 1 hour | High |
| In-memory FieldAuditTrail | FieldAuditTrail service | Lost audit data | 2 hours | High |
| Decorative QR code | ReceiptService | Misleading | 4 hours | High |
| Missing FK constraints | 10 columns | Orphaned records | 2 hours | High |
| Missing indexes | 13 columns | Performance | 1 hour | High |
| Missing authorization | 12 controllers | Security vulnerability | 4 hours | Critical |
| Type definition mismatches | Frontend types | Confusion | 2 hours | Medium |
| Phase 2 action types (unimplemented) | enterprise-rule-engine.ts | Confusion | 1 hour | Low |
| TODO comments in code | Multiple files | Incomplete features | Varies | Medium |

### 5.2 Issues

#### AD-010: 12 controllers missing authorization [CRITICAL]
- **Severity:** Critical
- **Root Cause:** Authorization was never added to many controllers
- **Impact:** Any authenticated user can access all resources
- **Solution:** Add policies and authorize calls to all controllers

---

## 6. DEBT RANKING

### 6.1 Critical (Must Fix Before Production)

| Rank | Issue | Category | Effort |
|------|-------|----------|--------|
| 1 | publish() writes to dropped column | Broken functionality | 5 min |
| 2 | Missing authorization on 12 controllers | Security | 4 hours |
| 3 | Three competing rule engines | Architecture | 2 days |
| 4 | Duplicated action handlers | Architecture | 1 day |
| 5 | Two formula evaluators with different precision | Financial integrity | 4 hours |
| 6 | Duplicated receipt event applicators | Architecture | 2 hours |
| 7 | SQL injection through validation rules | Security | 2 hours |
| 8 | Fee resolution N+1 queries | Performance | 2 hours |

### 6.2 High (Should Fix Before Production)

| Rank | Issue | Category | Effort |
|------|-------|----------|--------|
| 9 | Two routing implementations | Architecture | 4 hours |
| 10 | Dead code: RuleEngineV2::evaluate() | Code quality | 30 min |
| 11 | Duplicate API clients | Code quality | 1 hour |
| 12 | In-memory FieldAuditTrail | Audit integrity | 2 hours |
| 13 | Missing FK constraints | Data integrity | 2 hours |
| 14 | Missing indexes | Performance | 1 hour |
| 15 | Decorative QR code | User experience | 4 hours |
| 16 | Field key convention inconsistency | Data integrity | 2 hours |

### 6.3 Medium (Should Fix Soon)

| Rank | Issue | Category | Effort |
|------|-------|----------|--------|
| 17 | Type definition mismatches | Code quality | 2 hours |
| 18 | Condition format standardization | Architecture | 4 hours |
| 19 | EnterpriseRuleEngine god class | Code quality | 1 day |
| 20 | WorkflowExecutionService god class | Code quality | 1 day |
| 21 | Duplicated validation engines | Architecture | 4 hours |
| 22 | Missing builders for orphan actions | UX | 4 hours |

### 6.4 Low (Nice to Have)

| Rank | Issue | Category | Effort |
|------|-------|----------|--------|
| 23 | Phase 2 action types cleanup | Code quality | 1 hour |
| 24 | TODO comment resolution | Code quality | Varies |
| 25 | FieldStateProvider integration | Code quality | 2 hours |

---

## FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 8 |
| High | 8 |
| Medium | 6 |
| Low | 3 |

---

## RECOMMENDED FIXES PRIORITY

1. **AD-001:** Consolidate three rule engines into one
2. **AD-010:** Add authorization to all controllers
3. **AD-004:** Remove or rewrite FormulaEvaluator
4. **AD-002:** Deduplicate receipt event applicators
5. **AD-003:** Create unified ActionExecutor
6. **AD-005:** Consolidate routing implementations
7. **AD-006:** Split EnterpriseRuleEngine god class
8. **AD-007:** Split WorkflowExecutionService god class
9. **AD-008:** Create FieldKeyResolver service
10. **AD-009:** Standardize condition format
