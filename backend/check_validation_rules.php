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

echo "Version ID: " . $version->id . PHP_EOL;

// الحصول على قواعد التحقق
$rules = ValidationRule::where('workflow_version_id', $version->id)
    ->where('is_active', true)
    ->whereNull('rule_config')
    ->get();

echo "عدد قواعد التحقق: " . $rules->count() . PHP_EOL;

foreach ($rules as $rule) {
    echo PHP_EOL . "Rule: " . $rule->name . PHP_EOL;
    echo "  Type: " . $rule->validation_type . PHP_EOL;
    echo "  Target Register: " . $rule->target_register_id . PHP_EOL;
    echo "  Target Fields: " . json_encode($rule->target_fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
