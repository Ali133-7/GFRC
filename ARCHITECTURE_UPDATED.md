# GFRC Enterprise Workflow Engine — Updated Architecture

**Date:** 2026-06-07  
**Version:** v2.0 Enterprise  
**Classification:** INTERNAL — CONFIDENTIAL

---

## Dependency Map (Updated)

```
[Register Field]  ← Master Source of Truth
       ↓  يرث: field_type, options, validation_rules, is_financial...
[FieldInheritanceResolver]  ← NEW: Provenance tracking for every property
       ↓
[Workflow Schema Builder]
       ↓  يُنتج: workflow_versions + steps + fields + rules
[Workflow Execution Engine]
       ↓  يُنشئ: workflow_executions + values_snapshot
[EnterpriseRuleEngineV2]  ← AND / OR / Nested — null-safe
       ↓  يُحدِّث: field_states, calculated_items
[FieldStateEngine]  ← NEW: Deterministic state transitions with audit log
       ↓
[ValidationEngine]  ← duplicate_check, cross-register, SQL-injection hardened
       ↓  يُنتج: validation_results (error / warning / redirect)
[FinancialCalculationPipeline]  ← NEW: Fee → Formula → Discount → Totals → SHA-256
       ↓  يُنتج: calculated_items + financial_trace + total_amount
[WorkflowRoutingEngine]  ← NEW: Atomic redirects with values preservation
       ↓  يُحوِّل: execution إلى workflow آخر مع الحفاظ على history
[UI Renderer]  ← Dynamic Field Renderer + Wizard State Machine
       ↓  يعرض: field_states + fee_panel + validation_messages
[Receipt Generator]  ← immutable snapshot + idempotency_key UNIQUE
       ↓  يُخزِّن: calculation_snapshot + audit_trail
```

---

## New Components

### Backend Services

| Service | File | Responsibility |
|---------|------|---------------|
| `FormulaEvaluator` | `app/Services/FormulaEvaluator.php` | Safe formula evaluation via `symfony/expression-language`. Whitelist: `min, max, round, abs`. Zero `eval()`. |
| `FieldInheritanceResolver` | `app/Services/FieldInheritanceResolver.php` | Strict priority: Workflow Override → Snapshot → Register Field → System Default. Never silently falls back to `text`. |
| `FinancialCalculationPipeline` | `app/Services/FinancialCalculationPipeline.php` | 5-stage pipeline: FeeResolution → FormulaEvaluation → DiscountApplication → TotalsAggregation → SnapshotGeneration. Throws on zero total with active items. |
| `FieldStateEngine` | `app/Services/FieldStateEngine.php` | Manages `visible/hidden`, `required/optional`, `readonly/editable`, `locked/unlocked`, `enabled/disabled`. Persists every change to `field_state_history`. |
| `WorkflowRoutingEngine` | `app/Services/WorkflowRoutingEngine.php` | Atomic workflow-to-workflow redirects. Copies `values_snapshot`, preserves `execution_history`, logs to `workflow_routing_log`. |
| `CleanupAbandonedWorkflows` | `app/Console/Commands/CleanupAbandonedWorkflows.php` | Hourly command marking stale executions as `abandoned`. Configurable via `WORKFLOW_ABANDONED_HOURS`. |

### Models

| Model | File | Key Change |
|-------|------|-----------|
| `Workflow` | `app/Models/Workflow.php` | Dropped `current_version` column. Active version resolved via subquery. |
| `FeeVersion` | `app/Models/FeeVersion.php` | Added `boot()` temporal overlap check for SQLite/PostgreSQL parity. |
| `WorkflowExecution` | `app/Models/WorkflowExecution.php` | Added casts for `field_states`, `rule_results`, `validation_results`, `routing_decisions`, `financial_trace`, `last_saved_at`. |
| `WorkflowRoutingLog` | `app/Models/WorkflowRoutingLog.php` | NEW: Audit trail for every workflow redirect. |
| `FieldStateHistory` | `app/Models/FieldStateHistory.php` | NEW: Row-level history of every field state change. |

### Frontend Components

| Component | File | Responsibility |
|-----------|------|---------------|
| `useWorkflowExecution` | `frontend/src/hooks/useWorkflowExecution.ts` | Encapsulates execution lifecycle: start, submit, preview, safeComplete. Auto-save draft every 30s. Pre-completes if already completed. |
| `RealTimeFeePanel` | `frontend/src/components/execution/RealTimeFeePanel.tsx` | Live fee display with loading state. Shows warning if calculation > 3s. |
| `WorkflowWizardFooter` | `frontend/src/components/execution/WorkflowWizardFooter.tsx` | Disables confirm button during fee calculation. Shows spinner states. |
| `FieldStateProvider` | `frontend/src/components/execution/FieldStateProvider.tsx` | React Context for field states. Batch updates without full re-render. |
| `DynamicFieldRenderer` | `frontend/src/components/execution/DynamicFieldRenderer.tsx` | Type-safe field rendering: text, number, select, multiselect, textarea, date, checkbox. Respects `FieldStateEngine` output. |

---

## Hardening Summary

| Vector | Before | After |
|--------|--------|-------|
| `eval()` in formulas | `EnterpriseRuleEngine::calculateExpression()` used `eval()` | `FormulaEvaluator` uses `symfony/expression-language` with strict whitelist |
| SQL Injection in validation | `ValidationEngine` interpolated column names | Parameterized JSON operators + `isValidFieldName()` whitelist |
| Arbitrary code execution | `DynamicOptionSource::resolveFromService()` called any class/method | Service class whitelist via `config('workflow.allowed_option_services')` |
| SQL Injection in options | `DynamicOptionSource::resolveFromDatabase()` interpolated table/columns | `isSafeIdentifier()` regex validation on all identifiers |
| Race condition on complete | Controller loaded execution without lock; service ignored `update()` affected rows | Controller uses `lockForUpdate()` + transaction; service checks `affected === 0` |
| Duplicate receipts | `idempotency_key` was random UUID per call | Deterministic `idempotency_key` based on `execution_id + lock_version + calculated_items` |
| Fee version overlap | `orderByDesc('version')` only; no overlap prevention | `FeeVersion::boot()` enforces temporal exclusivity with `TemporalOverlapException` |
| Null rule evaluation | `null > 5` returned `false` silently | `RuleEngineV2::evaluateCondition()` throws `RuleEvaluationException` for null with non-empty operators |
| Stale workflow version | `current_version` integer column, manually updated | Subquery: `activeVersion()` always returns the latest `status = 'active'` version |
| Abandoned executions | No cleanup; records remained `in_progress` forever | `workflow:cleanup-abandoned` runs hourly via Laravel Scheduler |

---

## Database Schema Changes

### Dropped
- `workflows.current_version`

### Added
- `workflow_executions.field_states` (JSONB)
- `workflow_executions.rule_results` (JSONB)
- `workflow_executions.validation_results` (JSONB)
- `workflow_executions.routing_decisions` (JSONB)
- `workflow_executions.financial_trace` (JSONB)
- `workflow_executions.last_saved_at` (TIMESTAMP)
- `workflow_fields.inheritance_source` (VARCHAR 20)

### New Tables
- `workflow_routing_log`
- `field_state_history`

### Constraints
- `fee_versions` temporal overlap check in `FeeVersion::boot()` (SQLite/PostgreSQL parity)
- `receipts.idempotency_key` already had UNIQUE constraint (confirmed)

---

## Configuration

New file: `config/workflow.php`

```php
'abandoned_hours' => env('WORKFLOW_ABANDONED_HOURS', 24),
'financial_scale' => env('WORKFLOW_FINANCIAL_SCALE', 3),
'strict_formula_validation' => env('WORKFLOW_STRICT_FORMULA_VALIDATION', true),
'allowed_formula_functions' => ['min', 'max', 'round', 'abs'],
'debug_panel_enabled' => env('WORKFLOW_DEBUG_PANEL_ENABLED', true),
```

---

*End of Architecture Document*
