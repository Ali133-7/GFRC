<?php

namespace App\Console\Commands;

use App\Models\ValidationRule;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs rules whose custom-field references (custom_<workflow_field.id>) point at a
 * DIFFERENT version's fields — the result of cloning a version before the clone learned to
 * remap custom-field ids. Stale references are matched to the correct field in the SAME
 * version by custom_name, then rewritten.
 *
 * Run with --dry-run first to review, then without it to apply.
 */
class ReconcileWorkflowFieldKeys extends Command
{
    protected $signature = 'workflow:reconcile-field-keys {--dry-run : Show changes without saving}';
    protected $description = 'Remap stale custom-field references inside workflow/validation rules to the correct field in their own version';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $totalChanged = 0;
        $unresolved = 0;

        foreach (WorkflowVersion::with(['fields', 'rules', 'validationRules'])->get() as $version) {
            $idsInVersion = [];
            $nameToKey = [];
            $nameCount = [];
            foreach ($version->fields as $f) {
                $idsInVersion[$f->id] = true;
                if ($f->register_field_id === null) {
                    $name = (string) $f->custom_name;
                    $nameCount[$name] = ($nameCount[$name] ?? 0) + 1;
                    $nameToKey[$name] = 'custom_' . $f->id;
                }
            }

            foreach ($version->rules as $rule) {
                $sources = [$rule->trigger_field_id, $rule->condition_logic, $rule->actions, $rule->cases, $rule->default_actions];
                $keyMap = $this->buildKeyMap($sources, $idsInVersion, $nameToKey, $nameCount, $version, $unresolved);
                if (empty($keyMap)) {
                    continue;
                }

                $this->line("ver V{$version->version} · rule [{$rule->name}]:");
                foreach ($keyMap as $old => $new) {
                    $this->line("    {$old}  →  {$new}");
                }

                if (!$dry) {
                    $rule->trigger_field_id = $this->remap($rule->trigger_field_id, $keyMap);
                    $rule->condition_logic = $this->remap($rule->condition_logic, $keyMap);
                    $rule->actions = $this->remap($rule->actions, $keyMap);
                    $rule->cases = $this->remap($rule->cases, $keyMap);
                    $rule->default_actions = $this->remap($rule->default_actions, $keyMap);
                    $rule->save();
                }
                $totalChanged++;
            }

            foreach ($version->validationRules as $vRule) {
                $sources = [$vRule->trigger_field_id, $vRule->trigger_conditions, $vRule->target_fields, $vRule->query_conditions, $vRule->route_config, $vRule->lookup_config, $vRule->field_effects, $vRule->rule_config];
                $keyMap = $this->buildKeyMap($sources, $idsInVersion, $nameToKey, $nameCount, $version, $unresolved);
                if (empty($keyMap)) {
                    continue;
                }

                $this->line("ver V{$version->version} · validation [{$vRule->name}]:");
                foreach ($keyMap as $old => $new) {
                    $this->line("    {$old}  →  {$new}");
                }

                if (!$dry) {
                    $vRule->trigger_field_id = $this->remap($vRule->trigger_field_id, $keyMap);
                    $vRule->trigger_conditions = $this->remap($vRule->trigger_conditions, $keyMap);
                    $vRule->target_fields = $this->remap($vRule->target_fields, $keyMap);
                    $vRule->query_conditions = $this->remap($vRule->query_conditions, $keyMap);
                    $vRule->route_config = $this->remap($vRule->route_config, $keyMap);
                    $vRule->lookup_config = $this->remap($vRule->lookup_config, $keyMap);
                    $vRule->field_effects = $this->remap($vRule->field_effects, $keyMap);
                    $vRule->rule_config = $this->remap($vRule->rule_config, $keyMap);
                    $vRule->save();
                }
                $totalChanged++;
            }
        }

        $this->newLine();
        $this->info(($dry ? '[DRY RUN] ' : '') . "Rules with remapped references: {$totalChanged}; unresolved stale refs: {$unresolved}");
        if ($dry && $totalChanged > 0) {
            $this->comment('Re-run without --dry-run to apply.');
        }
        return self::SUCCESS;
    }

    /**
     * Collect stale custom_<id> references from the given sources and resolve each to the
     * correct field in this version by matching custom_name.
     */
    private function buildKeyMap(array $sources, array $idsInVersion, array $nameToKey, array $nameCount, WorkflowVersion $version, int &$unresolved): array
    {
        $refs = [];
        foreach ($sources as $src) {
            $this->collectCustomRefs($src, $refs);
        }

        $keyMap = [];
        foreach (array_keys($refs) as $ref) {
            $rawId = substr($ref, 7); // strip 'custom_'
            if (isset($idsInVersion[$rawId])) {
                continue; // valid reference in this version
            }
            $srcField = WorkflowField::find($rawId);
            if (!$srcField) {
                $this->warn("    unresolved (no source field): {$ref} in V{$version->version}");
                $unresolved++;
                continue;
            }
            $name = (string) $srcField->custom_name;
            if (($nameCount[$name] ?? 0) !== 1 || empty($nameToKey[$name])) {
                $this->warn("    unresolved (no unique name match for '{$name}'): {$ref} in V{$version->version}");
                $unresolved++;
                continue;
            }
            if ($nameToKey[$name] !== $ref) {
                $keyMap[$ref] = $nameToKey[$name];
            }
        }
        return $keyMap;
    }

    private function collectCustomRefs(mixed $data, array &$out): void
    {
        if (is_string($data)) {
            if (str_starts_with($data, 'custom_')) {
                $out[$data] = true;
            }
            return;
        }
        if (is_array($data)) {
            foreach ($data as $v) {
                $this->collectCustomRefs($v, $out);
            }
        }
    }

    private function remap(mixed $data, array $keyMap): mixed
    {
        if (is_string($data)) {
            return $keyMap[$data] ?? $data;
        }
        if (is_array($data)) {
            return array_map(fn ($v) => $this->remap($v, $keyMap), $data);
        }
        return $data;
    }
}
