# RULE ENGINE FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** Complete forensic audit of ALL rule engines

---

## EXECUTIVE SUMMARY

The system contains **THREE rule engines** that overlap significantly:
1. `RuleEngineV2` — Simple + case-based rules (workflow_rules table)
2. `EnterpriseRuleEngine` — Enterprise rules (validation_rules table with rule_config) + workflow rules
3. `ConditionalBranchingEngine` — Case-based rule evaluation (standalone)

This is the most critical architectural issue in the entire codebase. All three engines handle overlapping rule types with duplicated logic, creating a maintenance nightmare and potential for divergent behavior.

---

## 1. RULE TYPE ANALYSIS

### 1.1 All Rule Types

| Rule Type | Storage Table | Engine | Builder | Verified |
|-----------|--------------|--------|---------|----------|
| Simple | workflow_rules | RuleEngineV2 + EnterpriseRuleEngine | SimpleRuleBuilder | ✅ |
| Case-based | workflow_rules | RuleEngineV2 + EnterpriseRuleEngine | CaseRuleBuilder | ✅ |
| Enterprise | validation_rules (rule_config) | EnterpriseRuleEngine | EnterpriseRuleBuilder | ✅ |
| Validation | validation_rules | ValidationEngine + EnterpriseRuleEngine | ValidationRuleBuilder | ✅ |
| Routing | validation_rules (field_existence_check) | ValidationEngine | RoutingRuleBuilder | ✅ |
| Financial | workflow_rules + validation_rules | EnterpriseRuleEngine (set_fee, calculate, apply_discount) | All builders | ✅ |

### 1.2 Critical Issues

#### RE-001: Three overlapping rule engines [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `RuleEngineV2`, `EnterpriseRuleEngine`, and `ConditionalBranchingEngine` all evaluate rules with overlapping functionality
- **Evidence:**
  - `RuleEngineV2::evaluate()` handles simple + case-based rules
  - `EnterpriseRuleEngine::execute()` handles enterprise rules AND workflow rules (simple + case-based)
  - `ConditionalBranchingEngine::evaluateCaseRule()` handles case-based rules independently
- **Impact:** Rules may be evaluated differently depending on which engine processes them
- **Solution:** Consolidate into single `RuleEngine` with strategy pattern for rule types

#### RE-002: EnterpriseRuleEngine double-processes workflow rules [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `EnterpriseRuleEngine::execute()` loads BOTH validation_rules AND workflow_rules, processing them in a single pass
- **Evidence:** Lines 39-50 load enterprise rules, lines 47-50 load workflow rules
- **Impact:** Workflow rules are processed by both RuleEngineV2 (in WorkflowExecutionService) AND EnterpriseRuleEngine, potentially executing twice
- **Solution:** Remove workflow rule processing from EnterpriseRuleEngine or remove RuleEngineV2 from WorkflowExecutionService

---

## 2. RULE LIFECYCLE VERIFICATION

### 2.1 Full Lifecycle: Create → Edit → Save → Reload → Clone → Version Copy

| Operation | Simple | Case-based | Enterprise | Validation | Routing |
|-----------|--------|------------|------------|------------|---------|
| Create | ✅ | ✅ | ✅ | ✅ | ✅ |
| Edit | ✅ | ✅ | ✅ | ✅ | ✅ |
| Save | ✅ | ✅ | ✅ | ✅ | ✅ |
| Reload | ✅ | ✅ | ✅ | ✅ | ✅ |
| Clone | ✅ | ✅ | ⚠️ | ⚠️ | ✅ |
| Version copy | ✅ | ✅ | ⚠️ | ⚠️ | ✅ |

### 2.2 Clone/Version Issues

#### RE-003: Validation rule cloning drops `expectation` column [HIGH]
- **Severity:** High
- **Root Cause:** Version cloning code does not copy `expectation` column
- **Affected Files:** `backend/app/Http/Controllers/Api/WorkflowVersionController.php`
- **Impact:** Cloned validation rules lose their expectation value
- **Solution:** Add `expectation` to clone mapping

#### RE-004: Workflow field cloning drops `inheritance_source` [HIGH]
- **Severity:** High
- **Root Cause:** `replicateVersionContents()` does not copy `inheritance_source`
- **Affected Files:** `backend/app/Http/Controllers/Api/WorkflowVersionController.php`
- **Impact:** Cloned fields default to `'register'` regardless of source
- **Solution:** Add `inheritance_source` to clone mapping

---

## 3. CONDITION EVALUATION

### 3.1 Condition Format Support

| Format | RuleEngineV2 | EnterpriseRuleEngine | Verified |
|--------|-------------|---------------------|----------|
| Simple {field_id, operator, value} | ✅ | ✅ | ✅ |
| Group {type: 'group', logic, conditions} | ❌ | ✅ | ⚠️ |
| ConditionLogic {operator, conditions} | ✅ | ✅ | ✅ |
| Nested groups | ❌ | ✅ (unlimited) | ✅ |

#### RE-005: RuleEngineV2 does not support group conditions [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `RuleEngineV2::evaluateCondition()` only handles {operator, conditions} format, not {type: 'group'}
- **Impact:** Simple rules cannot use nested condition groups
- **Solution:** Add group support or document limitation

### 3.2 Operator Coverage

| Operator | RuleEngineV2 | EnterpriseRuleEngine | Match? |
|----------|-------------|---------------------|--------|
| equals | ✅ (bcCompareEqual) | ✅ (string comparison) | ⚠️ |
| not_equals | ✅ | ✅ | ⚠️ |
| greater_than | ✅ (bcCompare) | ✅ (bccomp) | ✅ |
| less_than | ✅ | ✅ | ✅ |
| contains | ✅ | ✅ | ✅ |
| in | ✅ | ✅ | ✅ |
| is_empty | ✅ | ✅ | ✅ |
| database_exists | ❌ | ✅ | ❌ |
| regex | ❌ | ✅ | ❌ |

#### RE-006: Operator comparison inconsistency [HIGH]
- **Severity:** High
- **Root Cause:** `RuleEngineV2::equals` uses BC math equality, `EnterpriseRuleEngine::equals` uses string comparison `(string) $a === (string) $b`
- **Impact:** `"1.000" === "1"` is false in EnterpriseRuleEngine but true in RuleEngineV2 (after BC normalization)
- **Solution:** Standardize on BC math comparison for all numeric operators

---

## 4. ACTION EXECUTION

### 4.1 Action Handler Coverage

| Action Type | EnterpriseRuleEngine | RuleEngineV2 | ConditionalBranchingEngine |
|-------------|---------------------|--------------|---------------------------|
| set_value | ✅ | ✅ | ✅ |
| override_value | ✅ | ✅ | ❌ |
| calculate | ✅ | ✅ | ✅ |
| set_fee | ✅ | ✅ | ✅ |
| apply_discount | ✅ | ✅ | ✅ |
| show | ✅ | ❌ | ❌ |
| hide | ✅ | ❌ | ❌ |
| set_visibility | ✅ | ✅ | ❌ |
| set_required | ✅ | ✅ | ❌ |
| set_readonly | ✅ | ✅ | ❌ |
| set_editable | ✅ | ✅ | ❌ |
| set_lock | ✅ | ✅ | ❌ |
| unlock | ✅ | ❌ | ❌ |
| set_field_type | ✅ | ✅ | ❌ |
| set_options | ✅ | ✅ | ❌ |
| append_options | ✅ | ❌ | ❌ |
| remove_options | ✅ | ❌ | ❌ |
| clear_value | ✅ | ❌ | ❌ |
| copy_value | ✅ | ❌ | ❌ |
| route_to_step | ✅ | ❌ | ❌ |
| route_to_workflow | ✅ | ❌ | ❌ |
| switch_mode | ✅ | ❌ | ❌ |
| skip_step | ✅ | ❌ | ❌ |
| show_message | ✅ | ❌ | ❌ |
| show_warning | ✅ | ❌ | ❌ |
| show_error | ✅ | ❌ | ❌ |
| show_confirmation | ✅ | ❌ | ❌ |
| generate_reference | ✅ | ❌ | ❌ |
| audit_log | ✅ | ❌ | ❌ |
| pause_execution | ✅ | ❌ | ❌ |
| resume_execution | ✅ | ❌ | ❌ |
| execute_validation | ✅ | ❌ | ❌ |

#### RE-007: Massive action handler duplication [CRITICAL]
- **Severity:** Critical
- **Root Cause:** 35 action types are handled independently in three engines with no shared base
- **Impact:** Adding a new action type requires changes in 3+ places. Bug fixes in one engine are not reflected in others
- **Solution:** Create `ActionExecutor` class with single handler per action type

---

## 5. SERIALIZATION & API TRANSPORT

### 5.1 Rule Serialization

| Component | Serialization | Deserialization | Round-trip? |
|-----------|--------------|-----------------|-------------|
| WorkflowRule | JSON columns (condition_logic, actions, cases, default_actions) | Laravel array cast | ✅ |
| ValidationRule | JSON columns (rule_config, field_effects, etc.) | Laravel array cast | ✅ |
| EnterpriseRule | rule_config JSON | Laravel array cast | ✅ |

### 5.2 API Transport

| Endpoint | Request Format | Response Format | Match? |
|----------|---------------|-----------------|--------|
| POST /workflows/:id/rules | {condition_logic, actions, rule_type} | Same | ✅ |
| POST /workflows/:id/validation-rules | {rule_config, validation_type} | Same | ✅ |
| POST /workflows/:id/enterprise/simulate | {rule_config, values} | {results, financial_trace} | ✅ |

#### RE-008: Two different RuleAction type definitions [HIGH]
- **Severity:** High
- **Root Cause:** `types/enterprise-rule-engine.ts` uses `type: ActionType`, `types/workflow.ts` uses `action: string`
- **Impact:** Frontend developers may confuse the two formats, leading to incorrect payloads
- **Solution:** Standardize on single action format or add clear documentation

---

## 6. FINANCIAL ACTION CORRECTNESS

### 6.1 set_fee Resolution

| Builder | Resolution Method | Matches Runtime? |
|---------|------------------|-----------------|
| SimpleRuleBuilder | Fee library lookup | ⚠️ Shows fee.amount, not resolved_amount |
| CaseRuleBuilder | Fee library lookup | ❌ Shows fee.amount only |
| EnterpriseRuleBuilder | Fee library lookup | ⚠️ Standard: fee.amount, Case: resolved_amount |
| Runtime | FeeEngine::resolveActive() | ✅ Authoritative |

#### RE-009: Builder fee display does not match runtime resolution [HIGH]
- **Severity:** High
- **Root Cause:** CaseRuleBuilder displays `fee.amount` directly from fee library, not the resolved amount from `FeeEngine::resolveActive()`
- **Impact:** If a fee has versions with different amounts, the builder shows the wrong amount
- **Solution:** All builders should call fee resolution API to get the active amount

### 6.2 Discount Calculation

| Engine | Method | Scale Source | Correct? |
|--------|--------|-------------|----------|
| RuleEngineV2 | `calculateDiscount()` | CalculationContext | ✅ |
| EnterpriseRuleEngine | `executeActions` case 'apply_discount' | Hardcoded 3 | ❌ |
| ConditionalBranchingEngine | `resolveAction` case 'apply_discount' | Hardcoded 3 | ❌ |

#### RE-010: Discount calculation uses hardcoded scale [HIGH]
- **Severity:** High
- **Root Cause:** EnterpriseRuleEngine and ConditionalBranchingEngine hardcode `$scale = 3` instead of using `CalculationContext::scale()`
- **Impact:** If scale is changed in context, discount calculations remain at 3 decimal places
- **Solution:** Inject CalculationContext and use `$ctx->scale()`

---

## 7. RULE LIFECYCLE DIAGRAM

```
┌─────────────────────────────────────────────────────────────────────┐
│                        RULE LIFECYCLE                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  CREATE                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────────┐   │
│  │ SimpleRule   │    │ CaseRule     │    │ EnterpriseRule       │   │
│  │ Builder      │    │ Builder      │    │ Builder              │   │
│  └──────┬───────┘    └──────┬───────┘    └──────────┬───────────┘   │
│         │                   │                       │               │
│         ▼                   ▼                       ▼               │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────────┐   │
│  │ workflow_    │    │ workflow_    │    │ validation_rules     │   │
│  │ rules        │    │ rules        │    │ (rule_config IS NOT  │   │
│  │ (rule_type=  │    │ (rule_type=  │    │  NULL)               │   │
│  │  'simple')   │    │  'case_based')│    │                      │   │
│  └──────┬───────┘    └──────┬───────┘    └──────────┬───────────┘   │
│         │                   │                       │               │
│         └───────────────────┼───────────────────────┘               │
│                             ▼                                       │
│                    ┌─────────────────┐                              │
│                    │  SAVE / UPDATE  │                              │
│                    └────────┬────────┘                              │
│                             ▼                                       │
│                    ┌─────────────────┐                              │
│                    │  RELOAD (GET)   │                              │
│                    └────────┬────────┘                              │
│                             ▼                                       │
│                    ┌─────────────────┐                              │
│                    │  CLASSIFY       │                              │
│                    │  (ruleEditor    │                              │
│                    │   Resolver)     │                              │
│                    └────────┬────────┘                              │
│                             ▼                                       │
│              ┌──────────────┼──────────────┐                        │
│              ▼              ▼              ▼                        │
│     ┌────────────┐ ┌────────────┐ ┌──────────────┐                 │
│     │ Simple     │ │ Case-based │ │ Enterprise   │                 │
│     │ RuleEngine │ │ RuleEngine │ │ RuleEngine   │                 │
│     │ (V2)       │ │ (V2)       │ │ (V4)         │                 │
│     └────────────┘ └────────────┘ └──────────────┘                 │
│              │              │              │                        │
│              └──────────────┼──────────────┘                        │
│                             ▼                                       │
│                    ┌─────────────────┐                              │
│                    │  CLONE / VERSION│                              │
│                    │  COPY           │                              │
│                    └────────┬────────┘                              │
│                             ▼                                       │
│                    ┌─────────────────┐                              │
│                    │  ⚠️ DROPPED     │                              │
│                    │  COLUMNS:       │                              │
│                    │  - expectation  │                              │
│                    │  - inheritance_ │                              │
│                    │    source       │                              │
│                    └─────────────────┘                              │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 8. TYPE CORRUPTION DETECTION

### 8.1 Type Corruption Vectors

| Vector | Risk | Evidence |
|--------|------|----------|
| rule_type mismatch | Medium | `ruleEditorResolver` classifies by heuristic, not explicit type |
| Builder mismatch | High | Two different RuleAction type definitions |
| Invalid conversions | Medium | `convertWorkflowActions()` may lose data during format conversion |
| Hidden fallbacks | High | EnterpriseRuleEngine falls back to string comparison for equals |
| Duplicated logic | Critical | Three engines with overlapping functionality |

#### RE-011: convertWorkflowActions may lose data [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `convertWorkflowActions()` copies all keys except `action`, but the target format expects `type` instead of `action`
- **Evidence:** Line 696: `'type' => $act['action'] ?? $act['type'] ?? null`
- **Impact:** If both `action` and `type` exist in source, `type` wins, potentially overriding the intended action
- **Solution:** Explicitly map known fields instead of copying all keys

---

## 9. FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 3 |
| High | 7 |
| Medium | 3 |
| Low | 0 |

---

## RECOMMENDED FIXES PRIORITY

1. **RE-001:** Consolidate three rule engines into one
2. **RE-002:** Remove duplicate workflow rule processing from EnterpriseRuleEngine
3. **RE-007:** Create unified ActionExecutor class
4. **RE-009:** Fix builder fee display to match runtime resolution
5. **RE-010:** Use CalculationContext scale for all discount calculations
6. **RE-003:** Fix validation rule cloning to copy expectation
7. **RE-004:** Fix workflow field cloning to copy inheritance_source
8. **RE-006:** Standardize operator comparison to BC math
9. **RE-008:** Standardize RuleAction type definitions
10. **RE-011:** Fix convertWorkflowActions data loss
