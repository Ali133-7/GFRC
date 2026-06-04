<?php

namespace Tests\Unit;

use App\Services\RuleEngineV2;
use Tests\TestCase;

class RuleEngineSelectOperatorsTest extends TestCase
{
    public function test_equals_on_select_field_uses_value(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'equals',
            'field_id' => 'customer_type',
            'value' => 'vip',
        ];

        $this->assertTrue($engine->evaluateCondition($condition, ['customer_type' => 'vip']));
        $this->assertFalse($engine->evaluateCondition($condition, ['customer_type' => 'regular']));
        $this->assertFalse($engine->evaluateCondition($condition, ['customer_type' => '']));
    }

    public function test_not_equals_on_select_field(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'not_equals',
            'field_id' => 'customer_type',
            'value' => 'vip',
        ];

        $this->assertFalse($engine->evaluateCondition($condition, ['customer_type' => 'vip']));
        $this->assertTrue($engine->evaluateCondition($condition, ['customer_type' => 'regular']));
    }

    public function test_in_operator_on_select_field(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'in',
            'field_id' => 'status',
            'value' => ['active', 'pending'],
        ];

        $this->assertTrue($engine->evaluateCondition($condition, ['status' => 'active']));
        $this->assertTrue($engine->evaluateCondition($condition, ['status' => 'pending']));
        $this->assertFalse($engine->evaluateCondition($condition, ['status' => 'cancelled']));
    }

    public function test_in_operator_with_json_string_value(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'in',
            'field_id' => 'status',
            'value' => '["active","pending"]',
        ];

        $this->assertTrue($engine->evaluateCondition($condition, ['status' => 'active']));
        $this->assertFalse($engine->evaluateCondition($condition, ['status' => 'cancelled']));
    }

    public function test_not_in_operator_on_select_field(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'not_in',
            'field_id' => 'status',
            'value' => ['cancelled', 'expired'],
        ];

        $this->assertTrue($engine->evaluateCondition($condition, ['status' => 'active']));
        $this->assertFalse($engine->evaluateCondition($condition, ['status' => 'cancelled']));
    }

    public function test_in_operator_with_single_value(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'in',
            'field_id' => 'type',
            'value' => 'single',
        ];

        $this->assertTrue($engine->evaluateCondition($condition, ['type' => 'single']));
        $this->assertFalse($engine->evaluateCondition($condition, ['type' => 'other']));
    }

    public function test_select_equals_with_numeric_values(): void
    {
        $engine = app(RuleEngineV2::class);

        $condition = [
            'operator' => 'equals',
            'field_id' => 'priority',
            'value' => '1',
        ];

        $this->assertTrue($engine->evaluateCondition($condition, ['priority' => '1']));
        $this->assertTrue($engine->evaluateCondition($condition, ['priority' => 1]));
        $this->assertFalse($engine->evaluateCondition($condition, ['priority' => '2']));
    }
}
