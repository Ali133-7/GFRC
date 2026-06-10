<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ValidationRule;
use App\Models\WorkflowVersion;

// الحصول على آخر نسخة منشورة
$version = WorkflowVersion::where('status', 'active')->first();
if (!$version) {
    echo "لا توجد نسخة منشورة\n";
    exit;
}

echo "Version ID: " . $version->id . PHP_EOL . PHP_EOL;

// الحصول على قواعد التحقق بدون rule_config (legacy rules)
$legacyRules = ValidationRule::where('workflow_version_id', $version->id)
    ->where('is_active', true)
    ->whereNull('rule_config')
    ->get();

echo "عدد قواعد التحقق القديمة (بدون rule_config): " . $legacyRules->count() . PHP_EOL;
foreach ($legacyRules as $rule) {
    echo "  - " . $rule->name . " (" . $rule->validation_type . ")" . PHP_EOL;
}

echo PHP_EOL;

// الحصول على قواعد التحقق مع rule_config (enterprise rules)
$enterpriseRules = ValidationRule::where('workflow_version_id', $version->id)
    ->where('is_active', true)
    ->whereNotNull('rule_config')
    ->get();

echo "عدد قواعد التحقق المتقدمة (مع rule_config): " . $enterpriseRules->count() . PHP_EOL;
foreach ($enterpriseRules as $rule) {
    echo "  - " . $rule->name . " (" . $rule->validation_type . ")" . PHP_EOL;
}

echo PHP_EOL;

// البحث عن قاعدة "منع تكرار رقم الاضبارة"
$duplicateRule = ValidationRule::where('workflow_version_id', $version->id)
    ->where('name', 'like', '%تكرار%')
    ->first();

if ($duplicateRule) {
    echo "=== قاعدة منع التكرار ===" . PHP_EOL;
    echo "ID: " . $duplicateRule->id . PHP_EOL;
    echo "Name: " . $duplicateRule->name . PHP_EOL;
    echo "Type: " . $duplicateRule->validation_type . PHP_EOL;
    echo "Has rule_config: " . ($duplicateRule->rule_config ? 'Yes' : 'No') . PHP_EOL;
    echo "Target Register: " . $duplicateRule->target_register_id . PHP_EOL;
    echo "Target Fields: " . json_encode($duplicateRule->target_fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo "Response Type: " . $duplicateRule->response_type . PHP_EOL;
    echo "Error Message: " . $duplicateRule->error_message_ar . PHP_EOL;
} else {
    echo "لم يتم العثور على قاعدة منع التكرار!" . PHP_EOL;
}
