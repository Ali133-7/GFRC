<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\HelpArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelpCenterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = HelpArticle::where('is_active', true)->orderBy('sort_order');

        if ($request->has('page_key')) {
            $query->where('page_key', $request->input('page_key'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title_ar', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('content_ar', 'like', "%{$search}%")
                  ->orWhere('content_en', 'like', "%{$search}%");
            });
        }

        $articles = $query->get();
        return $this->success($articles);
    }

    public function getByPageKey(string $pageKey): JsonResponse
    {
        $articles = HelpArticle::where('page_key', $pageKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($articles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page_key' => 'required|string|max:100',
            'category' => 'nullable|string|max:50',
            'title_ar' => 'required|string|max:300',
            'title_en' => 'nullable|string|max:300',
            'content_ar' => 'required|string',
            'content_en' => 'nullable|string',
            'media' => 'nullable|array',
            'links' => 'nullable|array',
            'examples' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $article = HelpArticle::create($data);
        return $this->success($article, 'تم إنشاء المقالة بنجاح', [], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $article = HelpArticle::findOrFail($id);

        $data = $request->validate([
            'page_key' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'title_ar' => 'nullable|string|max:300',
            'title_en' => 'nullable|string|max:300',
            'content_ar' => 'nullable|string',
            'content_en' => 'nullable|string',
            'media' => 'nullable|array',
            'links' => 'nullable|array',
            'examples' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $article->update($data);
        return $this->success($article);
    }

    public function destroy(string $id): JsonResponse
    {
        $article = HelpArticle::findOrFail($id);

        if ($article->is_system) {
            return $this->error('لا يمكن حذف مقالة نظام', 422);
        }

        $article->delete();
        return $this->success([], 'تم حذف المقالة');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'articles' => 'required|array',
            'articles.*.id' => 'required|string',
            'articles.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['articles'] as $articleData) {
                HelpArticle::where('id', $articleData['id'])
                    ->update(['sort_order' => $articleData['sort_order']]);
            }
        });

        return $this->success(HelpArticle::orderBy('sort_order')->get());
    }

    public function seedSystemArticles(): JsonResponse
    {
        $systemArticles = [
            [
                'page_key' => 'dashboard',
                'category' => 'general',
                'title_ar' => 'لوحة التحكم',
                'content_ar' => "لوحة التحكم هي الصفحة الرئيسية للنظام. تعرض لك:\n\n• ملخص السجلات والإحصائيات\n• آخر النشاطات\n• الوصول السريع للوظائف الأساسية\n\nيمكنك التنقل بين الأقسام المختلفة من القائمة الجانبية.",
                'sort_order' => 1,
                'is_system' => true,
            ],
            [
                'page_key' => 'registers',
                'category' => 'registers',
                'title_ar' => 'إدارة السجلات',
                'content_ar' => "هذه الصفحة تستخدم لإنشاء وإدارة السجلات.\n\nيمكنك:\n• إضافة سجل جديد\n• تعديل السجل\n• حذف السجل\n• إضافة حقول لكل سجل\n• ربط السجل بسير العمل\n\nكل سجل يمثل مجموعة بيانات مستقلة مثل (الموظفين، الضبائر، الكتب الرسمية).",
                'sort_order' => 2,
                'is_system' => true,
            ],
            [
                'page_key' => 'workflow_designer',
                'category' => 'workflows',
                'title_ar' => 'إنشاء سير العمل',
                'content_ar' => "مصمم سير العمل يتيح لك بناء تدفقات عمل معقدة.\n\nالمكونات:\n\n1. **الخطوات (Steps)**: مراحل سير العمل المتتابعة\n2. **الحقول (Fields)**: البيانات المطلوبة في كل خطوة\n3. **القواعد (Rules)**: منطق الشروط والإجراءات\n4. **قواعد التحقق (Validation)**: منع التكرار والتأكد من صحة البيانات\n\nنصائح:\n• ابدأ بإنشاء الخطوات أولاً\n• أضف الحقول وربطها بالخطوات\n• عيّن القواعد للتحكم في التدفق\n• استخدم معاينة لاختبار سير العمل",
                'sort_order' => 3,
                'is_system' => true,
            ],
            [
                'page_key' => 'validation_rules',
                'category' => 'workflows',
                'title_ar' => 'قواعد التحقق',
                'content_ar' => "قواعد التحقق تعمل قبل حفظ البيانات للتأكد من صحتها.\n\nأنواع التحقق:\n\n1. **منع التكرار**: التأكد من عدم وجود قيمة مكررة\n2. **التحقق من الوجود**: التأكد من وجود قيمة مسبقاً\n3. **تحقق متعدد الحقول**: البحث بأكثر من حقل\n4. **بحث في السجل**: البحث في سجل معين\n5. **منشئ الاستعلامات**: بناء استعلام مرئي\n6. **SQL متقدم**: كتابة استعلام يدوي\n\nأنواع الاستجابة:\n• **خطأ**: منع الحفظ تماماً\n• **تحذير**: تنبيه مع السماح بالمتابعة\n• **تأكيد**: سؤال المستخدم قبل المتابعة",
                'sort_order' => 4,
                'is_system' => true,
            ],
            [
                'page_key' => 'receipts',
                'category' => 'receipts',
                'title_ar' => 'الإيصالات',
                'content_ar' => "صفحة الإيصالات لإدارة جميع الإيصالات المالية.\n\nيمكنك:\n• إنشاء إيصال جديد\n• عرض تفاصيل الإيصال\n• طباعة الإيصال\n• إلغاء إيصال\n• مراجعة التعديلات",
                'sort_order' => 5,
                'is_system' => true,
            ],
            [
                'page_key' => 'users',
                'category' => 'settings',
                'title_ar' => 'إدارة المستخدمين',
                'content_ar' => "هذه الصفحة لإدارة مستخدمي النظام.\n\nيمكنك:\n• إضافة مستخدم جديد\n• تعديل بيانات المستخدم\n• تعيين الأدوار والصلاحيات\n• تعطيل أو تفعيل المستخدم\n• عرض سجل النشاطات",
                'sort_order' => 6,
                'is_system' => true,
            ],
            [
                'page_key' => 'settings',
                'category' => 'settings',
                'title_ar' => 'الإعدادات',
                'content_ar' => "صفحة الإعدادات للتحكم في إعدادات النظام العامة.\n\nتشمل:\n• إعدادات النظام الأساسية\n• مركز المساعدة\n• الشعار والهوية\n• النسخ الاحتياطي\n• استيراد وتصدير البيانات",
                'sort_order' => 7,
                'is_system' => true,
            ],
        ];

        foreach ($systemArticles as $articleData) {
            HelpArticle::updateOrCreate(
                ['page_key' => $articleData['page_key'], 'is_system' => true],
                $articleData
            );
        }

        return $this->success([], 'تم تحديث مقالات النظام');
    }
}
