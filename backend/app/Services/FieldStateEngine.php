<?php

namespace App\Services;

use App\Models\FieldStateHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Field State Engine
 *
 * Manages field states deterministically:
 *   visible / hidden
 *   required / optional
 *   readonly / editable
 *   locked / unlocked
 *   enabled / disabled
 *
 * Every state change is logged to field_state_history.
 */
class FieldStateEngine
{
    private const VALID_STATES = [
        'visible', 'hidden',
        'required', 'optional',
        'readonly', 'editable',
        'locked', 'unlocked',
        'enabled', 'disabled',
    ];

    private const STATE_MAP = [
        'show' => ['visible' => true, 'hidden' => false],
        'hide' => ['visible' => false, 'hidden' => true],
        'set_required' => ['required' => true, 'optional' => false],
        'set_optional' => ['required' => false, 'optional' => true],
        'set_readonly' => ['readonly' => true, 'editable' => false],
        'set_editable' => ['readonly' => false, 'editable' => true],
        'set_lock' => ['locked' => true, 'unlocked' => false],
        'set_unlock' => ['locked' => false, 'unlocked' => true],
        'enable' => ['enabled' => true, 'disabled' => false],
        'disable' => ['enabled' => false, 'disabled' => true],
    ];

    /**
     * Apply an action to a field's state.
     *
     * @param string $action One of the mapped actions
     * @param string $fieldId UUID of the field
     * @param array $executionContext Must contain 'execution_id'
     * @param string|null $ruleId UUID of the triggering rule
     * @return array The new state fragment
     */
    public function apply(string $action, string $fieldId, array $executionContext, ?string $ruleId = null): array
    {
        if (!isset(self::STATE_MAP[$action])) {
            Log::warning("FieldStateEngine: unknown action '{$action}'", [
                'field_id' => $fieldId,
                'execution_id' => $executionContext['execution_id'] ?? null,
            ]);
            return [];
        }

        $newFragment = self::STATE_MAP[$action];

        $executionId = $executionContext['execution_id'] ?? null;
        if ($executionId) {
            $this->recordHistory($executionId, $fieldId, $ruleId, $newFragment);
        }

        return $newFragment;
    }

    /**
     * Merge multiple action results into a full field state object.
     */
    public function mergeStates(array $states): array
    {
        $merged = [
            'visible' => true,
            'required' => false,
            'readonly' => false,
            'locked' => false,
            'enabled' => true,
        ];

        foreach ($states as $fragment) {
            foreach ($fragment as $key => $value) {
                if (in_array($key, self::VALID_STATES, true)) {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * Build default state for a field.
     */
    public function defaultState(): array
    {
        return [
            'visible' => true,
            'required' => false,
            'readonly' => false,
            'locked' => false,
            'enabled' => true,
        ];
    }

    /**
     * Batch apply actions and return full state map.
     *
     * @param array $actions Array of ['action' => string, 'field_id' => string, 'rule_id' => ?string]
     * @param array $executionContext
     * @return array Map of field_id => state
     */
    public function applyBatch(array $actions, array $executionContext): array
    {
        $fieldStates = [];

        foreach ($actions as $actionDef) {
            $fragment = $this->apply(
                $actionDef['action'],
                $actionDef['field_id'],
                $executionContext,
                $actionDef['rule_id'] ?? null
            );

            if (!isset($fieldStates[$actionDef['field_id']])) {
                $fieldStates[$actionDef['field_id']] = $this->defaultState();
            }

            foreach ($fragment as $key => $value) {
                $fieldStates[$actionDef['field_id']][$key] = $value;
            }
        }

        return $fieldStates;
    }

    private function recordHistory(string $executionId, string $fieldId, ?string $ruleId, array $newFragment): void
    {
        try {
            DB::table('field_state_history')->insert([
                'execution_id' => $executionId,
                'field_id' => $fieldId,
                'rule_id' => $ruleId,
                'old_state' => json_encode($this->defaultState()),
                'new_state' => json_encode($newFragment),
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('FieldStateEngine: failed to record history', [
                'execution_id' => $executionId,
                'field_id' => $fieldId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
