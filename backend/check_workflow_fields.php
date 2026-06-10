<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WorkflowField;
use App\Models\WorkflowVersion;

// الحصول على آخر نسخة منشورة
$version = WorkflowVersion::where('status', 'active')->first();
if (!$version) {
    echo "لا توجد نسخة منشورة\n";
    exit;
}

echo "Version ID: " . $version->id . PHP_EOL . PHP_EOL;

// الحصول على جميع الحقول
$fields = WorkflowField::where('workflow_version_id', $version->id)->get();

echo "عدد الحقول: " . $fields->count() . PHP_EOL . PHP_EOL;

foreach ($fields as $field) {
    echo "Field ID: " . $field->id . PHP_EOL;
    echo "  Label: " . $field->label . PHP_EOL;
    echo "  Register Field ID: " . ($field->register_field_id ?? 'NULL') . PHP_EOL;
    echo "  Custom Name: " . ($field->custom_name ?? 'NULL') . PHP_EOL;
    echo "  Step ID: " . ($field->step_id ?? 'NULL') . PHP_EOL;
    echo "  Is Financial: " . ($field->is_financial ? 'Yes' : 'No') . PHP_EOL;
    echo PHP_EOL;
}

// البحث عن الحقل المحدد
$targetFieldId = 'db4566e7-8dc6-436f-979b-137723abb505';
$targetField = WorkflowField::find($targetFieldId);

if ($targetField) {
    echo "=== الحقل المستهدف ===" . PHP_EOL;
    echo "Field ID: " . $targetField->id . PHP_EOL;
    echo "Label: " . $targetField->label . PHP_EOL;
    echo "Register Field ID: " . ($targetField->register_field_id ?? 'NULL') . PHP_EOL;
    echo "Custom Name: " . ($targetField->custom_name ?? 'NULL') . PHP_EOL;
} else {
    echo "الحقل المستهدف غير موجود!" . PHP_EOL;
}
