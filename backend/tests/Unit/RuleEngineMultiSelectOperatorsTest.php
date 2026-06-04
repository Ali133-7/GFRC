<?php

namespace Tests\Unit;

use App\Services\RuleEngineV2;
use Tests\TestCase;

class RuleEngineMultiSelectOperatorsTest extends TestCase
{
    public function test_contains_on_text_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'contains', 'field_id' => 'notes', 'value' => 'urgent'];

        $this->assertTrue($engine->evaluateCondition($condition, ['notes' => 'This is an urgent matter']));
        $this->assertFalse($engine->evaluateCondition($condition, ['notes' => 'Regular matter']));
    }

    public function test_contains_on_multi_select_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'contains', 'field_id' => 'services', 'value' => 'premium'];

        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['premium', 'basic']]));
        $this->assertFalse($engine->evaluateCondition($condition, ['services' => ['basic', 'standard']]));
    }

    public function test_not_contains_on_text_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'not_contains', 'field_id' => 'notes', 'value' => 'urgent'];

        $this->assertTrue($engine->evaluateCondition($condition, ['notes' => 'Regular matter']));
        $this->assertFalse($engine->evaluateCondition($condition, ['notes' => 'This is urgent']));
    }

    public function test_not_contains_on_multi_select_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'not_contains', 'field_id' => 'services', 'value' => 'premium'];

        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['basic', 'standard']]));
        $this->assertFalse($engine->evaluateCondition($condition, ['services' => ['premium', 'basic']]));
    }

    public function test_any_of_on_multi_select_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'any_of', 'field_id' => 'services', 'value' => ['premium', 'vip']];

        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['premium', 'basic']]));
        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['vip']]));
        $this->assertFalse($engine->evaluateCondition($condition, ['services' => ['basic', 'standard']]));
    }

    public function test_any_of_with_json_string(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'any_of', 'field_id' => 'services', 'value' => '["premium","vip"]'];

        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['premium']]));
        $this->assertFalse($engine->evaluateCondition($condition, ['services' => ['basic']]));
    }

    public function test_all_of_on_multi_select_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'all_of', 'field_id' => 'services', 'value' => ['premium', 'vip']];

        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['premium', 'vip', 'basic']]));
        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['vip', 'premium']]));
        $this->assertFalse($engine->evaluateCondition($condition, ['services' => ['premium', 'basic']]));
    }

    public function test_all_of_with_single_value(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'all_of', 'field_id' => 'services', 'value' => 'premium'];

        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['premium']]));
        $this->assertTrue($engine->evaluateCondition($condition, ['services' => ['premium', 'basic']]));
        $this->assertFalse($engine->evaluateCondition($condition, ['services' => ['basic']]));
    }

    public function test_any_of_on_single_value_field(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = ['operator' => 'any_of', 'field_id' => 'status', 'value' => ['active', 'pending']];

        $this->assertTrue($engine->evaluateCondition($condition, ['status' => 'active']));
        $this->assertFalse($engine->evaluateCondition($condition, ['status' => 'cancelled']));
    }

    public function test_combined_operators_in_and_group(): void
    {
        $engine = app(RuleEngineV2::class);
        $condition = [
            'operator' => 'and',
            'conditions' => [
                ['operator' => 'any_of', 'field_id' => 'services', 'value' => ['premium']],
                ['operator' => 'contains', 'field_id' => 'notes', 'value' => 'priority'],
            ],
        ];

        $this->assertTrue($engine->evaluateCondition($condition, [
            'services' => ['premium', 'basic'],
            'notes' => 'High priority request',
        ]));

        $this->assertFalse($engine->evaluateCondition($condition, [
            'services' => ['basic'],
            'notes' => 'High priority request',
        ]));
    }
}
