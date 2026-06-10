<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Register;
use Illuminate\Support\Facades\DB;

// الحصول على جميع السجلات
$registers = Register::all();

echo "=== السجلات الموجودة ===" . PHP_EOL;
foreach ($registers as $register) {
    $count = DB::table('records')
        ->where('register_id', $register->id)
        ->whereNull('deleted_at')
        ->count();
    
    echo "Register: " . $register->name_ar . " (" . $register->code . ")" . PHP_EOL;
    echo "  ID: " . $register->id . PHP_EOL;
    echo "  Records count: " . $count . PHP_EOL;
    echo PHP_EOL;
}

// التحقق من السجل الهدف
$targetRegisterId = 'c32b9747-0209-4611-aab8-02fcda3e9f29';
$targetRegister = Register::find($targetRegisterId);

if ($targetRegister) {
    echo "=== السجل الهدف ===" . PHP_EOL;
    echo "Name: " . $targetRegister->name_ar . PHP_EOL;
    echo "Code: " . $targetRegister->code . PHP_EOL;
    echo "Is Active: " . ($targetRegister->is_active ? 'Yes' : 'No') . PHP_EOL;
    
    $records = DB::table('records')
        ->where('register_id', $targetRegisterId)
        ->whereNull('deleted_at')
        ->get();
    
    echo "Records count: " . $records->count() . PHP_EOL;
    
    if ($records->count() > 0) {
        echo PHP_EOL . "=== السجلات ===" . PHP_EOL;
        foreach ($records as $record) {
            $data = json_decode($record->data, true);
            echo "Record ID: " . $record->id . PHP_EOL;
            echo "  Data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        }
    }
} else {
    echo "السجل الهدف غير موجود!" . PHP_EOL;
}
