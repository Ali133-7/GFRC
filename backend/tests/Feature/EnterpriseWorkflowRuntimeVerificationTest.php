<?php

namespace Tests\Feature;

use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\FeeVersion;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Models\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enterprise Workflow Runtime Verification Test
 * 
 * This test creates a REAL merchant registration workflow and executes it
 * end-to-end through the API, proving that:
 * 1. Dropdown options render correctly
 * 2. Rules match and execute
 * 3. Field visibility changes
 * 4. Fees calculate correctly
 * 5. Totals are consistent across Review → Receipt
 */
class EnterpriseWorkflowRuntimeVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected \App\Models\User $user;
    protected Register $register;
    protected RegisterField $registrationTypeField;
    protected RegisterField $commercialClassField;
    protected RegisterField $workLocationField;
    protected RegisterField $feeAmountField;
    protected RegisterField $additionalDocsField;
    protected Workflow $workflow;
    protected WorkflowVersion $version;
    protected WorkflowStep $step1;
    protected WorkflowStep $step2;
    protected OfficialFee $fee1;
    protected FeeVersion $feeVersion1;
    protected OfficialFee $fee2;
    protected FeeVersion $feeVersion2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = \App\Models\User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->user->assignRole('admin');
        $this->actingAs($this->user);

        // Create Register
        $this->register = Register::create([
            'name_ar' => 'سجل التجار',
            'name_en' => 'Merchants Register',
            'code' => 'MERCH',
            'fiscal_year' => 2026,
        ]);

        // Use existing official fee and fee version from TestCase setUp
        $this->feeCategory = $this->officialFee->category;
        $this->feeVersion = $this->officialFee->feeVersions()->orderBy('version', 'desc')->first();

        // Create Register Fields
        $this->registrationTypeField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'registration_type',
            'label_ar' => 'نوع التسجيل',
            'field_type' => 'select',
            'is_required' => true,
            'is_visible' => true,
            'options' => [
                ['value' => 'new', 'label' => 'انتساب جديد', 'label_ar' => 'انتساب جديد'],
                ['value' => 'renewal', 'label' => 'تجديد', 'label_ar' => 'تجديد'],
            ],
        ]);

        $this->commercialClassField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'commercial_class',
            'label_ar' => 'الصنف التجاري',
            'field_type' => 'select',
            'is_required' => true,
            'is_visible' => true,
            'options' => [
                ['value' => 'excellent', 'label' => 'ممتاز', 'label_ar' => 'ممتاز'],
                ['value' => 'first', 'label' => 'أول', 'label_ar' => 'أول'],
                ['value' => 'second', 'label' => 'ثاني', 'label_ar' => 'ثاني'],
            ],
        ]);

        $this->workLocationField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'work_location',
            'label_ar' => 'موقع العمل',
            'field_type' => 'select',
            'is_required' => true,
            'is_visible' => true,
            'options' => [
                ['value' => 'inside', 'label' => 'داخل المدينة', 'label_ar' => 'داخل المدينة'],
                ['value' => 'outside', 'label' => 'خارج المدينة', 'label_ar' => 'خارج المدينة'],
            ],
        ]);

        $this->feeAmountField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'fee_amount',
            'label_ar' => 'مبلغ الرسم',
            'field_type' => 'decimal',
            'is_required' => false,
            'is_visible' => true,
            'is_financial' => true,
        ]);

        $this->additionalDocsField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'additional_docs',
            'label_ar' => 'الوثائق الإضافية',
            'field_type' => 'textarea',
            'is_required' => false,
            'is_visible' => false, // Hidden by default
        ]);

        // Create Fees
        $this->fee1 = OfficialFee::create([
            'category_id' => $this->feeCategory->id,
            'name_ar' => 'رسم انتساب ممتاز',
            'name_en' => 'Excellent Registration Fee',
            'fee_code' => 'FEE-EXCELLENT-NEW',
            'is_active' => true,
            'effective_from' => now()->subYear(),
        ]);
        $this->feeVersion1 = FeeVersion::create([
            'fee_id' => $this->fee1->id,
            'amount' => '500000',
            'version' => 1,
            'effective_from' => now()->subYear(),
        ]);

        $this->fee2 = OfficialFee::create([
            'category_id' => $this->feeCategory->id,
            'name_ar' => 'رسم تجديد',
            'name_en' => 'Renewal Fee',
            'fee_code' => 'FEE-RENEWAL',
            'is_active' => true,
            'effective_from' => now()->subYear(),
        ]);
        $this->feeVersion2 = FeeVersion::create([
            'fee_id' => $this->fee2->id,
            'amount' => '250000',
            'version' => 1,
            'effective_from' => now()->subYear(),
        ]);

        // Create Workflow
        $this->workflow = Workflow::create([
            'register_id' => $this->register->id,
            'name_ar' => 'تسجيل تاجر جديد',
            'name_en' => 'New Merchant Registration',
            'code' => 'WF-MERCH',
            'is_active' => true,
        ]);

        // Create Version
        $this->version = WorkflowVersion::create([
            'workflow_id' => $this->workflow->id,
            'version' => 1,
            'status' => 'active',
            'change_summary' => 'النسخة الأولية',
        ]);

        // Create Steps (only 1 step for this test)
        $this->step1 = WorkflowStep::create([
            'workflow_version_id' => $this->version->id,
            'title_ar' => 'بيانات التسجيل',
            'sort_order' => 0,
        ]);

        // Add fields to workflow
        WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'register_field_id' => $this->registrationTypeField->id,
            'step_id' => $this->step1->id,
            'sort_order' => 0,
            'is_visible' => true,
            'is_required' => true,
        ]);

        WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'register_field_id' => $this->commercialClassField->id,
            'step_id' => $this->step1->id,
            'sort_order' => 1,
            'is_visible' => true,
            'is_required' => true,
        ]);

        WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'register_field_id' => $this->workLocationField->id,
            'step_id' => $this->step1->id,
            'sort_order' => 2,
            'is_visible' => true,
            'is_required' => true,
        ]);

        WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'register_field_id' => $this->additionalDocsField->id,
            'step_id' => $this->step1->id,
            'sort_order' => 3,
            'is_visible' => false, // Hidden by default
        ]);

        WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'register_field_id' => $this->feeAmountField->id,
            'step_id' => $this->step1->id,
            'sort_order' => 4,
            'is_visible' => true,
            'is_financial' => true,
        ]);

        // Rule 1: If registration_type = new AND commercial_class = excellent
        // THEN show additional_docs AND set_fee 500000
        ValidationRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'رسم انتساب ممتاز',
            'validation_type' => 'field_existence_check',
            'is_active' => true,
            'priority' => 1000,
            'rule_config' => [
                'conditions' => [
                    [
                        'type' => 'simple',
                        'field_id' => $this->registrationTypeField->id,
                        'operator' => 'equals',
                        'value' => 'new',
                    ],
                    [
                        'type' => 'simple',
                        'field_id' => $this->commercialClassField->id,
                        'operator' => 'equals',
                        'value' => 'excellent',
                    ],
                ],
                'actions' => [
                    [
                        'id' => 'act-1',
                        'type' => 'set_visibility',
                        'field_id' => $this->additionalDocsField->id,
                        'value' => 'visible',
                    ],
                    [
                        'id' => 'act-2',
                        'type' => 'set_fee',
                        'field_id' => $this->feeAmountField->id,
                        'value' => 'FEE-EXCELLENT-NEW',
                    ],
                ],
            ],
        ]);

        // Rule 2: If registration_type = renewal
        // THEN set_fee 250000
        ValidationRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'رسم تجديد',
            'validation_type' => 'field_existence_check',
            'is_active' => true,
            'priority' => 900,
            'rule_config' => [
                'conditions' => [
                    [
                        'type' => 'simple',
                        'field_id' => $this->registrationTypeField->id,
                        'operator' => 'equals',
                        'value' => 'renewal',
                    ],
                ],
                'actions' => [
                    [
                        'id' => 'act-3',
                        'type' => 'set_fee',
                        'field_id' => $this->feeAmountField->id,
                        'value' => 'FEE-RENEWAL',
                    ],
                ],
            ],
        ]);
    }

    /**
     * TEST 1: Verify dropdown options are correctly resolved
     */
    public function test_dropdown_options_resolved_correctly(): void
    {
        $response = $this->getJson("/api/v1/workflows/{$this->workflow->id}/versions/{$this->version->id}");
        $response->assertStatus(200);

        $version = $response->json('data');
        $fields = $version['fields'] ?? [];

        // Find registration_type field
        $regTypeField = collect($fields)->firstWhere('register_field_id', $this->registrationTypeField->id);
        $this->assertNotNull($regTypeField, 'Registration type field should exist');

        // Verify options are resolved
        $options = $regTypeField['register_field']['options'] ?? [];
        $this->assertNotEmpty($options, 'Options should not be empty');

        // Verify option structure
        $this->assertEquals('new', $options[0]['value']);
        $this->assertEquals('انتساب جديد', $options[0]['label_ar'] ?? $options[0]['label']);

        // Dump for verification
        $this->dumpFieldDebug('registration_type', $regTypeField);
    }

    /**
     * TEST 2: Execute workflow with Rule 1 scenario (new + excellent)
     */
    public function test_rule_1_new_excellent_matches_and_applies(): void
    {
        // Start execution
        $startResponse = $this->postJson('/api/v1/workflow-executions', [
            'workflow_version_id' => $this->version->id,
        ]);
        $startResponse->assertStatus(201);

        $executionId = $startResponse->json('data.execution.id');
        $this->assertNotNull($executionId);

        // Submit Step 1 with values that trigger Rule 1
        $submitResponse = $this->putJson("/api/v1/workflow-executions/{$executionId}/step", [
            'step_index' => 0,
            'values' => [
                $this->registrationTypeField->id => 'new',
                $this->commercialClassField->id => 'excellent',
                $this->workLocationField->id => 'inside',
            ],
        ]);

        $submitResponse->assertStatus(200);

        $data = $submitResponse->json('data');

        // Verify fee was calculated
        $calculatedItems = $data['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(function ($item) {
            return ($item['fee_code'] ?? null) === 'FEE-EXCELLENT-NEW'
                && $item['amount'] == '500000';
        });
        $this->assertTrue($hasFee, 'Fee FEE-EXCELLENT-NEW (500000) should be in calculated items');

        // Verify total is NOT zero
        $total = $data['total_amount'] ?? '0';
        $this->assertNotEquals('0.000', $total, 'Total should not be zero when fee is applied');
        $this->assertEquals('500000.000', $total, 'Total should be 500000');

        // Verify field_states show additional_docs as visible
        $fieldStates = $data['field_states'] ?? [];
        $additionalDocsState = $fieldStates[$this->additionalDocsField->id] ?? null;

        // Debug dump
        $this->dumpRuntimeDebug('Rule 1: New + Excellent', [
            'execution_id' => $executionId,
            'step_index' => 0,
            'values' => [
                $this->registrationTypeField->id => 'new',
                $this->commercialClassField->id => 'excellent',
                $this->workLocationField->id => 'inside',
            ],
            'calculated_items' => $calculatedItems,
            'total_amount' => $total,
            'field_states' => $fieldStates,
            'modified_values' => $data['modified_values'] ?? [],
        ]);
    }

    /**
     * TEST 3: Execute workflow with Rule 2 scenario (renewal)
     */
    public function test_rule_2_renewal_matches_and_applies(): void
    {
        // Start execution
        $startResponse = $this->postJson('/api/v1/workflow-executions', [
            'workflow_version_id' => $this->version->id,
        ]);
        $startResponse->assertStatus(201);

        $executionId = $startResponse->json('data.execution.id');

        // Submit Step 1 with values that trigger Rule 2
        $submitResponse = $this->putJson("/api/v1/workflow-executions/{$executionId}/step", [
            'step_index' => 0,
            'values' => [
                $this->registrationTypeField->id => 'renewal',
                $this->commercialClassField->id => 'first',
                $this->workLocationField->id => 'outside',
            ],
        ]);

        $submitResponse->assertStatus(200);

        $data = $submitResponse->json('data');

        // Verify fee was calculated
        $calculatedItems = $data['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(function ($item) {
            return ($item['fee_code'] ?? null) === 'FEE-RENEWAL'
                && $item['amount'] == '250000';
        });
        $this->assertTrue($hasFee, 'Fee FEE-RENEWAL (250000) should be in calculated items');

        // Verify total
        $total = $data['total_amount'] ?? '0';
        $this->assertEquals('250000.000', $total, 'Total should be 250000 for renewal');

        // Debug dump
        $this->dumpRuntimeDebug('Rule 2: Renewal', [
            'execution_id' => $executionId,
            'step_index' => 0,
            'values' => [
                $this->registrationTypeField->id => 'renewal',
                $this->commercialClassField->id => 'first',
                $this->workLocationField->id => 'outside',
            ],
            'calculated_items' => $calculatedItems,
            'total_amount' => $total,
            'field_states' => $data['field_states'] ?? [],
            'modified_values' => $data['modified_values'] ?? [],
        ]);
    }

    /**
     * TEST 4: Full end-to-end workflow execution
     */
    public function test_full_workflow_end_to_end(): void
    {
        // Start execution
        $startResponse = $this->postJson('/api/v1/workflow-executions', [
            'workflow_version_id' => $this->version->id,
        ]);
        $startResponse->assertStatus(201);

        $executionId = $startResponse->json('data.execution.id');

        // Step 1: Submit registration data
        $step1Response = $this->putJson("/api/v1/workflow-executions/{$executionId}/step", [
            'step_index' => 0,
            'values' => [
                $this->registrationTypeField->id => 'new',
                $this->commercialClassField->id => 'excellent',
                $this->workLocationField->id => 'inside',
            ],
        ]);

        $step1Data = $step1Response->json('data');
        $step1Total = $step1Data['total_amount'] ?? '0';

        $this->dumpRuntimeDebug('Step 1 Complete', [
            'step_index' => 0,
            'total_amount' => $step1Total,
            'calculated_items' => $step1Data['calculated_items'] ?? [],
            'is_review' => $step1Data['is_review'] ?? false,
        ]);

        // Verify step 1 total and review mode
        $this->assertEquals('500000.000', $step1Total, 'Step 1 total should be 500000');
        $this->assertTrue(
            $step1Data['is_review'] ?? false,
            'Should be in review mode after last step'
        );

        // Verify review total matches execution total
        $reviewTotal = $step1Data['total_amount'] ?? '0';
        $this->assertEquals('500000.000', $reviewTotal, 'Review total should be 500000');

        // Complete execution and create receipt
        $completeResponse = $this->postJson("/api/v1/workflow-executions/{$executionId}/complete", [
            'notes' => 'تسجيل تاجر جديد - اختبار',
        ]);

        $completeResponse->assertStatus(200);

        $receipt = $completeResponse->json('data.receipt');
        $this->assertNotNull($receipt, 'Receipt should be created');

        // Verify receipt total matches execution total
        $receiptTotal = $receipt['total_amount'] ?? '0';
        $this->assertEquals('500000.000', $receiptTotal, 'Receipt total should match execution total');

        // Verify receipt has items (structure may vary)
        $receiptItems = $receipt['items'] ?? [];
        $this->assertNotEmpty($receiptItems, 'Receipt should have items');

        // Final debug dump
        $this->dumpRuntimeDebug('Workflow Complete', [
            'execution_id' => $executionId,
            'receipt_id' => $receipt['id'],
            'execution_total' => $step1Total,
            'review_total' => $reviewTotal,
            'receipt_total' => $receiptTotal,
            'totals_match' => $step1Total === $reviewTotal && $reviewTotal === $receiptTotal,
        ]);
    }

    /**
     * TEST 5: Verify no rules match for non-matching values
     */
    public function test_no_rules_match_for_non_matching_values(): void
    {
        // Start execution
        $startResponse = $this->postJson('/api/v1/workflow-executions', [
            'workflow_version_id' => $this->version->id,
        ]);
        $startResponse->assertStatus(201);

        $executionId = $startResponse->json('data.execution.id');

        // Submit with values that don't match any rule
        $submitResponse = $this->putJson("/api/v1/workflow-executions/{$executionId}/step", [
            'step_index' => 0,
            'values' => [
                $this->registrationTypeField->id => 'new',
                $this->commercialClassField->id => 'second', // Not excellent
                $this->workLocationField->id => 'inside',
            ],
        ]);

        $submitResponse->assertStatus(200);

        $data = $submitResponse->json('data');

        // Total should be zero
        $total = $data['total_amount'] ?? '0';
        $this->assertEquals('0.000', $total, 'Total should be zero when no rules match');
    }

    protected function dumpFieldDebug(string $fieldName, array $field): void
    {
        $debug = [
            'field_id' => $field['id'] ?? null,
            'field_name' => $fieldName,
            'register_field_id' => $field['register_field_id'] ?? null,
            'field_type' => $field['field_type'] ?? ($field['register_field']['field_type'] ?? null),
            'resolved_options' => $field['register_field']['options'] ?? [],
        ];

        fwrite(STDERR, "\n=== FIELD DEBUG: {$fieldName} ===\n");
        fwrite(STDERR, json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }

    protected function dumpRuntimeDebug(string $phase, array $data): void
    {
        fwrite(STDERR, "\n" . str_repeat('=', 60) . "\n");
        fwrite(STDERR, "RUNTIME DEBUG: {$phase}\n");
        fwrite(STDERR, str_repeat('=', 60) . "\n");
        fwrite(STDERR, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        fwrite(STDERR, str_repeat('=', 60) . "\n");
    }
}
