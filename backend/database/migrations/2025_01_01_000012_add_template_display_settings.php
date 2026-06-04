<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new settings for template and display customization
        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'HIDE_ZERO_BALANCES',
                'group' => 'display',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'إخفاء الأرصدة الصفرية من الوصولات',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'HIDE_EMPTY_FIELDS',
                'group' => 'display',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'إخفاء الحقول الفارغة من الوصولات',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'FIELD_SEPARATOR_STYLE',
                'group' => 'display',
                'value' => 'divider',
                'type' => 'enum:divider,space,none',
                'description' => 'نمط الفاصل بين الحقول',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'TEMPLATE_AUTO_SAVE',
                'group' => 'system',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'حفظ تلقائي لقوالب الوصولات',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'TEMPLATE_VERSION_HISTORY',
                'group' => 'system',
                'value' => '10',
                'type' => 'integer',
                'description' => 'عدد إصدارات القالب المحفوظة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'HIDE_ZERO_BALANCES',
            'HIDE_EMPTY_FIELDS',
            'FIELD_SEPARATOR_STYLE',
            'TEMPLATE_AUTO_SAVE',
            'TEMPLATE_VERSION_HISTORY',
        ])->delete();
    }
};
