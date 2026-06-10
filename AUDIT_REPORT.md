# GFRC Enterprise Workflow Engine — Comprehensive Audit Report

**Date:** 2026-06-07  
**Auditor:** AI Implementation Agent  
**Classification:** INTERNAL — CONFIDENTIAL  

---

## Executive Summary

This audit covers the complete GFRC backend (Laravel 12) and frontend (React 18 + TypeScript) codebase. **16 critical/high issues** were identified, along with **5 missing architectural components** required for Enterprise Workflow Engine status. The system currently operates as a functional prototype but contains multiple vectors for silent data corruption, race conditions, remote code execution, and financial inaccuracy.

**Overall System Maturity:** ALPHA — NOT PRODUCTION-READY for financial transactions without remediation.

---

## Audit Methodology

1. **Static Code Analysis** — All models, services, controllers, and components read and analyzed
2. **Dependency Mapping** — Traced data flow from Register Field → Receipt
3. **Security Scan** — Searched for `eval()`, SQL injection, arbitrary code execution
4. **Race Condition Analysis** — Checked all financial mutations for locking and transaction boundaries
5. **Frontend Inspection** — Component hierarchy, state management, API integration patterns

---

## Backend Audit Table

### Models

| Component | Expected Behavior | Actual Behavior | Gap | Severity |
|-----------|------------------|-----------------|-----|----------|
| `Workflow` | `current_version` derived dynamically from `workflow_versions` | `current_version` stored as mutable `INTEGER` with no constraint | Bug 1: Stale version reference possible | CRITICAL |
| `WorkflowVersion` | Immutable after publish; active version unique per workflow | No DB constraint preventing multiple `active` versions | Can run ambiguous version | HIGH |
| `WorkflowField` | `field_type` guaranteed non-null via inheritance resolver | Falls back to `'text'` when `register_field_id` is null or model not hydrated | Silent type corruption | CRITICAL |
| `WorkflowExecution` | Optimistic locking with affected-rows verification | `lock_version` incremented but `update()` return value ignored | Silent race condition on concurrent complete/cancel | CRITICAL |
| `FeeVersion` | Temporal exclusivity — no overlapping active periods | `orderByDesc('version')` only; no overlap prevention | Bug 4: Wrong fee resolved silently | CRITICAL |
| `Receipt` | Idempotency enforced at DB level | `idempotency_key` column exists but no `UNIQUE INDEX` | Bug 6: Duplicate receipts possible | CRITICAL |
| `WorkflowExecutionEvent` | Append-only immutable ledger | Blocks `updating`/`deleting` in `boot()` — correct | ✅ No gap | — |
| `ReceiptEvent` | Append-only immutable ledger | Blocks `updating`/`deleting` in `boot()` — correct | ✅ No gap | — |
| `ReceiptCalculationSnapshot` | SHA-256 hash verifies integrity | `verifyIntegrity()` compares hash correctly | ✅ No gap | — |
| `IdempotencyKey` | Unique constraint on `key` + expiration cleanup | No unique DB constraint; no scheduled cleanup command | Race condition + key bloat | HIGH |
| `RegisterField` | Master source of truth for field properties | Has all required properties but no inheritance trace | No provenance tracking | MEDIUM |

### Services

| Component | Expected Behavior | Actual Behavior | Gap | Severity |
|-----------|------------------|-----------------|-----|----------|
| `EnterpriseRuleEngine` | Safe formula evaluation without `eval()` | `calculateExpression()` uses `eval("return (float)($evaluated);")` | Bug 3: RCE vector + silent failure | CRITICAL |
| `EnterpriseRuleEngine` | SQL injection prevention | `evaluateDatabaseCondition()` interpolates `$condition['register_column']` into `whereRaw` | SQL injection via JSON path | CRITICAL |
| `RuleEngineV2` | Null-safe condition evaluation | `evaluateCondition()` passes `null` to `compareValues()` without warning | Bug 2: Silent rule failure | CRITICAL |
| `RuleEngineV2` | Deterministic string substitution | `resolvePlaceholders()` returns `''` for missing fields | Rules match unexpectedly (`'' === ''`) | HIGH |
| `ValidationEngine` | SQL injection prevention | `applyQueryConditions()` interpolates field names into `whereRaw` | SQL injection | CRITICAL |
| `ValidationEngine` | Safe raw SQL execution | `checkSql()` uses `DB::selectOne()` with `PDO::quote()` fallback | Brittle parser; injection risk if regex misses | HIGH |
| `ValidationEngine` | Time-of-check vs time-of-use protection | All DB lookups without transactions or locks | Race condition on duplicate check | MEDIUM |
| `FeeEngine` | Reject invalid formulas | `tokenize()` silently drops unknown characters; generic RuntimeException | Broken formulas fail silently | HIGH |
| `WorkflowExecutionService` | Check `update()` affected rows on optimistic lock | `update()` return value ignored in `complete()`, `submitStep()`, `cancel()` | Bug 6: Silent lock failure | CRITICAL |
| `WorkflowExecutionService` | Single fee resolution per field | `calculateItems()` calls `feeEngine->resolve()` twice per field | Wasted queries + inconsistency risk | MEDIUM |
| `WorkflowExecutionService` | Preserve negative amounts if valid | `bccomp($amount, '0') > 0` excludes zero/negative | Discounts may disappear silently | MEDIUM |
| `WorkflowBranchController` | Atomic redirect creation | `createRedirectedExecution()` not wrapped in `DB::transaction()` | Orphaned execution risk | HIGH |
| `WorkflowBranchController` | Target workflow validation | No lock on target workflow at redirect time | Redirect to deactivated workflow possible | MEDIUM |
| `ReceiptService` | Generate valid QR codes | `generateQrSvg()` produces visual grid from MD5 — not scannable | Functional fraud risk | HIGH |
| `ReceiptService` | Event append after cache update | `recordPrint()` appends event before optimistic-lock update | Event stream inconsistency | HIGH |
| `ReceiptService` | High-precision amount formatting | `normalizeAmount()` casts to `(float)` before `number_format()` | Precision loss for large amounts | MEDIUM |
| `DynamicOptionSource` | Safe service method invocation | `resolveFromService()` calls `$service->$method()` from JSON config | Arbitrary code execution | CRITICAL |
| `DynamicOptionSource` | Safe DB table/column resolution | `resolveFromDatabase()` interpolates `$table`, `$valueColumn`, `$labelColumn` | SQL injection | CRITICAL |
| `FieldAuditTrail` | Persistent audit history | Stored only in `$this->trail = []` (in-memory) | Compliance gap | HIGH |
| `ConditionalValidationEngine` | Precise numeric comparison | `validateMin()`/`validateMax()` cast to `(float)` | Precision loss | MEDIUM |
| `CrossFieldValidationEngine` | Configurable decimal scale | Hard-coded scale `3` in all `bccomp` calls | Inconsistency if context scale changes | LOW |
| `ComputedFieldEngine` | Fail on missing dependency | `resolvePlaceholders()` substitutes `'0'` for missing values | Misleading calculations | MEDIUM |
| `TemplateService` | Atomic template operations | `createTemplate`, `updateTemplate`, `deleteTemplate` lack transactions | Partial writes | MEDIUM |
| `TemplateService` | Import preserves elements | `importTemplate()` ignores `elements` array from JSON | Data loss on import | MEDIUM |

### Controllers

| Component | Expected Behavior | Actual Behavior | Gap | Severity |
|-----------|------------------|-----------------|-----|----------|
| `WorkflowExecutionController::complete()` | Lock execution before status check | Loads execution with `findOrFail()`; no lock | Stale read race condition | CRITICAL |
| `WorkflowExecutionController::complete()` | Idempotency check before receipt generation | No idempotency check in controller or service entry | Duplicate receipt on retry | CRITICAL |
| `ReceiptController::print()` | Delegate state mutation to service | Mutates `printed_at` on model before service call | Event inconsistency | MEDIUM |
| `GuidedReceiptController` | High-precision arithmetic | Uses `(float)` addition for totals | Financial rounding errors | MEDIUM |

### Scheduled Tasks / Infrastructure

| Component | Expected Behavior | Actual Behavior | Gap | Severity |
|-----------|------------------|-----------------|-----|----------|
| `workflow:cleanup-abandoned` | Hourly cleanup of stale executions | Command does not exist | Bug 5: Records remain `in_progress` forever | HIGH |
| `IdempotencyKey` cleanup | Purge expired keys | No scheduled command | Key table bloat | MEDIUM |
| `WORKFLOW_ABANDONED_HOURS` | Environment-configurable timeout | No config file exists; no `.env` variable read | Hard-coded default only | MEDIUM |

---

## Frontend Audit Table

| Component | Expected Behavior | Actual Behavior | Gap | Severity |
|-----------|------------------|-----------------|-----|----------|
| `WorkflowExecutionPage` | Dedicated `useWorkflowExecution` hook | ~250 lines of inline state logic in page component | Unmaintainable; untestable | HIGH |
| `WorkflowExecutionPage` | Debounced fee calculation | Every keystroke triggers full re-render; no fee panel component | Performance + UX gap | HIGH |
| `WorkflowExecutionPage` | Safe multi-select parsing | `JSON.parse(val)` without try/catch | Crash on malformed data | CRITICAL |
| `WorkflowExecutionPage` | Execution start with error boundary | Mount `useEffect` retries infinitely on failure | API hammering | HIGH |
| `CaseRuleBuilder` | TanStack Query mutations | Direct API calls bypassing React Query | Inconsistent state/cache | MEDIUM |
| `EnterpriseRuleBuilder` | TanStack Query mutations | Direct API calls bypassing React Query | Inconsistent state/cache | MEDIUM |
| `ValidationRuleBuilder` | TanStack Query mutations | Direct API calls bypassing React Query | Inconsistent state/cache | MEDIUM |
| `BranchHandler` | TanStack Query mutations | Direct API calls bypassing React Query | Inconsistent state/cache | MEDIUM |
| `RealTimeFeePanel` | Display live fee with loading state | Component does not exist | Bug 7: User sees stale/no amount | HIGH |
| `WorkflowWizardFooter` | Confirm button disabled during calc | Component does not exist | Bug 7: Risk of submit during calc | HIGH |
| `DynamicFieldRenderer` | Render fields by type | Component does not exist | Inline rendering in execution page | HIGH |
| `WizardStateMachine` | Manage step transitions | Component does not exist | Inline logic in execution page | HIGH |
| `useWorkflowExecution` | Safe complete with pre-check | Hook does not exist | Bug 8: Duplicate submit on reconnect | HIGH |
| `useWorkflows` | Consistent query invalidation | `useResumeExecution` uses wrong query key shape | Cache staleness risk | LOW |
| API Client | Single axios instance with cancellation | Two divergent instances (`client.ts` + `apiClient.ts`) | Data shape mismatches | MEDIUM |

---

## Security Findings

| ID | Vector | File | Impact |
|----|--------|------|--------|
| SEC-001 | `eval()` in formula evaluation | `EnterpriseRuleEngine.php` | Remote Code Execution |
| SEC-002 | SQL injection via JSON path column | `ValidationEngine.php` | Data exfiltration/modification |
| SEC-003 | SQL injection via table/column names | `DynamicOptionSource.php` | Data exfiltration/modification |
| SEC-004 | Arbitrary class/method invocation | `DynamicOptionSource.php` | Remote Code Execution |
| SEC-005 | Regex injection in pattern match | `EnterpriseRuleEngine.php` | ReDoS / fatal errors |

---

## Financial Correctness Findings

| ID | Issue | File | Impact |
|----|-------|------|--------|
| FIN-001 | Float casting before money operations | `GuidedReceiptController.php`, `ConditionalValidationEngine.php`, `VisibilityResolver.php` | Rounding errors |
| FIN-002 | Fee resolution overlap possible | `FeeVersion.php` (no constraint) | Wrong fee amount |
| FIN-003 | Duplicate receipt on concurrent complete | `WorkflowExecutionController.php` | Double charging / accounting mismatch |
| FIN-004 | Negative amounts silently dropped | `WorkflowExecutionService.php` | Missing discount items |
| FIN-005 | `total_amount` can be zero with active fees | `WorkflowExecutionService.php` | Bug in aggregation pipeline |

---

## Missing Components (Not Implemented)

| Component | Required For | Priority |
|-----------|-----------|----------|
| `FieldInheritanceResolver` service | Phase 2 — Single source of truth | CRITICAL |
| `FieldStateEngine` class | Phase 4 — Deterministic field states | CRITICAL |
| `EnterpriseRuleEngineV2` | Phase 5 — AND/OR nested rules | CRITICAL |
| `FinancialCalculationPipeline` | Phase 9 — Deterministic totals | CRITICAL |
| `ValidationEngine` (rebuilt) | Phase 8 — Cross-register checks | CRITICAL |
| `WorkflowRoutingEngine` | Phase 7 — Workflow-to-workflow routing | HIGH |
| `WorkflowDebugPanel` | Phase 11 — Auditor transparency | HIGH |
| `safeComplete` hook | Bug 8 — Network resilience | HIGH |
| `RealTimeFeePanel` | Bug 7 — UI fee accuracy | HIGH |
| `WorkflowWizardFooter` | Bug 7 — UX during calculation | HIGH |
| `DynamicFieldRenderer` | Phase 3 — Modular rendering | HIGH |
| `WizardStateMachine` | Phase 4 — Step transition logic | HIGH |
| `FieldStateProvider` (React Context) | Phase 4 — State broadcast | HIGH |
| Frontend test suite | Phase 12 — Quality assurance | CRITICAL |

---

## Test Coverage Analysis

| Layer | Files | Coverage Estimate | Gaps |
|-------|-------|-------------------|------|
| Backend Unit | 5 | ~15% | No RuleEngineV2 null tests, no fee overlap tests, no inheritance tests |
| Backend Feature | 16 | ~25% | No race condition tests for `complete()`, no temporal overlap tests, no RCE prevention tests |
| Frontend | 0 | 0% | No component tests, no hook tests, no E2E tests |

---

## Recommendations

### Immediate (Do Not Deploy Without)
1. **Remove `eval()`** from `EnterpriseRuleEngine` and replace with `symfony/expression-language`
2. **Fix SQL injection** in `ValidationEngine` and `DynamicOptionSource`
3. **Add `lockForUpdate()` + affected-rows check** to `WorkflowExecutionController::complete()`
4. **Add temporal overlap constraint** to `fee_versions`
5. **Drop `current_version` column** and use subquery

### Short-term (Pre-Production)
6. Implement `FieldInheritanceResolver` with provenance tracking
7. Rebuild `RuleEngineV2` with null-safety and AND/OR nesting
8. Build `FinancialCalculationPipeline` with SHA-256 snapshot
9. Add `workflow:cleanup-abandoned` command with scheduler
10. Create all missing frontend components and hooks

### Medium-term (Post-Launch)
11. Achieve >85% test coverage across all layers
12. Implement `WorkflowDebugPanel` for auditors
13. Add Playwright E2E suite for RTL + dark mode
14. Performance baseline and optimization

---

*End of Audit Report*
