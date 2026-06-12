# DATABASE INTEGRITY FORENSIC AUDIT

**Date:** 2026-06-10
**Auditor:** Principal Workflow Systems Architect
**Scope:** Migrations, foreign keys, indexes, nullable fields, JSON columns

---

## EXECUTIVE SUMMARY

The database contains 60 migrations creating 35+ tables. The schema is generally well-structured but contains **10 missing foreign key constraints**, **13 missing indexes**, **3 dangerous delete patterns**, and **5 version corruption risks**.

---

## 1. MIGRATIONS ANALYSIS

### 1.1 Migration Summary

| Phase | Migrations | Tables Created | Status |
|-------|-----------|---------------|--------|
| Core Infrastructure | 4 | users, sessions, cache, jobs, personal_access_tokens | ✅ |
| Domain (Phase 1) | 12 | registers, register_fields, receipts, receipt_items, receipt_revisions, settings, receipt_templates, template_elements, template_styles | ✅ |
| Fees & Templates (Phase 2) | 6 | official_fee_categories, official_fees, transaction_templates, transaction_template_fields, template_rules | ✅ |
| Workflow Engine | 11 | workflows, workflow_versions, workflow_steps, workflow_fields, workflow_rules, workflow_executions, fee_versions, receipt_calculation_snapshots | ✅ |
| Event Ledger | 4 | workflow_execution_events, receipt_events, idempotency_keys, PostgreSQL triggers | ✅ |
| Enterprise Phase | 15 | validation_rules, help_articles, records, workflow_routing_log, field_state_history | ✅ |
| Cleanup | 4 | Column drops, data migrations, permission seeds | ✅ |
| Final | 4 | validation_rules.alter (expectation) | ✅ |

### 1.2 Issues

#### DB-001: PostgreSQL triggers silently skipped on other databases [HIGH]
- **Severity:** High
- **Root Cause:** Migration `2026_06_01_000003` creates PostgreSQL triggers blocking UPDATE/DELETE on event ledger tables
- **Affected Files:** Migration `2026_06_01_000003`
- **Impact:** On SQLite or MySQL, append-only guarantees are not enforced at database level
- **Solution:** Add equivalent constraints for other databases or document PostgreSQL requirement

#### DB-002: transaction_templates.sections uses json instead of jsonb [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Migration `2026_05_31_000018` uses `json` instead of `jsonb`
- **Impact:** No indexing capability on sections column
- **Solution:** Change to jsonb (PostgreSQL) or document limitation

---

## 2. FOREIGN KEY ANALYSIS

### 2.1 Missing Foreign Key Constraints

| Table | Column | Should Reference | Risk |
|-------|--------|-----------------|------|
| activity_log | workflow_version_id | workflow_versions.id | Orphaned audit entries |
| activity_log | execution_id | workflow_executions.id | Orphaned audit entries |
| workflow_routing_log | from_step_id | workflow_steps.id | Orphaned routing entries |
| workflow_routing_log | trigger_rule_id | workflow_rules.id or validation_rules.id | Orphaned routing entries |
| field_state_history | field_id | workflow_fields.id | Orphaned history entries |
| field_state_history | rule_id | workflow_rules.id or validation_rules.id | Orphaned history entries |
| workflow_fields | parent_field_id | workflow_fields.id (self-ref) | Orphaned parent references |
| workflow_rules | trigger_field_id | workflow_fields.id or register_fields.id | Orphaned trigger references |
| validation_rules | trigger_field_id | workflow_fields.id or register_fields.id | Orphaned trigger references |
| idempotency_keys | entity_id | Generic (workflow_executions.id or receipts.id) | Intentional |

### 2.2 Issues

#### DB-003: 10 missing foreign key constraints [HIGH]
- **Severity:** High
- **Root Cause:** Many UUID columns reference other tables but have no FK constraints
- **Impact:** Orphaned records can be created if referenced records are deleted
- **Solution:** Add FK constraints with appropriate onDelete behavior

#### DB-004: template_rules trigger_field_id and target_field_id have no onDelete [HIGH]
- **Severity:** High
- **Root Cause:** Migration `2026_05_31_000017` creates FKs without onDelete
- **Impact:** Cannot delete a register_field if referenced by any template rule (RESTRICT)
- **Solution:** Add `nullOnDelete` or `cascade` based on business requirements

---

## 3. INDEX ANALYSIS

### 3.1 Missing Indexes

| Table | Column(s) | Query Pattern | Impact |
|-------|-----------|--------------|--------|
| receipts | status | WHERE status = ? | Full table scan on receipt listing |
| receipts | created_by | WHERE created_by = ? | Full table scan on user receipt filtering |
| workflow_executions | workflow_version_id | WHERE workflow_version_id = ? | Full table scan on version execution listing |
| workflow_executions | started_by | WHERE started_by = ? | Full table scan on user execution listing |
| workflow_executions | receipt_id | WHERE receipt_id = ? | Full table scan on receipt lookup |
| official_fees | is_active + effective_from/to | WHERE is_active AND effective_from <= ? | Full table scan on fee resolution |
| fee_versions | fee_id + effective_from + effective_to | WHERE fee_id = ? AND effective_from <= ? | Full table scan on version resolution |
| validation_rules | workflow_version_id + is_active | WHERE workflow_version_id = ? AND is_active = true | Full table scan on rule loading |
| records | record_number | WHERE record_number = ? | Full table scan on record lookup |
| help_articles | category | WHERE category = ? | Full table scan on help article filtering |
| template_rules | trigger_field_id | WHERE trigger_field_id = ? | Full table scan on rule lookup |
| template_rules | target_field_id | WHERE target_field_id = ? | Full table scan on rule lookup |
| register_fields | validation_rules | WHERE validation_rules LIKE ? | Full table scan on validation rule lookup |

### 3.2 Issues

#### DB-005: 13 missing indexes [HIGH]
- **Severity:** High
- **Root Cause:** Frequently queried columns lack indexes
- **Impact:** Full table scans on large datasets
- **Solution:** Add indexes for all frequently queried columns

---

## 4. NULLABLE FIELD ANALYSIS

### 4.1 Nullable Fields Risk Assessment

| Table | Nullable Column | Business Impact | Risk |
|-------|----------------|----------------|------|
| users | email | Authentication | Low (username is unique) |
| workflows | created_by | Audit trail | Medium |
| workflow_versions | published_by | Audit trail | Medium |
| workflow_steps | condition_logic | Step visibility | Low (defaults to visible) |
| workflow_fields | register_field_id | Field identification | High (custom fields) |
| workflow_fields | step_id | Step association | Medium |
| workflow_executions | receipt_id | Receipt linkage | Low (set on completion) |
| workflow_executions | completed_at | Completion timestamp | Low (set on completion) |
| workflow_executions | cancelled_at | Cancellation timestamp | Low (set on cancellation) |
| workflow_executions | cancel_reason | Cancellation reason | Medium |
| receipts | approved_by | Approval audit | Medium |
| receipts | qr_payload | QR code | Medium |
| receipts | printed_at | Print audit | Low |
| receipts | cancelled_at | Cancellation audit | Low |
| receipts | cancel_reason | Cancellation reason | Medium |
| fee_versions | effective_to | Version expiry | Low (null = no expiry) |
| fee_versions | change_reason | Audit trail | Medium |
| fee_versions | created_by | Audit trail | Medium |
| validation_rules | target_register_id | Cross-register validation | Low (null = same register) |
| validation_rules | sql_query | SQL validation | Low (null = not SQL type) |
| validation_rules | expectation | Validation expectation | Medium |

### 4.2 Issues

#### DB-006: workflow_fields.register_field_id nullable breaks field identification [HIGH]
- **Severity:** High
- **Root Cause:** Custom fields have no register_field_id, requiring `custom_<id>` convention
- **Impact:** Any code that assumes register_field_id exists will fail for custom fields
- **Solution:** Document convention and add null checks everywhere

---

## 5. JSON COLUMN ANALYSIS

### 5.1 JSON Column Usage

| Table | JSON Columns | Count | Indexed? |
|-------|-------------|-------|----------|
| workflow_fields | condition_logic, options, validation_rules, conditional_validation_rules, cross_field_validation_rules, computed_dependencies, cascade_config | 7 | ❌ |
| workflow_rules | condition_logic, actions, cases, default_actions | 4 | ❌ |
| workflow_executions | values_snapshot, calculated_items, branch_state, routing_history, preserved_values, state_mapping, field_states, rule_results, validation_results, routing_decisions, financial_trace | 11 | ❌ |
| workflow_execution_events | event_payload, calculated_items, fee_snapshot, context_snapshot | 4 | ❌ |
| receipt_events | before_state, after_state, fee_snapshot, context_snapshot | 4 | ❌ |
| validation_rules | target_fields, trigger_conditions, query_conditions, route_config, lookup_config, field_effects, rule_config | 7 | ❌ |
| records | data | 1 | ❌ |

### 5.2 Issues

#### DB-007: No GIN indexes on JSON columns [MEDIUM]
- **Severity:** Medium
- **Root Cause:** PostgreSQL supports GIN indexes on jsonb columns for efficient querying
- **Impact:** JSON column queries require full table scans
- **Solution:** Add GIN indexes on frequently queried JSON columns

#### DB-008: register_fields.validation_rules is varchar(500), not jsonb [HIGH]
- **Severity:** High
- **Root Cause:** Migration `2025_01_01_000003` creates validation_rules as varchar(500)
- **Impact:** Long validation rule arrays will be truncated. Laravel array cast will JSON-encode/decode but column may truncate
- **Solution:** Alter column to jsonb

---

## 6. ORPHAN RECORD DETECTION

### 6.1 Potential Orphan Scenarios

| Parent Table | Child Table | Orphan Risk | Prevention |
|-------------|-------------|-------------|------------|
| workflows | workflow_versions | Low (FK cascade) | ✅ |
| workflow_versions | workflow_steps | Low (FK cascade) | ✅ |
| workflow_versions | workflow_fields | Low (FK cascade) | ✅ |
| workflow_versions | workflow_rules | Low (FK cascade) | ✅ |
| workflow_versions | validation_rules | Low (FK cascade) | ✅ |
| workflow_versions | workflow_executions | Medium (FK no cascade) | ⚠️ |
| registers | register_fields | Low (FK cascade) | ✅ |
| registers | receipts | Low (FK no cascade) | ⚠️ |
| receipts | receipt_items | Low (FK cascade) | ✅ |
| receipts | receipt_revisions | Low (FK cascade) | ✅ |
| receipts | receipt_events | Low (FK cascade) | ✅ |
| official_fees | fee_versions | Low (FK cascade) | ✅ |
| workflow_executions | workflow_execution_events | Low (FK cascade) | ✅ |

### 6.2 Issues

#### DB-009: workflow_executions FK to workflow_version_id has no cascade [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Migration `2026_05_31_000024` creates FK without cascade
- **Impact:** Cannot delete workflow_version if executions exist
- **Solution:** Document intentional behavior or add cascade with archival

#### DB-010: receipts FK to register_id has no cascade [MEDIUM]
- **Severity:** Medium
- **Root Cause:** Migration `2025_01_01_000004` creates FK without cascade
- **Impact:** Cannot delete register if receipts exist
- **Solution:** Document intentional behavior or add cascade with archival

---

## 7. DANGEROUS DELETE PATTERNS

### 7.1 Delete Pattern Analysis

| Pattern | Location | Risk | Impact |
|---------|----------|------|--------|
| SystemController::reset() | Deletes all data including event ledgers | Critical | Destroys audit trail |
| SystemController::import() | Force-deletes then re-inserts | Critical | Same as reset |
| Workflow soft delete | Does not cascade to versions | Medium | Orphaned versions |
| RegisterField soft delete | FK to receipt_items is hard | High | Receipt items reference soft-deleted fields |
| Receipt soft delete | Cascades to receipt_items, receipt_events | Medium | Events are soft-deleted too |

### 7.2 Issues

#### DB-011: System reset attempts to delete append-only tables [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `SystemController::reset()` calls `WorkflowExecutionEvent::query()->delete()` which triggers model-level guard
- **Impact:** Reset will always fail, leaving partial data
- **Solution:** Remove reset capability or implement proper archival

#### DB-012: RegisterField soft delete breaks receipt_items FK [HIGH]
- **Severity:** Hard
- **Root Cause:** receipt_items.field_id is a hard FK to register_fields.id
- **Impact:** Soft-deleting a register field leaves receipt_items referencing a soft-deleted record
- **Solution:** Store field name/label as snapshot (already done) and consider removing FK

---

## 8. VERSION CORRUPTION RISKS

### 8.1 Version Corruption Vectors

| Vector | Table | Risk | Evidence |
|--------|-------|------|----------|
| Multiple active versions | workflow_versions | High | No partial unique index on (workflow_id, status='active') |
| Fee version overlap | fee_versions | High | Model-level check is not atomic |
| Cloned fields lose inheritance_source | workflow_fields | Medium | replicateVersionContents() missing column |
| Cloned rules lose expectation | validation_rules | Medium | Replicate missing column |
| publish() writes to dropped column | workflows | Critical | current_version column dropped but still referenced |

### 8.2 Issues

#### DB-013: No database-level constraint for single active version [HIGH]
- **Severity:** High
- **Root Cause:** Multiple workflow versions can have status='active' for the same workflow
- **Impact:** Race condition could result in two active versions
- **Solution:** Add partial unique index: `CREATE UNIQUE INDEX ON workflow_versions (workflow_id) WHERE status = 'active'`

#### DB-014: WorkflowVersion::publish() writes to dropped column [CRITICAL]
- **Severity:** Critical
- **Root Cause:** `current_version` column was dropped but `publish()` still references it
- **Impact:** Publishing workflow versions always fails with SQL error
- **Solution:** Remove the current_version update from publish()

---

## FINDINGS SUMMARY

| Severity | Count |
|----------|-------|
| Critical | 2 |
| High | 8 |
| Medium | 5 |
| Low | 0 |

---

## RECOMMENDED FIXES PRIORITY

1. **DB-014:** Fix WorkflowVersion::publish() dropped column reference
2. **DB-011:** Fix or remove system reset
3. **DB-001:** Document PostgreSQL requirement or add cross-database triggers
4. **DB-003:** Add missing foreign key constraints
5. **DB-005:** Add missing indexes
6. **DB-013:** Add partial unique index for single active version
7. **DB-008:** Change register_fields.validation_rules to jsonb
8. **DB-004:** Add onDelete behavior for template_rules FKs
9. **DB-006:** Document custom field convention
10. **DB-012:** Fix RegisterField soft delete impact on receipt_items
