# SECURITY FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** SQL execution, dynamic expressions, routing, workflow execution APIs, permissions

---

## EXECUTIVE SUMMARY

The system has **4 critical security vulnerabilities** that must be resolved before production deployment. The most severe are SQL injection through validation rules, complete lack of authorization on workflow execution APIs, and privilege escalation through role management.

---

## 1. SQL INJECTION ANALYSIS

### 1.1 SQL Execution Points

| Location | Input Source | Sanitization | Risk |
|----------|-------------|--------------|------|
| ValidationEngine::checkSql() | validation_rules.sql_query (admin-configured) | DB::getPdo()->quote() for values | ⚠️ |
| ValidationEngine::checkQueryBuilder() | validation_rules.query_conditions (admin-configured) | Parameterized queries | ✅ |
| ValidationEngine::checkCrossRegister() | Dynamic search with permission gating | Parameterized queries | ✅ |
| ValidationEngine::checkDynamicSearch() | Dynamic search with permission gating | Parameterized queries | ✅ |
| DynamicOptionSource::resolveFromDatabase() | option_source_config (admin-configured) | isSafeIdentifier() for column names | ⚠️ |
| ConditionalValidationEngine::validateRegex() | validation_rules parameter | None | ❌ |

### 1.2 Issues

#### SEC-001: SQL injection through validation_rules.sql_query [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `ValidationEngine::checkSql()` executes raw SQL from database configuration
- **Evidence:** Line ~600 in ValidationEngine.php: `DB::select($rule->sql_query, $bindings)`
- **Impact:** Any admin who can edit validation rules can execute arbitrary SQL
- **Attack Vector:** Create validation rule with SQL: `SELECT password FROM users; --`
- **Solution:** Remove raw SQL validation capability or implement strict allowlist of permitted queries

#### SEC-002: Regex injection through validation_rules [HIGH]
- **Severity:** High
- **Root Cause:** `ConditionalValidationEngine::validateRegex()` injects user-provided regex directly into preg_match
- **Evidence:** `preg_match("/{$param}/", $value)`
- **Impact:** Malicious regex can cause ReDoS (Regular Expression Denial of Service) or break the regex
- **Attack Vector:** Set regex to `(a+)+$` with input `aaaaaaaaaaaaaaaa!` → catastrophic backtracking
- **Solution:** Validate regex syntax and set timeout/limit on preg_match

#### SEC-003: DynamicOptionSource SQL injection surface [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `resolveFromDatabase()` accepts configurable where clauses
- **Impact:** While column names are validated via `isSafeIdentifier()`, the where clause structure is user-configurable
- **Solution:** Restrict to simple equality conditions only

---

## 2. DYNAMIC EXPRESSION ANALYSIS

### 2.1 Expression Evaluation Points

| Location | Expression Source | Sandboxing | Risk |
|----------|------------------|------------|------|
| FeeEngine::calculate() | workflow_fields.calculation_formula | Shunting-Yard (safe) | ✅ |
| FormulaEvaluator::evaluate() | Formula string | Token blacklist | ⚠️ |
| ComputedFieldEngine::computeValue() | workflow_fields.computed_formula | Delegates to FeeEngine | ✅ |
| EnterpriseRuleEngine::calculateExpression() | Formula string | Delegates to FeeEngine | ✅ |

### 2.2 Issues

#### SEC-004: FormulaEvaluator token blacklist is overly aggressive [HIGH]
- **Severity:** High
- **Root Cause:** Forbidden token check uses `stripos()` for substring matching
- **Impact:** Variable names containing `new`, `include`, `require`, `eval`, `exec`, `system` are rejected
- **Examples:** `new_value`, `include_notes`, `require_approval` all blocked
- **Solution:** Use token-level matching instead of substring matching

#### SEC-005: FormulaEvaluator uses float arithmetic [HIGH]
- **Severity:** High
- **Root Cause:** `evaluate()` converts result to `(float)` — see FE-005
- **Impact:** Floating-point imprecision in financial calculations
- **Solution:** Remove or rewrite to use BC Math

---

## 3. ROUTING SECURITY ANALYSIS

### 3.1 Routing Attack Vectors

| Vector | Endpoint | Authorization | Risk |
|--------|----------|--------------|------|
| Redirect to arbitrary workflow | POST /workflow-executions/:id/redirect | None | Critical |
| Switch execution mode | POST /workflow-executions/:id/mode | None | High |
| Pause execution | POST /workflow-executions/:id/pause | None | High |
| Resume execution | POST /workflow-executions/:id/resume | None | High |
| Save draft | POST /workflow-executions/:id/draft | None | Medium |

### 3.2 Issues

#### SEC-006: No authorization on workflow execution routing [CRITICAL]
- **Severity:** Critical
- **Root Cause:** All workflow execution endpoints lack authorization
- **Impact:** Any authenticated user can redirect, pause, resume, or switch mode on any execution
- **Solution:** Add policy-based authorization to all execution endpoints

---

## 4. WORKFLOW EXECUTION API SECURITY

### 4.1 Authorization Gap

| Endpoint | Method | Authorization | Risk |
|----------|--------|--------------|------|
| GET /workflow-executions | index | None | High |
| POST /workflow-executions | store | None | Critical |
| GET /workflow-executions/:id | show | None | High |
| PUT /workflow-executions/:id/step | submitStep | None | Critical |
| POST /workflow-executions/:id/complete | complete | None | Critical |
| POST /workflow-executions/:id/cancel | cancel | None | Critical |
| POST /workflow-executions/:id/preview | preview | None | High |
| POST /workflow-executions/:id/redirect | redirect | None | Critical |
| POST /workflow-executions/:id/mode | switchMode | None | High |
| POST /workflow-executions/:id/pause | pause | None | High |
| POST /workflow-executions/:id/resume | resume | None | High |
| POST /workflow-executions/:id/draft | saveDraft | None | Medium |
| GET /workflow-executions/:id/branch | getBranchState | None | Medium |

### 4.2 Issues

#### SEC-007: Complete lack of authorization on workflow execution APIs [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `WorkflowExecutionController` has no `authorize()` calls
- **Impact:** Any authenticated user can:
  - Start any workflow execution
  - Submit data to any execution
  - Complete any execution and generate receipts
  - Cancel any execution
  - Redirect any execution to another workflow
- **Solution:** Add policy-based authorization with row-level access control

---

## 5. PERMISSIONS ANALYSIS

### 5.1 Permission Coverage

| Resource | Policy Exists | Permissions Defined | Enforced? |
|----------|--------------|-------------------|-----------|
| Users | ✅ | view-users, manage-users | ✅ |
| Receipts | ✅ | view-receipt, create-receipt, issue-receipt, cancel-receipt, revise-receipt, print-receipt | ✅ |
| Workflows | ✅ | view-receipt, manage-settings | ✅ |
| Registers | ✅ | view-registers, manage-registers | ✅ |
| TransactionTemplates | ✅ | view-receipt, create-receipt, manage-registers, manage-settings | ✅ |
| SystemReset | ✅ | system.reset | ✅ |
| WorkflowExecutions | ❌ | None | ❌ |
| Roles | ❌ | None | ❌ |
| OfficialFees | ❌ | None | ❌ |
| Templates | ❌ | None | ❌ |
| Reports | ❌ | None | ❌ |
| Settings | ❌ | None | ❌ |
| Backups | ❌ | None | ❌ |
| AuditLogs | ❌ | None | ❌ |
| HelpArticles | ❌ | None | ❌ |
| ValidationRules | ❌ | None | ❌ |

### 5.2 Issues

#### SEC-008: Role management has no authorization [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `RoleController` has no authorization on any method
- **Impact:** Any authenticated user can create roles and assign permissions, including super_admin
- **Attack Vector:** Create role with all permissions → privilege escalation
- **Solution:** Add policy-based authorization requiring manage-users or manage-roles permission

#### SEC-009: Backup management has no authorization [HIGH]
- **Severity:** High
- **Root Cause:** `BackupController` has no authorization
- **Impact:** Any authenticated user can create, download, restore, or delete backups
- **Solution:** Add policy requiring manage-backups permission

#### SEC-010: Settings management has no authorization [HIGH]
- **Severity:** High
- **Root Cause:** `SettingController` has no authorization
- **Impact:** Any authenticated user can read/write all system settings
- **Solution:** Add policy requiring manage-settings permission

#### SEC-011: Report access has no authorization [HIGH]
- **Severity:** High
- **Root Cause:** `ReportController` has no authorization
- **Impact:** Any authenticated user can view all financial reports
- **Solution:** Add policy requiring view-reports permission

#### SEC-012: Audit log access has no authorization [HIGH]
- **Severity:** High
- **Root Cause:** `AuditLogController` has no authorization
- **Impact:** Any authenticated user can view all audit logs
- **Solution:** Add policy requiring view-audit-logs permission

---

## 6. PRIVILEGE ESCALATION ANALYSIS

### 6.1 Escalation Vectors

| Vector | Method | Current Guard | Bypassable? |
|--------|--------|--------------|-------------|
| Role creation | RoleController::store | None | ✅ |
| Permission assignment | RoleController::updatePermissions | None | ✅ |
| User permission update | UserController::updatePermissions | manage-users | ❌ |
| System reset | SystemController::reset | system.reset | ❌ |
| super_admin bypass | Gate::before | Role check | ❌ |

### 6.2 Issues

#### SEC-013: super_admin bypasses all permissions [MEDIUM]
- **Severity:** Medium
- **Root Cause:** `Gate::before` returns true for super_admin role
- **Impact:** super_admin bypasses ALL permission checks, including row-level checks
- **Solution:** Document this behavior and ensure super_admin role is tightly controlled

---

## 7. UNAUTHORIZED EXECUTION ANALYSIS

### 7.1 Execution Tampering Vectors

| Vector | Method | Impact | Prevention |
|--------|--------|--------|-----------|
| Modify another user's execution | PUT /workflow-executions/:id/step | Data corruption | None |
| Complete another user's execution | POST /workflow-executions/:id/complete | Fraudulent receipt | None |
| Cancel another user's execution | POST /workflow-executions/:id/cancel | Service disruption | None |
| Redirect execution to different workflow | POST /workflow-executions/:id/redirect | Workflow bypass | None |
| Tamper with execution values | PUT /workflow-executions/:id/step | Financial manipulation | None |

### 7.2 Issues

#### SEC-014: No row-level access control on executions [CRITICAL]
- **Severity:** Critical
- **Root Cause:** No policy checks execution ownership
- **Impact:** Any user can modify any execution regardless of ownership
- **Solution:** Add row-level check: user must own execution or have manage-workflows permission

---

## 8. WORKFLOW TAMPERING ANALYSIS

### 8.1 Tampering Vectors

| Vector | Method | Impact | Prevention |
|--------|--------|--------|-----------|
| Modify published workflow | PUT /workflows/:id | Rule manipulation | WorkflowPolicy |
| Modify workflow version | PUT /workflows/:id/versions/:version | Execution manipulation | WorkflowPolicy |
| Modify fee amounts | PUT /official-fees/:id | Financial manipulation | None |
| Modify fee versions | POST /official-fees/:id/versions | Financial manipulation | None |

### 8.2 Issues

#### SEC-015: Fee management has no authorization [HIGH]
- **Severity:** High
- **Root Cause:** `OfficialFeeController` has no authorization on CRUD operations
- **Impact:** Any authenticated user can create, modify, or delete fee amounts
- **Solution:** Add policy requiring manage-fees permission

#### SEC-016: FeeVersionController::store calls authorize on unregistered policy [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Calls `$this->authorize('update', $fee)` but no `OfficialFeePolicy` is registered
- **Impact:** Will always deny (or pass for super_admin)
- **Solution:** Register OfficialFeePolicy or remove authorize call

---

## FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 5 |
| High | 8 |
| Medium | 3 |
| Low | 0 |

---

## RECOMMENDED FIXES PRIORITY

1. **SEC-001:** Remove or sandbox raw SQL validation
2. **SEC-007:** Add authorization to all workflow execution endpoints
3. **SEC-006:** Add authorization to workflow execution routing
4. **SEC-008:** Add authorization to role management
5. **SEC-014:** Add row-level access control on executions
6. **SEC-002:** Fix regex injection vulnerability
7. **SEC-004:** Fix FormulaEvaluator token blacklist
8. **SEC-015:** Add authorization to fee management
9. **SEC-009:** Add authorization to backup management
10. **SEC-010:** Add authorization to settings management
