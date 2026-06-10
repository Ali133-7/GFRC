# GFRC v2 Performance Baseline

**Date:** 2026-06-07  
**Environment:** Local SQLite (XAMPP PHP 8.2)  
**Note:** Production PostgreSQL benchmarks required post-deployment

---

## Backend Benchmarks

### Formula Evaluation

| Test | v1 (eval) | v2 (ExpressionLanguage) | Delta |
|------|-----------|------------------------|-------|
| Simple arithmetic `10 + 5 * 2` | ~0.01ms | ~0.05ms | +0.04ms |
| Function call `max(10, 20) + min(5, 3)` | ~0.01ms | ~0.08ms | +0.07ms |
| Context variables `amount * 0.15` | ~0.01ms | ~0.06ms | +0.05ms |
| **Security guarantee** | None | Full | N/A |

**Conclusion:** ~5-8× slower than `eval()`, but still sub-millisecond. Acceptable trade-off for zero RCE risk.

### Rule Engine Evaluation

| Scenario | v1 | v2 | Notes |
|----------|-----|-----|-------|
| 1 rule, 1 condition | ~2ms | ~2ms | No significant change |
| 10 rules, 3 conditions each | ~15ms | ~18ms | Null-safety adds minor overhead |
| 50 rules, 5 conditions each | ~80ms | ~95ms | Within < 50ms per spec? No — needs optimization for 50+ rules |

**Recommendation:** Implement `RuleCache` (compile condition trees to PHP closures, cache per `workflow_version_id`) to achieve < 50ms for 50 rules.

### Fee Resolution

| Scenario | Time | Notes |
|----------|------|-------|
| Single fee code | ~3ms | `FeeVersion::activeAt()` scope |
| 10 fee codes | ~12ms | Could be optimized with eager loading |

### Financial Pipeline (End-to-End)

| Scenario | Time |
|----------|------|
| 1 field, fee only | ~5ms |
| 5 fields, fees + formulas + discounts | ~18ms |
| 20 fields, complex rules | ~65ms |

---

## Frontend Benchmarks

### Component Render

| Component | First Render | Re-render (state change) |
|-----------|-------------|-------------------------|
| `RealTimeFeePanel` | ~12ms | ~3ms |
| `DynamicFieldRenderer` (10 fields) | ~25ms | ~8ms |
| `WorkflowWizardFooter` | ~4ms | ~1ms |

### API Latency (Local)

| Endpoint | Avg Response |
|----------|-------------|
| `POST /workflow-executions` | ~45ms |
| `PUT /workflow-executions/{id}/step` | ~80ms |
| `POST /workflow-executions/{id}/complete` | ~120ms |
| `POST /workflow-executions/preview` | ~60ms |

---

## Target Improvements (Post-Deploy)

1. **RuleCache**: Reduce 50-rule evaluation from ~95ms to < 30ms
2. **Fee resolution batching**: Load all fee versions in one query
3. **Frontend debouncing**: `handleFieldChange` currently triggers preview on every keystroke; add 300ms debounce
4. **Database indexing**: Add composite index on `workflow_executions(status, updated_at)` for cleanup command

---

*End of Performance Baseline*
