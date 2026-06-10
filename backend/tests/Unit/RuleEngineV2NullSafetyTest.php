<?php

namespace Tests\Unit;

use App\Exceptions\Workflow\RuleEvaluationException;
use App\Services\FeeEngine;
use App\Services\RuleEngineV2;
use Tests\TestCase;

class RuleEngineV2NullSafetyTest extends TestCase
{
    private RuleEngineV2 $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RuleEngineV2(app(FeeEngine::class));
    }

    public function test_null_field_value_with_gt_operator_throws(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->expectExceptionMessage('Null value for field');

        $this->engine->evaluateCondition([
            'field_id' => 'amount',
            'operator' => 'gt',
            'value' => 100,
        ], ['amount' => null]);
    }

    public function test_null_field_value_with_equals_operator_throws(): void
    {
        $this->expectException(RuleEvaluationException::class);

        $this->engine->evaluateCondition([
            'field_id' => 'name',
            'operator' => 'equals',
            'value' => 'test',
        ], ['name' => null]);
    }

    public function test_is_empty_allows_null(): void
    {
        $result = $this->engine->evaluateCondition([
            'field_id' => 'name',
            'operator' => 'is_empty',
        ], ['name' => null]);

        $this->assertTrue($result);
    }

    public function test_is_not_empty_allows_null(): void
    {
        $result = $this->engine->evaluateCondition([
            'field_id' => 'name',
            'operator' => 'is_not_empty',
        ], ['name' => null]);

        $this->assertFalse($result);
    }

    public function test_non_null_value_evaluates_normally(): void
    {
        $result = $this->engine->evaluateCondition([
            'field_id' => 'amount',
            'operator' => 'gt',
            'value' => 100,
        ], ['amount' => 200]);

        $this->assertTrue($result);
    }

    public function test_and_group_with_null_throws(): void
    {
        $this->expectException(RuleEvaluationException::class);

        $this->engine->evaluateCondition([
            'operator' => 'and',
            'conditions' => [
                [
                    'field_id' => 'amount',
                    'operator' => 'gt',
                    'value' => 100,
                ],
            ],
        ], ['amount' => null]);
    }
}
