<?php

namespace Database\Seeders;

use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\TransactionTemplate;
use App\Models\TransactionTemplateField;
use App\Models\TemplateRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Create fee categories
        $catTrade = OfficialFeeCategory::firstOrCreate(
            ['code' => 'TRADE_CLASS'],
            ['id' => (string) Str::uuid(), 'name_ar' => 'تصنيفات التجار', 'name_en' => 'Trade Classes', 'is_active' => true]
        );

        // Create fees
        $fees = [
            ['category_id' => $catTrade->id, 'name_ar' => 'الصنف الممتاز', 'amount' => 500000],
            ['category_id' => $catTrade->id, 'name_ar' => 'الصنف الأول', 'amount' => 250000],
            ['category_id' => $catTrade->id, 'name_ar' => 'الصنف الثاني', 'amount' => 150000],
            ['category_id' => $catTrade->id, 'name_ar' => 'رسم الخدمات', 'amount' => 25000],
            ['category_id' => $catTrade->id, 'name_ar' => 'رسم الكشف', 'amount' => 10000],
        ];
        foreach ($fees as $fee) {
            OfficialFee::firstOrCreate(
                ['name_ar' => $fee['name_ar'], 'category_id' => $catTrade->id],
                array_merge(['id' => (string) Str::uuid(), 'is_active' => true], $fee)
            );
        }

        // Find a register to attach templates to
        $register = Register::first();
        if (!$register) {
            return;
        }

        // Ensure register has the needed fields
        $fields = [
            ['name' => 'merchant_name', 'label_ar' => 'اسم التاجر', 'field_type' => 'text', 'is_financial' => false],
            ['name' => 'identity_number', 'label_ar' => 'رقم الهوية', 'field_type' => 'text', 'is_financial' => false],
            ['name' => 'trade_class', 'label_ar' => 'الصنف التجاري', 'field_type' => 'select', 'is_financial' => false, 'options' => ['ممتاز', 'أول', 'ثاني']],
            ['name' => 'class_fee', 'label_ar' => 'رسم الصنف', 'field_type' => 'number', 'is_financial' => true],
            ['name' => 'service_fee', 'label_ar' => 'رسم الخدمات', 'field_type' => 'number', 'is_financial' => true],
            ['name' => 'inspection_fee', 'label_ar' => 'رسم الكشف', 'field_type' => 'number', 'is_financial' => true],
            ['name' => 'notes', 'label_ar' => 'الملاحظات', 'field_type' => 'textarea', 'is_financial' => false],
        ];

        $registerFields = [];
        foreach ($fields as $f) {
            $rf = RegisterField::firstOrCreate(
                ['register_id' => $register->id, 'name' => $f['name']],
                array_merge(['id' => (string) Str::uuid(), 'is_required' => false, 'is_visible' => true, 'sort_order' => 0], $f)
            );
            $registerFields[$f['name']] = $rf;
        }

        // Create sample template
        $template = TransactionTemplate::firstOrCreate(
            ['register_id' => $register->id, 'name_ar' => 'تسجيل تاجر جديد'],
            [
                'id' => (string) Str::uuid(),
                'name_en' => 'New Merchant Registration',
                'description' => 'قالب تسجيل تاجر جديد في السجل التجاري',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Attach fields to template
        $templateFields = [
            ['register_field_id' => $registerFields['merchant_name']->id, 'sort_order' => 1, 'is_required' => true],
            ['register_field_id' => $registerFields['identity_number']->id, 'sort_order' => 2, 'is_required' => true],
            ['register_field_id' => $registerFields['trade_class']->id, 'sort_order' => 3, 'is_required' => true],
            ['register_field_id' => $registerFields['class_fee']->id, 'sort_order' => 4, 'is_readonly' => true],
            ['register_field_id' => $registerFields['service_fee']->id, 'sort_order' => 5, 'is_readonly' => true],
            ['register_field_id' => $registerFields['inspection_fee']->id, 'sort_order' => 6, 'is_readonly' => true],
            ['register_field_id' => $registerFields['notes']->id, 'sort_order' => 7],
        ];

        foreach ($templateFields as $tf) {
            TransactionTemplateField::firstOrCreate(
                ['template_id' => $template->id, 'register_field_id' => $tf['register_field_id']],
                array_merge(['id' => (string) Str::uuid()], $tf)
            );
        }

        // Create rules
        $rules = [
            [
                'trigger_field_id' => $registerFields['trade_class']->id,
                'trigger_operator' => 'equals',
                'trigger_value' => 'ممتاز',
                'target_field_id' => $registerFields['class_fee']->id,
                'action' => 'set_amount',
                'action_value' => '500000',
            ],
            [
                'trigger_field_id' => $registerFields['trade_class']->id,
                'trigger_operator' => 'equals',
                'trigger_value' => 'أول',
                'target_field_id' => $registerFields['class_fee']->id,
                'action' => 'set_amount',
                'action_value' => '250000',
            ],
            [
                'trigger_field_id' => $registerFields['trade_class']->id,
                'trigger_operator' => 'equals',
                'trigger_value' => 'ثاني',
                'target_field_id' => $registerFields['class_fee']->id,
                'action' => 'set_amount',
                'action_value' => '150000',
            ],
            [
                'trigger_field_id' => $registerFields['trade_class']->id,
                'trigger_operator' => 'equals',
                'trigger_value' => 'ممتاز',
                'target_field_id' => $registerFields['service_fee']->id,
                'action' => 'set_amount',
                'action_value' => '25000',
            ],
            [
                'trigger_field_id' => $registerFields['trade_class']->id,
                'trigger_operator' => 'equals',
                'trigger_value' => 'ممتاز',
                'target_field_id' => $registerFields['inspection_fee']->id,
                'action' => 'set_amount',
                'action_value' => '10000',
            ],
        ];

        foreach ($rules as $r) {
            TemplateRule::firstOrCreate(
                [
                    'template_id' => $template->id,
                    'trigger_field_id' => $r['trigger_field_id'],
                    'trigger_value' => $r['trigger_value'],
                    'target_field_id' => $r['target_field_id'],
                ],
                array_merge(['id' => (string) Str::uuid(), 'is_active' => true, 'sort_order' => 0], $r)
            );
        }
    }
}
