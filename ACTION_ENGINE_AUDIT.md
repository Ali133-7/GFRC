# ACTION ENGINE FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** All action types — builder support, API support, backend support, runtime support, frontend rendering

---

## EXECUTIVE SUMMARY

The system defines 35 action types across multiple engines. While all actions have backend support, there is significant duplication in action handling logic, orphan actions in the type definitions, and missing frontend handlers for some actions.

---

## 1. COMPLETE ACTION MATRIX

| # | Action Type | EnterpriseRuleEngine | RuleEngineV2 | ConditionalBranchingEngine | SimpleRuleBuilder | CaseRuleBuilder | EnterpriseRuleBuilder | ValidationRuleBuilder | Frontend Handler | Backend Handler | Status |
|---|-------------|---------------------|--------------|---------------------------|------------------|-----------------|----------------------|----------------------|-----------------|----------------|--------|
| 1 | set_value | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ Complete |
| 2 | override_value | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 3 | calculate | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ Complete |
| 4 | show | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in RuleEngineV2, ConditionalBranchingEngine |
| 5 | hide | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in RuleEngineV2, ConditionalBranchingEngine |
| 6 | enable | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 7 | disable | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 8 | set_visibility | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 9 | set_required | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 10 | set_optional | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 11 | set_readonly | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 12 | set_editable | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 13 | set_lock | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 14 | unlock | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 15 | set_options | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 16 | append_options | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 17 | remove_options | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 18 | set_field_type | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in ConditionalBranchingEngine |
| 19 | clear_value | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 20 | copy_value | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in builders |
| 21 | set_fee | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ Complete |
| 22 | apply_discount | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ⚠️ Missing in SimpleRuleBuilder |
| 23 | route_to_step | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ✅ | ⚠️ No frontend handler |
| 24 | route_to_workflow | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Partial frontend |
| 25 | switch_mode | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Partial frontend |
| 26 | skip_step | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ | ⚠️ No frontend handler |
| 27 | show_message | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Partial frontend |
| 28 | show_warning | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Partial frontend |
| 29 | show_error | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Partial frontend |
| 30 | show_confirmation | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ Partial frontend |
| 31 | generate_reference | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ No frontend handler |
| 32 | audit_log | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ✅ | ⚠️ No frontend handler |
| 33 | pause_execution | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ No frontend handler |
| 34 | resume_execution | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ No frontend handler |
| 35 | execute_validation | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ⚠️ No frontend handler |

---

## 2. ORPHAN ACTION DETECTION

### 2.1 Actions defined in types but not implemented

| Action Type | Defined In | Implemented? | Status |
|-------------|-----------|-------------|--------|
| send_notification | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |
| create_task | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |
| assign_user | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |
| assign_role | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |
| create_record | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |
| update_record | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |
| delete_record | enterprise-rule-engine.ts (Phase 2) | ❌ | Orphan (TODO) |

**Finding:** 7 Phase 2 action types are defined in frontend types but not implemented in backend. EnterpriseRuleEngine throws `UnimplementedActionException` for these, which is correct fail-fast behavior.

### 2.2 Actions with backend support but no frontend builder

| Action Type | Backend | Frontend Builder | Impact |
|-------------|---------|-----------------|--------|
| enable | ✅ | ❌ | Cannot be created in UI |
| disable | ✅ | ❌ | Cannot be created in UI |
| set_optional | ✅ | ❌ | Cannot be created in UI |
| unlock | ✅ | ❌ | Cannot be created in UI |
| append_options | ✅ | ❌ | Cannot be created in UI |
| remove_options | ✅ | ❌ | Cannot be created in UI |
| clear_value | ✅ | ❌ | Cannot be created in UI |
| copy_value | ✅ | ❌ | Cannot be created in UI |
| route_to_step | ✅ | ❌ | Cannot be created in UI |
| skip_step | ✅ | ❌ | Cannot be created in UI |
| generate_reference | ✅ | ❌ | Cannot be created in UI |
| audit_log | ✅ | ❌ | Cannot be created in UI |
| pause_execution | ✅ | ❌ | Cannot be created in UI |
| resume_execution | ✅ | ❌ | Cannot be created in UI |
| execute_validation | ✅ | ✅ (ValidationRuleBuilder) | ✅ |

**Finding:** 14 actions have backend support but no frontend builder support. These can only be created programmatically or through direct API calls.

---

## 3. DUPLICATE REGISTRATION DETECTION

### 3.1 Action Handler Duplication

| Action | EnterpriseRuleEngine | RuleEngineV2 | ConditionalBranchingEngine | Duplication Count |
|--------|---------------------|--------------|---------------------------|------------------|
| set_value | ✅ | ✅ | ✅ | 3 |
| calculate | ✅ | ✅ | ✅ | 3 |
| set_fee | ✅ | ✅ | ✅ | 3 |
| apply_discount | ✅ | ✅ | ✅ | 3 |
| set_visibility | ✅ | ✅ | ❌ | 2 |
| set_required | ✅ | ✅ | ❌ | 2 |
| set_readonly | ✅ | ✅ | ❌ | 2 |
| set_editable | ✅ | ✅ | ❌ | 2 |
| set_lock | ✅ | ✅ | ❌ | 2 |
| set_field_type | ✅ | ✅ | ❌ | 2 |
| set_options | ✅ | ✅ | ❌ | 2 |
| override_value | ✅ | ✅ | ❌ | 2 |
| show | ✅ | ❌ | ❌ | 1 |
| hide | ✅ | ❌ | ❌ | 1 |
| ... (22 more) | ✅ | ❌ | ❌ | 1 |

**Finding:** 12 action types are duplicated across 2-3 engines. This is the most critical architectural issue in the action system.

---

## 4. DEAD CODE DETECTION

### 4.1 Dead Code Candidates

| Code | Location | Reason |
|------|----------|--------|
| `RuleEngineV2::evaluate()` | Not called directly | EnterpriseRuleEngine handles all rule evaluation |
| `RuleEngineV2::evaluateCaseRule()` | Duplicated | EnterpriseRuleEngine has its own case evaluation |
| `RuleEngineV2::resolveAction()` | Duplicated | EnterpriseRuleEngine has its own action resolution |
| `ConditionalBranchingEngine::resolveAction()` | Duplicated | Same logic as EnterpriseRuleEngine |
| `ConditionalBranchingEngine::caseMatches()` | Duplicated | Same logic as EnterpriseRuleEngine |

**Finding:** `RuleEngineV2` is injected into `WorkflowExecutionService` but its `evaluate()` method is never called directly. `EnterpriseRuleEngine::execute()` handles all rule evaluation including workflow rules. This makes `RuleEngineV2::evaluate()` dead code.

---

## 5. MISSING HANDLERS

### 5.1 Frontend Missing Handlers

| Action | Backend Handler | Frontend Handler | Missing |
|--------|----------------|-----------------|---------|
| route_to_step | ✅ | ❌ | Yes |
| skip_step | ✅ | ❌ | Yes |
| generate_reference | ✅ | ❌ | Yes |
| audit_log | ✅ | ❌ | Yes |
| pause_execution | ✅ | ❌ | Yes |
| resume_execution | ✅ | ❌ | Yes |

### 5.2 Backend Missing Handlers

All 35 action types have backend handlers in `EnterpriseRuleEngine::executeActions()`. ✅

---

## 6. BUILDER SUPPORT MATRIX

### 6.1 Builder Action Support

| Action | SimpleRuleBuilder | CaseRuleBuilder | EnterpriseRuleBuilder | ValidationRuleBuilder |
|--------|------------------|-----------------|----------------------|----------------------|
| set_value | ✅ | ✅ | ✅ | ❌ |
| override_value | ❌ | ✅ | ✅ | ❌ |
| calculate | ✅ | ✅ | ✅ | ❌ |
| show | ✅ | ✅ | ✅ | ❌ |
| hide | ✅ | ✅ | ✅ | ❌ |
| set_required | ✅ | ❌ | ✅ | ❌ |
| set_readonly | ✅ | ❌ | ✅ | ❌ |
| set_fee | ✅ | ✅ | ✅ | ❌ |
| apply_discount | ❌ | ✅ | ✅ | ❌ |
| set_lock | ❌ | ✅ | ✅ | ❌ |
| set_editable | ❌ | ✅ | ✅ | ❌ |
| set_field_type | ❌ | ✅ | ✅ | ❌ |
| set_options | ❌ | ✅ | ✅ | ❌ |
| skip_step | ❌ | ✅ | ✅ | ❌ |
| All 35 types | 7 | 13 | 35 | 0 |

---

## 7. API SUPPORT MATRIX

### 7.1 API Endpoints for Actions

| Endpoint | Method | Action Support |
|----------|--------|---------------|
| POST /workflows/:id/rules | CRUD | Simple + case-based actions |
| POST /workflows/:id/validation-rules | CRUD | Enterprise actions via rule_config |
| POST /workflows/:id/enterprise/simulate | POST | All 35 action types (simulation) |
| POST /workflow-executions/:id/step | PUT | All action types (execution) |
| POST /workflow-executions/:id/preview | POST | All action types (preview) |

**Finding:** All action types are supported through the API. ✅

---

## 8. RUNTIME SUPPORT MATRIX

### 8.1 Runtime Action Execution

| Phase | Actions Supported | Engine |
|-------|------------------|--------|
| Step submission | All 35 | EnterpriseRuleEngine |
| Preview | All 35 | EnterpriseRuleEngine |
| Complete | N/A (no actions) | — |
| Cancel | N/A (no actions) | — |

**Finding:** All action types are supported at runtime. ✅

---

## 9. FRONTEND RENDERING MATRIX

### 9.1 Action Effect Rendering

| Effect Type | WorkflowExecutionPage | BranchHandler | RealTimeFeePanel |
|-------------|----------------------|---------------|-----------------|
| hide | ✅ | ❌ | ❌ |
| show | ✅ | ❌ | ❌ |
| set_value | ✅ | ❌ | ❌ |
| set_required | ✅ | ❌ | ❌ |
| set_readonly | ✅ | ❌ | ❌ |
| set_editable | ✅ | ❌ | ❌ |
| set_lock | ✅ | ❌ | ❌ |
| unlock | ✅ | ❌ | ❌ |
| set_visibility | ✅ | ❌ | ❌ |
| set_optional | ✅ | ❌ | ❌ |
| set_field_type | ✅ | ❌ | ❌ |
| set_options | ✅ | ❌ | ❌ |
| set_fee | ✅ | ❌ | ✅ |
| apply_discount | ✅ | ❌ | ✅ |
| calculate | ✅ | ❌ | ✅ |
| block | ❌ | ✅ | ❌ |
| redirect | ❌ | ✅ | ❌ |
| mode_switch | ❌ | ✅ | ❌ |
| warn | ❌ | ✅ | ❌ |
| confirm | ❌ | ✅ | ❌ |

---

## 10. FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 1 |
| High | 3 |
| Medium | 5 |
| Low | 2 |

### Critical
- **AE-001:** 12 action types duplicated across 3 engines with no shared base

### High
- **AE-002:** RuleEngineV2::evaluate() is dead code
- **AE-003:** 14 actions have backend support but no frontend builder
- **AE-004:** set_value can modify locked fields

### Medium
- **AE-005:** 7 Phase 2 action types defined but not implemented
- **AE-006:** 6 actions have no frontend rendering handler
- **AE-007:** VisibilityResolver disable action conflates disabled with hidden
- **AE-008:** SimpleRuleBuilder missing apply_discount
- **AE-009:** CaseRuleBuilder missing set_required, set_readonly

### Low
- **AE-010:** ValidationRuleBuilder has no action support (only field effects)
- **AE-011:** EnterpriseRuleBuilder is the only builder with all 35 actions

---

## RECOMMENDED FIXES PRIORITY

1. **AE-001:** Create unified ActionExecutor class
2. **AE-002:** Remove RuleEngineV2::evaluate() or integrate it properly
3. **AE-003:** Add missing builders for orphan actions
4. **AE-004:** Prevent rule actions from modifying locked fields
5. **AE-005:** Implement or remove Phase 2 action types
6. **AE-006:** Add frontend handlers for missing actions
7. **AE-007:** Fix VisibilityResolver disable action
8. **AE-008:** Add apply_discount to SimpleRuleBuilder
9. **AE-009:** Add set_required, set_readonly to CaseRuleBuilder
