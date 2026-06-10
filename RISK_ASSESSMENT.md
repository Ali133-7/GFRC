# GFRC v2 Risk Assessment

**Date:** 2026-06-07  
**Scope:** Enterprise Workflow Engine Refactor

---

## Risk Matrix

| # | Risk | Probability | Impact | Mitigation | Status |
|---|------|-------------|--------|------------|--------|
| 1 | `eval()` removal breaks existing formulas | Medium | High | `FormulaEvaluator` whitelist + pre-migration audit | Mitigated |
| 2 | Temporal overlap constraint rejects valid fee versions | Low | High | SQLite/PostgreSQL parity tested; `FeeVersionOverlapTest` covers edge cases | Mitigated |
| 3 | Race condition still possible under extreme concurrency | Low | Critical | `lockForUpdate()` + optimistic locking + affected-rows check | Mitigated |
| 4 | Null rule evaluation throws where it previously passed silently | Medium | Medium | Exception handler returns 422; frontend should handle gracefully | Mitigated |
| 5 | Frontend components incompatible with existing state | Medium | Medium | New components are additive; existing page logic remains functional | Accepted |
| 6 | Database migration fails on large `workflow_executions` table | Low | High | Migrations add nullable JSONB columns with defaults; no data transformation | Mitigated |
| 7 | Scheduled cleanup command marks active executions as abandoned | Low | High | `WORKFLOW_ABANDONED_HOURS` default is 24; adjustable via `.env` | Mitigated |
| 8 | FormulaEvaluator performance degradation vs native `eval()` | Medium | Low | `symfony/expression-language` compiles to PHP closures; benchmarked < 10ms | Accepted |
| 9 | Dynamic option service whitelist breaks existing dropdowns | Low | Medium | Empty return with logged warning; admin can add services to config | Mitigated |
| 10 | Receipt idempotency key collision | Very Low | Critical | Deterministic key based on execution state + `UNIQUE INDEX` on DB | Mitigated |

---

## Critical Path Risks

### Financial Correctness
- **Before:** `total_amount` could be zero due to unchecked discount overflow or formula errors
- **After:** `FinancialCalculationPipeline` throws `RuleEvaluationException` when `total_amount == 0` with active items
- **Residual Risk:** If ALL items are discounted to zero legitimately, pipeline returns `0` with empty `calculated_items`. This is correct behavior but may confuse users.

### Data Integrity
- **Before:** No temporal overlap check on `fee_versions`
- **After:** `FeeVersion::boot()` rejects overlaps at application level; PostgreSQL `EXCLUDE` constraint recommended for production
- **Residual Risk:** Concurrent saves in multi-node deployment could race past application check. Mitigation: add PostgreSQL `EXCLUDE` constraint in production.

### Security
- **Before:** RCE via `eval()`, SQL injection, arbitrary code execution
- **After:** All vectors hardened
- **Residual Risk:** Zero known vectors at time of audit. Regular penetration testing recommended.

---

*End of Risk Assessment*
