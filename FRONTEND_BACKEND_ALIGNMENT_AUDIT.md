# FRONTEND-BACKEND ALIGNMENT FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** Builder → API → Database → Runtime Engine → UI Rendering alignment

---

## EXECUTIVE SUMMARY

The frontend and backend have drifted in several critical areas: type definitions, field state shapes, action formats, and fee resolution. There are also duplicate API clients and inconsistent data formats between different parts of the frontend.

---

## 1. BUILDER → API ALIGNMENT

### 1.1 Rule Builder Payloads

| Builder | Payload Format | API Endpoint | API Expects | Match? |
|---------|---------------|--------------|-------------|--------|
| SimpleRuleBuilder | {condition_logic, actions, rule_type: 'simple'} | POST /workflows/:id/rules | Same | ✅ |
| CaseRuleBuilder | {trigger_field_id, cases, default_actions, rule_type: 'case_based'} | POST /workflows/:id/rules | Same | ✅ |
| EnterpriseRuleBuilder | {rule_config, validation_type} | POST /workflows/:id/validation-rules | Same | ✅ |
| ValidationRuleBuilder | {validation_type, target_fields, query_conditions, ...} | POST /workflows/:id/validation-rules | Same | ✅ |
| RoutingRuleBuilder | {validation_type: 'field_existence_check', lookup_config, route_config} | POST /workflows/:id/validation-rules | Same | ✅ |

### 1.2 Issues

#### FBA-001: Two different RuleAction type definitions [HIGH]
- **Severity:** High
- **Root Cause:** `types/enterprise-rule-engine.ts` uses `type: ActionType`, `types/workflow.ts` uses `action: string`
- **Impact:** Developers may confuse the two formats when creating or modifying rules
- **Solution:** Standardize on single action format

#### FBA-002: ExecutionMode type mismatch [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Enterprise types define 7 modes, workflow types define 4 modes
- **Impact:** Enterprise engine may receive modes that workflow execution type doesn't include
- **Solution:** Synchronize ExecutionMode types

---

## 2. API → DATABASE ALIGNMENT

### 2.1 API Response vs. Database Schema

| API Field | Database Column | Type Match? | Notes |
|-----------|----------------|-------------|-------|
| WorkflowVersion.validation_rules | validation_rules table | ⚠️ | Typed as `any[]` in frontend |
| WorkflowField.field_type | workflow_fields.field_type (varchar 30) | ✅ | |
| WorkflowField.options | workflow_fields.options (jsonb) | ✅ | |
| WorkflowField.validation_rules | workflow_fields.validation_rules (jsonb) | ✅ | |
| WorkflowExecution.field_states | workflow_executions.field_states (jsonb) | ⚠️ | Frontend type narrower than actual |
| ValidationRule.rule_config | validation_rules.rule_config (jsonb) | ✅ | |
| ValidationRule.expectation | validation_rules.expectation (varchar 20) | ⚠️ | Not included in frontend type |

### 2.2 Issues

#### FBA-003: WorkflowVersion.validation_rules typed as any[] [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Frontend type loses all type safety for validation rules
- **Affected Files:** `frontend/src/types/workflow.ts`
- **Solution:** Define proper ValidationRule type

#### FBA-004: ExecutionPreview.field_states type is narrower than actual [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Frontend type has 3 properties, backend returns 7+
- **Affected Files:** `frontend/src/types/workflow.ts`
- **Solution:** Expand type to match backend response

---

## 3. DATABASE → RUNTIME ENGINE ALIGNMENT

### 3.1 Database Values vs. Engine Expectations

| Database Column | Engine Usage | Format Match? | Notes |
|----------------|-------------|---------------|-------|
| workflow_steps.condition_logic (jsonb) | EnterpriseRuleEngine::evaluateConditions | ✅ | Handles multiple formats |
| workflow_rules.condition_logic (jsonb) | EnterpriseRuleEngine::evaluateConditions | ✅ | |
| workflow_rules.actions (jsonb) | EnterpriseRuleEngine::convertWorkflowActions | ⚠️ | Format conversion needed |
| workflow_rules.cases (jsonb) | EnterpriseRuleEngine::execute (case_based) | ✅ | |
| validation_rules.rule_config (jsonb) | EnterpriseRuleEngine::execute (enterprise) | ✅ | |
| validation_rules.field_effects (jsonb) | EnterpriseRuleEngine::executeActions | ✅ | |
| workflow_fields.condition_logic (jsonb) | VisibilityResolver::isFieldVisible | ✅ | |

### 3.2 Issues

#### FBA-005: Three condition logic formats in database [HIGH]
- **Severity:** High
- **Root Cause:** workflow_steps, workflow_rules, and workflow_fields all store condition_logic but in different formats
- **Impact:** EnterpriseRuleEngine must detect format heuristically
- **Solution:** Standardize on single format with migration

---

## 4. RUNTIME ENGINE → UI RENDERING ALIGNMENT

### 4.1 Field State Shape

| Source | Shape | Properties |
|--------|-------|------------|
| Backend (submitStep response) | `{is_visible, is_required, is_readonly, is_editable, is_locked, field_type, options}` | 7 properties |
| WorkflowExecutionPage | Same as backend | 7 properties |
| FieldStateProvider | `{visible, required, readonly, locked, enabled}` | 5 properties |
| DynamicFieldRenderer | Consumes FieldStateProvider | 5 properties |
| ExecutionContext (types) | `{is_visible, is_required, is_readonly, is_editable, is_locked}` | 5 properties |

#### FBA-006: FieldStateProvider shape does not match backend [HIGH]
- **Severity:** High
- **Root Cause:** FieldStateProvider uses plain names (visible, required), backend uses is_ prefix (is_visible, is_required)
- **Impact:** FieldStateProvider and DynamicFieldRenderer are not wired into main execution page
- **Solution:** Standardize on is_ prefix everywhere or create adapter

#### FBA-007: FieldStateProvider not used in WorkflowExecutionPage [MEDIUM]
- **Severity:** Medium
- **Root Cause:** WorkflowExecutionPage manages field states directly instead of using FieldStateProvider context
- **Impact:** Two parallel field state management systems
- **Solution:** Either wire FieldStateProvider into execution page or remove it

---

## 5. FEE AMOUNT ALIGNMENT

### 5.1 Fee Amount Display Chain

| Stage | Source | Amount Used | Matches Runtime? |
|-------|--------|-------------|-----------------|
| Fee Library Page | OfficialFee.amount | OfficialFee.amount | N/A |
| SimpleRuleBuilder | fee.resolved_amount ?? fee.amount | ⚠️ | ⚠️ |
| CaseRuleBuilder | fee.amount | fee.amount | ❌ |
| EnterpriseRuleBuilder (standard) | fee.amount | fee.amount | ❌ |
| EnterpriseRuleBuilder (case) | fee.resolved_amount ?? fee.amount | ⚠️ | ⚠️ |
| Runtime Execution | FeeEngine::resolveActive() | Resolved amount | ✅ |
| RealTimeFeePanel | calculated_items[].amount | Resolved amount | ✅ |
| Review Summary | calculated_items[].amount | Resolved amount | ✅ |

#### FBA-008: Builder fee amounts do not match runtime [CRITICAL]
- **Severity:** Critical
- **Root Cause:** CaseRuleBuilder and EnterpriseRuleBuilder (standard) display `fee.amount` from fee library, not the resolved amount from `FeeEngine::resolveActive()`
- **Impact:** Users see one amount in the builder but a different amount is charged at runtime
- **Solution:** All builders should call fee resolution API to get the active amount

---

## 6. FIELD EFFECTS ALIGNMENT

### 6.1 Field Effects Chain

```
EnterpriseRuleEngine::executeActions
    ↓ field_effects
WorkflowExecutionService::submitStep
    ↓ transforms to allActions
WorkflowExecutionService::buildFieldStates
    ↓ field_states
WorkflowExecutionController::response
    ↓ JSON
WorkflowExecutionPage::handleApplyFieldEffects
    ↓ updates local state
DynamicFieldRenderer::render
    ↓ applies disabled/required/readonly/hidden
```

#### FBA-009: Field effect transformation loses data [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `WorkflowExecutionService::submitStep` transforms enterprise field_effects to legacy action format
- **Evidence:** Lines 256-284 in WorkflowExecutionService.php
- **Impact:** Some effect properties may be lost during transformation
- **Solution:** Use unified action format throughout

---

## 7. ROUTING ALIGNMENT

### 7.1 Routing Decision Chain

```
ValidationEngine::checkFieldExistence
    ↓ routing decision
EnterpriseRuleEngine::execute
    ↓ routing_decisions
WorkflowExecutionService::submitStep
    ↓ legacy_routing + enterprise_routing
WorkflowExecutionController::response
    ↓ JSON
WorkflowExecutionPage
    ↓ routing decisions
BranchHandler
    ↓ renders block/redirect/mode_switch/warn/confirm
```

#### FBA-010: Two routing response formats [HIGH]
- **Severity:** High
- **Root Cause:** submitStep returns both `legacy_routing` and `enterprise_routing` separately
- **Evidence:** Lines 424-425 in WorkflowExecutionService.php
- **Impact:** Frontend must handle two different routing formats
- **Solution:** Merge into unified routing response

---

## 8. CALCULATION ALIGNMENT

### 8.1 Calculation Display Chain

| Stage | Format | Locale | Decimal Places |
|-------|--------|--------|---------------|
| Backend calculation | BC Math string (e.g., "1000.000") | — | 3 |
| API response | String | — | 3 |
| RealTimeFeePanel | Number.toLocaleString("ar-IQ") | Arabic-IQ | 3 |
| Review Summary | Number.toLocaleString("en-US") | en-US | 3 |
| formatCurrency utility | Number.toLocaleString("ar-IQ") | Arabic-IQ | 3 |

#### FBA-011: Review Summary uses en-US locale [LOW]
- **Severity:** Low
- **Root Cause:** Review Summary uses `en-US` locale while rest of system uses `ar-IQ`
- **Affected Files:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx`
- **Impact:** Inconsistent number formatting
- **Solution:** Use ar-IQ locale everywhere

---

## 9. DUPLICATE API CLIENT

### 9.1 API Client Comparison

| Feature | api/client.ts | services/apiClient.ts |
|---------|--------------|----------------------|
| Base URL | VITE_API_URL or localhost:8000 | VITE_API_URL or localhost:8000 |
| Auth header | Bearer token from localStorage | Bearer token from localStorage |
| Response unwrapping | ✅ Unwraps {success, data} | ❌ Returns raw response |
| Error messages | Arabic | Default axios |
| 401 handling | Auto-redirect to login | ❌ |
| Used by | All pages/hooks | Designer hooks only |

#### FBA-012: Duplicate API clients with different behavior [HIGH]
- **Severity:** High
- **Root Cause:** Two axios instances with different response handling
- **Impact:** Designer API calls get raw responses while other calls get unwrapped data
- **Solution:** Consolidate to single API client

---

## 10. FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 1 |
| High | 5 |
| Medium | 5 |
| Low | 1 |

---

## RECOMMENDED FIXES PRIORITY

1. **FBA-008:** Fix builder fee amounts to match runtime resolution
2. **FBA-001:** Standardize RuleAction type definitions
3. **FBA-006:** Standardize field state shape
4. **FBA-010:** Merge routing response formats
5. **FBA-012:** Consolidate API clients
6. **FBA-005:** Standardize condition logic format
7. **FBA-003:** Define proper ValidationRule type
8. **FBA-004:** Expand ExecutionPreview.field_states type
9. **FBA-009:** Use unified action format throughout
10. **FBA-002:** Synchronize ExecutionMode types
