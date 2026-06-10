# GFRC v1 → v2 Migration Guide

**Date:** 2026-06-07  
**Downtime Required:** Yes — estimated 15 minutes  
**Rollback Plan:** Included

---

## Pre-Migration Checklist

1. **Backup database** — full dump required
2. **Backup `.env`** — save current configuration
3. **Verify `symfony/expression-language` is installed** — run `composer install`
4. **Queue workers stopped** — prevent abandoned cleanup mid-migration
5. **Maintenance mode enabled** — `php artisan down`

---

## Migration Steps

### Step 1: Deploy Code

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

### Step 2: Run Migrations

```bash
php artisan migrate --force
```

Migrations applied (in order):
1. `2026_06_04_000001_drop_workflows_current_version`
2. `2026_06_04_000002_add_execution_consistency_columns`
3. `2026_06_04_000003_create_workflow_routing_log_table`
4. `2026_06_04_000004_create_field_state_history_table`
5. `2026_06_04_000005_add_inheritance_source_to_workflow_fields`

### Step 3: Seed Configuration

Add to `.env`:
```
WORKFLOW_ABANDONED_HOURS=24
WORKFLOW_FINANCIAL_SCALE=3
WORKFLOW_STRICT_FORMULA_VALIDATION=true
WORKFLOW_DEBUG_PANEL_ENABLED=true
```

### Step 4: Restart Scheduler

```bash
# Laravel Scheduler (cron)
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

The `workflow:cleanup-abandoned` command is now registered and will run hourly.

### Step 5: Warm Cache

```bash
php artisan config:cache
php artisan route:cache
```

### Step 6: Exit Maintenance Mode

```bash
php artisan up
```

---

## Post-Migration Verification

Run the critical test suite:
```bash
php artisan test --filter="FormulaEvaluatorTest|FeeVersionOverlapTest|RuleEngineV2NullSafetyTest|FinancialCalculationPipelineTest|WorkflowExecutionRaceConditionTest"
```

Expected: **33 passes, 0 failures**

---

## Rollback Plan

If critical failure occurs within 4 hours of migration:

1. **Enable maintenance mode**
   ```bash
   php artisan down
   ```

2. **Restore database from pre-migration dump**

3. **Revert code to previous tag**
   ```bash
   git checkout v1.x.x
   composer install --no-dev --optimize-autoloader
   ```

4. **Clear caches**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

5. **Exit maintenance mode**
   ```bash
   php artisan up
   ```

---

## Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| `workflows.current_version` dropped | Any code reading `$workflow->current_version` will return `null` | Use `$workflow->activeVersion()->version` or `$workflow->currentVersionModel()` |
| `eval()` removed | Custom formulas using PHP functions outside whitelist will fail | Audit formulas before migration; whitelist can be extended in `config/workflow.php` |
| `RuleEngineV2` null handling stricter | Rules with null values now throw instead of silently failing | Review rule configurations; ensure fields have defaults |
| `DynamicOptionSource` service whitelist | Any dynamic option source not in config will return empty | Add allowed service classes to `config/workflow.php` |

---

*End of Migration Guide*
