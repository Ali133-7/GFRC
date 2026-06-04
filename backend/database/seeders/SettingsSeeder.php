<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'DEPT_NAME_AR',
                'value' => 'الدائرة المالية',
                'type' => 'string',
                'group' => 'print',
                'label_ar' => 'اسم الدائرة (بالعربي)',
                'description' => 'يظهر في رأس صفحة الطباعة',
                'is_public' => true,
            ],
            [
                'key' => 'DEPT_NAME_EN',
                'value' => 'Financial Department',
                'type' => 'string',
                'group' => 'print',
                'label_ar' => 'اسم الدائرة (بالإنجليزي)',
                'description' => 'الاسم الإنجليزي للدائرة',
                'is_public' => true,
            ],
            [
                'key' => 'DEFAULT_FISCAL_YEAR',
                'value' => (string) now()->year,
                'type' => 'number',
                'group' => 'general',
                'label_ar' => 'السنة المالية الافتراضية',
                'description' => 'السنة المالية الافتراضية عند إنشاء سجل جديد',
                'is_public' => true,
            ],
            [
                'key' => 'RECEIPT_NUMBER_FORMAT',
                'value' => '{CODE}-{YEAR}-{SEQ:06d}',
                'type' => 'string',
                'group' => 'general',
                'label_ar' => 'تنسيق رقم الوصل',
                'description' => 'تنسيق رقم الوصل: {CODE} كود السجل، {YEAR} السنة، {SEQ} التسلسل',
                'is_public' => true,
            ],
            [
                'key' => 'SYSTEM_LOGO_URL',
                'value' => '',
                'type' => 'string',
                'group' => 'print',
                'label_ar' => 'رابط شعار المؤسسة',
                'description' => 'رابط الشعار للطباعة (يفضل SVG أو PNG)',
                'is_public' => true,
            ],
            [
                'key' => 'PRINT_FOOTER_TEXT',
                'value' => 'هذا الوصل صادر من نظام الإيصالات المالية GFRC',
                'type' => 'string',
                'group' => 'print',
                'label_ar' => 'نص تذييل الطباعة',
                'description' => 'النص المكتوب أسفل صفحة الطباعة',
                'is_public' => true,
            ],
            [
                'key' => 'MAX_LOGIN_ATTEMPTS',
                'value' => '5',
                'type' => 'number',
                'group' => 'security',
                'label_ar' => 'أقصى عدد محاولات تسجيل الدخول',
                'description' => 'عدد المحاولات المسموحة قبل الحظر',
                'is_public' => false,
            ],
            [
                'key' => 'LOGIN_LOCKOUT_MINUTES',
                'value' => '15',
                'type' => 'number',
                'group' => 'security',
                'label_ar' => 'مدة الحظر (دقيقة)',
                'description' => 'مدة حظر المستخدم بعد تجاوز المحاولات',
                'is_public' => false,
            ],
            [
                'key' => 'ENABLE_AUDIT_LOG',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'security',
                'label_ar' => 'تفعيل سجل التدقيق',
                'description' => 'تسجيل جميع العمليات في سجل التدقيق',
                'is_public' => false,
            ],
            [
                'key' => 'CURRENCY_CODE',
                'value' => 'IQD',
                'type' => 'string',
                'group' => 'general',
                'label_ar' => 'رمز العملة',
                'description' => 'رمز العملة (IQD, USD, EUR)',
                'is_public' => true,
            ],
            [
                'key' => 'HIDE_ZERO_OR_EMPTY',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'print',
                'label_ar' => 'إخفاء الأرصدة الصفرية أو الحقول الفارغة',
                'description' => 'إخفاء الحقول التي قيمتها صفر أو فارغة في جميع الوصولات عند الطباعة والمعاينة',
                'is_public' => true,
            ],
        ];

        foreach ($defaults as $item) {
            if (!Setting::where('key', $item['key'])->exists()) {
                Setting::create(array_merge(['id' => (string) Str::uuid()], $item));
            }
        }
    }
}
