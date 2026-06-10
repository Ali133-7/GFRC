<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ValidationEngine;
use App\Models\ValidationRule;
use App\Models\WorkflowVersion;

// الحصول على آخر نسخة منشورة
$version = WorkflowVersion::where('status', 'active')->first();
if (!$version) {
    echo "لا توجد نسخة منشورة\n";
    exit;
}

echo "Version ID: " . $version->id . PHP_EOL . PHP_EOL;

// الحصول على قاعدة التحقق
$rule = ValidationRule::where('workflow_version_id', $version->id)
    ->where('name', 'like', '%تكرار%')
    ->first();

if (!$rule) {
    echo "لم يتم العثور على قاعدة منع التكرار!\n";
    exit;
}

echo "Rule ID: " . $rule->id . PHP_EOL;
echo "Rule Name: " . $rule->name . PHP_EOL;
echo "Target Register: " . $rule->target_register_id . PHP_EOL;
echo "Target Fields: " . json_encode($rule->target_fields, JSON_UNESCAPED_UNICODE) . PHP_EOL . PHP_EOL;

// محاكاة القيم
$workflowFieldId = $rule->target_fields[0]['workflow_field_id'] ?? null;
$registerFieldName = $rule->target_fields[0]['register_field_name'] ?? null;

echo "Workflow Field ID (from rule): " . $workflowFieldId . PHP_EOL;
echo "Register Field Name (from rule): " . $registerFieldName . PHP_EOL . PHP_EOL;

// اختبار 1: قيمة غير مكررة
$testValues1 = [
    $workflowFieldId => '999999', // رقم أضبارة فريد
];

echo "=== اختبار 1: قيمة غير مكررة ===" . PHP_EOL;
echo "Values: " . json_encode($testValues1, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$engine = app(ValidationEngine::class);
$result1 = $engine->runValidation($rule, $testValues1, []);
echo "Result: " . json_encode($result1, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;

// اختبار 2: قيمة مكررة (جرب رقم أضبارة موجود)
// دعنا نبحث عن رقم أضبارة موجود في السجل
$existingRecord = DB::table('records')
    ->where('register_id', $rule->target_register_id)
    ->whereNull('deleted_at')
    ->first();

if ($existingRecord) {
    $existingData = json_decode($existingRecord->data, true);
    $existingFileNumber = $existingData[$registerFieldName] ?? null;
    
    if ($existingFileNumber) {
        echo "=== اختبار 2: قيمة مكررة ===" . PHP_EOL;
        echo "Found existing record with $registerFieldName: $existingFileNumber" . PHP_EOL;
        
        $testValues2 = [
            $workflowFieldId => $existingFileNumber,
        ];
        
        echo "Values: " . json_encode($testValues2, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        $result2 = $engine->runValidation($rule, $testValues2, []);
        echo "Result: " . json_encode($result2, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
    } else {
        echo "لم يتم العثور على $registerFieldName في السجل الموجود\n";
    }
} else {
    echo "لا توجد سجلات في السجل الهدف\n";
}

// اختبار 3: استخدام register_field_id مباشرة
echo "=== اختبار 3: استخدام register_field_id ===" . PHP_EOL;
$testValues3 = [
    'db4566e7-8dc6-436f-979b-137723abb505' => '999999',
];
echo "Values: " . json_encode($testValues3, JSON_UNESCAPED_UNICODE) . PHP_EOL;
$result3 = $engine->runValidation($rule, $testValues3, []);
echo "Result: " . json_encode($result3, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
