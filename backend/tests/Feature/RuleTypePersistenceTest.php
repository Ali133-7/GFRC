<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Rule type is a first-class property that must survive the full lifecycle:
 * create → reload → update → clone, without any silent type conversion.
 *
 * Rules live in two tables:
 *   - workflow_rules    : rule_type ∈ {simple, case_based}
 *   - validation_rules  : validation_type (+ rule_config for enterprise, field_existence_check for routing)
 */
class RuleTypePersistenceTest extends TestCase
{
    private string $wfId;
    private string $verId;

    protected function setUp(): void
    {
        parent::setUp();
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow); // draft by default
        $this->wfId = $workflow->id;
        $this->verId = $version->id;
    }

    private function rulesUrl(): string
    {
        return "/api/v1/workflows/{$this->wfId}/versions/{$this->verId}/rules";
    }

    private function validationsUrl(): string
    {
        return "/api/v1/workflows/{$this->wfId}/versions/{$this->verId}/validations";
    }

    private function createSimple(): array
    {
        return $this->actingAsAdmin()->postJson($this->rulesUrl(), [
            'name' => 'Simple Rule',
            'condition_logic' => ['operator' => 'gt', 'field_id' => 'f1', 'value' => '10'],
            'actions' => [['action' => 'set_value', 'target_field_id' => 'f2', 'value' => 'x']],
        ])->assertSuccessful()->json('data');
    }

    private function createCaseBased(): array
    {
        return $this->actingAsAdmin()->postJson($this->rulesUrl(), [
            'name' => 'Case Rule',
            'rule_type' => 'case_based',
            'trigger_field_id' => 'f1',
            'cases' => [['value' => 'a', 'actions' => [['action' => 'set_value', 'target_field_id' => 'f2', 'value' => '1']], 'priority' => 100]],
            'default_actions' => [],
        ])->assertSuccessful()->json('data');
    }

    private function createValidation(): array
    {
        return $this->actingAsAdmin()->postJson($this->validationsUrl(), [
            'name' => 'Validation Rule',
            'validation_type' => 'duplicate_check',
            'response_type' => 'error',
            'error_message_ar' => 'مكرر',
        ])->assertSuccessful()->json('data');
    }

    private function createEnterprise(): array
    {
        return $this->actingAsAdmin()->postJson($this->validationsUrl(), [
            'name' => 'Enterprise Rule',
            'validation_type' => 'field_existence_check',
            'response_type' => 'error',
            'rule_config' => ['conditions' => [], 'actions' => [['type' => 'set_value', 'field_id' => 'f2', 'value' => 'y']]],
        ])->assertSuccessful()->json('data');
    }

    private function createRouting(): array
    {
        return $this->actingAsAdmin()->postJson($this->validationsUrl(), [
            'name' => 'Routing Rule',
            'validation_type' => 'field_existence_check',
            'response_type' => 'warning',
            'route_config' => ['on_match' => ['action' => 'warn', 'message_ar' => 'موجود']],
        ])->assertSuccessful()->json('data');
    }

    public function test_each_rule_type_persists_with_correct_discriminators(): void
    {
        $simple = $this->createSimple();
        $this->assertEquals('simple', $simple['rule_type']);

        $case = $this->createCaseBased();
        $this->assertEquals('case_based', $case['rule_type']);
        $this->assertCount(1, $case['cases']);

        $validation = $this->createValidation();
        $this->assertEquals('duplicate_check', $validation['validation_type']);
        $this->assertNull($validation['rule_config'] ?? null);

        $enterprise = $this->createEnterprise();
        $this->assertNotNull($enterprise['rule_config']);

        $routing = $this->createRouting();
        $this->assertEquals('field_existence_check', $routing['validation_type']);
        $this->assertNull($routing['rule_config'] ?? null);
        $this->assertNotNull($routing['route_config']);
    }

    public function test_types_survive_reload(): void
    {
        $simple = $this->createSimple();
        $case = $this->createCaseBased();
        $enterprise = $this->createEnterprise();
        $routing = $this->createRouting();

        $show = $this->actingAsAdmin()
            ->getJson("/api/v1/workflows/{$this->wfId}/versions/{$this->verId}")
            ->assertSuccessful()->json('data');

        $wfRules = collect($show['rules']);
        $this->assertEquals('simple', $wfRules->firstWhere('id', $simple['id'])['rule_type']);
        $this->assertEquals('case_based', $wfRules->firstWhere('id', $case['id'])['rule_type']);

        $vRules = collect($show['validation_rules']);
        $this->assertNotNull($vRules->firstWhere('id', $enterprise['id'])['rule_config']);
        $reloadedRouting = $vRules->firstWhere('id', $routing['id']);
        $this->assertEquals('field_existence_check', $reloadedRouting['validation_type']);
        $this->assertNull($reloadedRouting['rule_config']);
    }

    public function test_update_does_not_mutate_type(): void
    {
        $simple = $this->createSimple();
        $updated = $this->actingAsAdmin()->putJson($this->rulesUrl()."/{$simple['id']}", [
            'name' => 'Simple Renamed',
            'condition_logic' => ['operator' => 'gt', 'field_id' => 'f1', 'value' => '20'],
            'actions' => [['action' => 'set_value', 'target_field_id' => 'f2', 'value' => 'z']],
        ])->assertSuccessful()->json('data');

        $this->assertEquals('simple', $updated['rule_type']);
        $this->assertEquals('Simple Renamed', $updated['name']);

        $case = $this->createCaseBased();
        $updatedCase = $this->actingAsAdmin()->putJson($this->rulesUrl()."/{$case['id']}", [
            'rule_type' => 'case_based',
            'trigger_field_id' => 'f1',
            'cases' => [['value' => 'b', 'actions' => [['action' => 'set_value', 'target_field_id' => 'f2', 'value' => '2']], 'priority' => 50]],
        ])->assertSuccessful()->json('data');
        $this->assertEquals('case_based', $updatedCase['rule_type']);
    }

    public function test_clone_preserves_workflow_rule_type_and_cases(): void
    {
        $this->createSimple();
        $this->createCaseBased();

        $cloned = $this->actingAsAdmin()
            ->postJson("/api/v1/workflows/{$this->wfId}/versions/{$this->verId}/clone")
            ->assertSuccessful()->json('data');

        $clonedRules = collect($cloned['rules']);
        $this->assertEquals(
            ['case_based', 'simple'],
            $clonedRules->pluck('rule_type')->sort()->values()->all()
        );
        $clonedCase = $clonedRules->firstWhere('rule_type', 'case_based');
        $this->assertCount(1, $clonedCase['cases'], 'cloned case_based rule lost its cases');
        $this->assertEquals('f1', $clonedCase['trigger_field_id']);
    }
}
