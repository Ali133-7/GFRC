<?php

namespace Database\Seeders;

use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\ValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data seeder — creates a realistic register, official fees,
 * and a conditional workflow with multiple rule types for testing.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Register with fields ────────────────────────────────────
        $adminId = \App\Models\User::where('username', 'admin')->first()?->id;

        $register = Register::firstOrCreate(
            ['code' => 'BCC-001'],
            [
                'id' => (string) Str::uuid(),
                'name_ar' => 'غرفة تجارة بغداد',
                'name_en' => 'Baghdad Chamber of Commerce',
                'description' => 'نظام إصدار الإيصالات المالية للانتساب والتجديد',
                'is_active' => true,
                'fiscal_year' => 2026,
                'current_sequence' => 0,
                'created_by' => $adminId,
            ]
        );

        $fieldRegistrationType = RegisterField::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'name' => 'registration_type',
            'label_ar' => 'نوع الانتساب',
            'label_en' => 'Registration Type',
            'field_type' => 'select',
            'options' => [
                ['value' => 'new', 'label' => 'انتساب جديد'],
                ['value' => 'renewal', 'label' => 'تجديد'],
            ],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $fieldCategory = RegisterField::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'name' => 'category',
            'label_ar' => 'الفئة',
            'label_en' => 'Category',
            'field_type' => 'select',
            'options' => [
                ['value' => 'excellent', 'label' => 'ممتاز'],
                ['value' => 'first', 'label' => 'أول'],
                ['value' => 'second', 'label' => 'ثاني'],
            ],
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $fieldLocation = RegisterField::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'name' => 'location',
            'label_ar' => 'الموقع',
            'label_en' => 'Location',
            'field_type' => 'select',
            'options' => [
                ['value' => 'inside', 'label' => 'داخل المدينة'],
                ['value' => 'outside', 'label' => 'خارج المدينة'],
            ],
            'is_required' => true,
            'sort_order' => 3,
        ]);

        $fieldFeeAmount = RegisterField::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'name' => 'fee_amount',
            'label_ar' => 'مبلغ الرسم',
            'label_en' => 'Fee Amount',
            'field_type' => 'number',
            'is_required' => false,
            'is_financial' => true,
            'sort_order' => 4,
        ]);

        // ─── 2. Official Fees ───────────────────────────────────────────
        $feeCategory = OfficialFeeCategory::firstOrCreate(
            ['code' => 'MEMBER'],
            [
                'id' => (string) Str::uuid(),
                'name_ar' => 'رسوم الانتساب',
                'name_en' => 'Membership Fees',
            ]
        );

        $fees = [
            ['fee_code' => 'FEE-EXCELLENT-NEW', 'name_ar' => 'انتساب جديد - ممتاز', 'amount' => '500000'],
            ['fee_code' => 'FEE-FIRST-NEW', 'name_ar' => 'انتساب جديد - أول', 'amount' => '300000'],
            ['fee_code' => 'FEE-SECOND-NEW', 'name_ar' => 'انتساب جديد - ثاني', 'amount' => '200000'],
            ['fee_code' => 'FEE-RENEWAL', 'name_ar' => 'تجديد انتساب', 'amount' => '250000'],
            ['fee_code' => 'FEE-OUTSIDE-SURCHARGE', 'name_ar' => 'رسوم إضافية خارج المدينة', 'amount' => '50000'],
        ];

        foreach ($fees as $feeData) {
            $fee = OfficialFee::firstOrCreate(
                ['fee_code' => $feeData['fee_code']],
                [
                    'id' => (string) Str::uuid(),
                    'category_id' => $feeCategory->id,
                    'name_ar' => $feeData['name_ar'],
                    'name_en' => $feeData['name_ar'],
                    'is_active' => true,
                ]
            );

            if ($fee->feeVersions()->count() === 0) {
                $fee->feeVersions()->create([
                    'id' => (string) Str::uuid(),
                    'amount' => $feeData['amount'],
                    'version' => 1,
                    'effective_from' => now()->subYear(),
                    'effective_to' => now()->addYear(),
                ]);
            }
        }

        // ─── 3. Workflow + Version + Steps ──────────────────────────────
        $workflow = Workflow::firstOrCreate(
            ['code' => 'WF-MEMBERSHIP'],
            [
                'id' => (string) Str::uuid(),
                'register_id' => $register->id,
                'name_ar' => 'سير عمل الانتساب',
                'name_en' => 'Membership Workflow',
                'description' => 'سير عمل لإصدار إيصالات انتساب وتجديد',
                'created_by' => $adminId,
                'is_active' => true,
            ]
        );

        $version = WorkflowVersion::firstOrCreate(
            ['workflow_id' => $workflow->id, 'version' => 1],
            [
                'id' => (string) Str::uuid(),
                'status' => 'active',
                'published_at' => now(),
                'published_by' => $adminId,
            ]
        );

        $step1 = WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'title_ar' => 'بيانات الانتساب',
            'title_en' => 'Registration Data',
            'sort_order' => 1,
        ]);

        $step2 = WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'title_ar' => 'الرسوم والدفع',
            'title_en' => 'Fees & Payment',
            'sort_order' => 2,
        ]);

        // ─── 4. Workflow Fields ─────────────────────────────────────────
        $wfFieldRegType = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'step_id' => $step1->id,
            'register_field_id' => $fieldRegistrationType->id,
            'sort_order' => 1,
            'is_visible' => true,
            'is_required' => true,
        ]);

        $wfFieldCategory = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'step_id' => $step1->id,
            'register_field_id' => $fieldCategory->id,
            'sort_order' => 2,
            'is_visible' => true,
            'is_required' => true,
        ]);

        $wfFieldLocation = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'step_id' => $step1->id,
            'register_field_id' => $fieldLocation->id,
            'sort_order' => 3,
            'is_visible' => true,
            'is_required' => true,
        ]);

        $wfFieldFeeAmount = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'step_id' => $step2->id,
            'register_field_id' => $fieldFeeAmount->id,
            'sort_order' => 1,
            'is_visible' => true,
            'is_required' => false,
            'is_financial' => true,
        ]);

        // ─── 5. Simple Workflow Rules (case_based) ──────────────────────
        // Rule 1: New + Excellent → FEE-EXCELLENT-NEW
        WorkflowRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'انتساب جديد - ممتاز',
            'rule_type' => 'case_based',
            'trigger_field_id' => $fieldRegistrationType->id,
            'match_mode' => 'exact',
            'condition_logic' => [],
            'actions' => [],
            'cases' => [
                [
                    'value' => 'new',
                    'compound_condition' => [
                        'type' => 'group',
                        'logic' => 'and',
                        'conditions' => [
                            ['field_id' => $wfFieldRegType->id, 'operator' => 'equals', 'value' => 'new'],
                            ['field_id' => $wfFieldCategory->id, 'operator' => 'equals', 'value' => 'excellent'],
                        ],
                    ],
                    'actions' => [
                        ['type' => 'set_fee', 'field_id' => $wfFieldFeeAmount->id, 'fee_code' => 'FEE-EXCELLENT-NEW'],
                    ],
                ],
                [
                    'value' => 'new',
                    'compound_condition' => [
                        'type' => 'group',
                        'logic' => 'and',
                        'conditions' => [
                            ['field_id' => $wfFieldRegType->id, 'operator' => 'equals', 'value' => 'new'],
                            ['field_id' => $wfFieldCategory->id, 'operator' => 'equals', 'value' => 'first'],
                        ],
                    ],
                    'actions' => [
                        ['type' => 'set_fee', 'field_id' => $wfFieldFeeAmount->id, 'fee_code' => 'FEE-FIRST-NEW'],
                    ],
                ],
                [
                    'value' => 'new',
                    'compound_condition' => [
                        'type' => 'group',
                        'logic' => 'and',
                        'conditions' => [
                            ['field_id' => $wfFieldRegType->id, 'operator' => 'equals', 'value' => 'new'],
                            ['field_id' => $wfFieldCategory->id, 'operator' => 'equals', 'value' => 'second'],
                        ],
                    ],
                    'actions' => [
                        ['type' => 'set_fee', 'field_id' => $wfFieldFeeAmount->id, 'fee_code' => 'FEE-SECOND-NEW'],
                    ],
                ],
            ],
            'default_actions' => [],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Rule 2: Renewal → FEE-RENEWAL
        WorkflowRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'تجديد انتساب',
            'rule_type' => 'case_based',
            'trigger_field_id' => $fieldRegistrationType->id,
            'match_mode' => 'exact',
            'condition_logic' => [],
            'actions' => [],
            'cases' => [
                [
                    'value' => 'renewal',
                    'actions' => [
                        ['type' => 'set_fee', 'field_id' => $wfFieldFeeAmount->id, 'fee_code' => 'FEE-RENEWAL'],
                    ],
                ],
            ],
            'default_actions' => [],
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // ─── 6. Enterprise Validation Rules ─────────────────────────────
        // Rule A: Outside city surcharge (applies additional fee)
        ValidationRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'رسوم إضافية خارج المدينة',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'loc-outside',
                        'type' => 'simple',
                        'field_id' => $wfFieldLocation->id,
                        'operator' => 'equals',
                        'value' => 'outside',
                    ],
                ],
                'actions' => [
                    ['type' => 'set_fee', 'field_id' => $wfFieldFeeAmount->id, 'fee_code' => 'FEE-OUTSIDE-SURCHARGE'],
                ],
                'else_actions' => [],
            ],
            'priority' => 50,
            'is_active' => true,
        ]);

        // Rule B: Conditional visibility — hide category for renewals
        ValidationRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'إخفاء الفئة عند التجديد',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'is-renewal',
                        'type' => 'simple',
                        'field_id' => $wfFieldRegType->id,
                        'operator' => 'equals',
                        'value' => 'renewal',
                    ],
                ],
                'actions' => [
                    ['type' => 'hide_field', 'target_field_id' => $wfFieldCategory->id],
                ],
                'else_actions' => [],
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        // Rule C: Cross-field validation — fee must be > 0
        ValidationRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'التحقق من وجود مبلغ الرسم',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'fee-exists',
                        'type' => 'simple',
                        'field_id' => $wfFieldFeeAmount->id,
                        'operator' => 'greater_than',
                        'value' => '0',
                    ],
                ],
                'actions' => [
                    ['type' => 'allow', 'target_field_id' => $wfFieldFeeAmount->id],
                ],
                'else_actions' => [
                    ['type' => 'show_message', 'message_ar' => 'مبلغ الرسم يجب أن يكون أكبر من صفر'],
                ],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->command->info('Demo data seeded successfully.');
        $this->command->info("Register: {$register->name_ar} ({$register->code})");
        $this->command->info("Workflow: {$workflow->name_ar} (v{$version->version_number})");
        $this->command->info('Official fees: ' . count($fees));
        $this->command->info('Workflow rules: 2 case-based + 3 enterprise');
    }
}
