# خطة تنفيذ: Receipt Templates & Guided Receipt Builder

## المشكلة: التمايز بين القوالب الموجودة والمطلوبة
- **المحتوى حالياً**: `receipt_templates` + `TemplateController` + `TemplateDesignerPage` = **Print Templates** (تصميم شكل الطباعة فقط).
- **المطلوب جديداً**: **Transaction Templates** (قوالب معاملات جاهزة تبسط إدخال البيانات).

---

## المرحلة 1: Backend — قاعدة البيانات والـ API

### 1.1 Migrations جديدة
| جدول | الغرض |
|------|-------|
| `transaction_templates` | القوالب الجاهزة (مربوطة بـ `register_id`) |
| `transaction_template_fields` | حقول كل قالب (ربط بـ `register_fields` + metadata) |
| `template_rules` | القواعد التلقائية (if X then Y = value) |
| `official_fees` | مكتبة الرسوم الرسمية (اسم، تصنيف، قيمة، تاريخ نفاذ/إلغاء) |
| `official_fee_categories` | تصنيفات الرسوم (للتنظيم) |

### 1.2 Models
- `TransactionTemplate` ← `hasMany` fields + rules
- `TransactionTemplateField` ← `belongsTo` register_field
- `TemplateRule` ← condition → action
- `OfficialFee` ← category + amount + effective dates
- `OfficialFeeCategory`

### 1.3 Controllers + Resources + Routes
| Controller | Routes |
|------------|--------|
| `TransactionTemplateController` | `GET/POST /transaction-templates`, `GET/PUT/DELETE /transaction-templates/{id}`, `POST /transaction-templates/{id}/clone`, `PATCH /transaction-templates/{id}/toggle` |
| `OfficialFeeController` | `GET/POST /official-fees`, `GET/PUT/DELETE /official-fees/{id}`, `GET /official-fees/categories` |
| `GuidedReceiptController` | `POST /guided-receipts` (إنشاء وصل من قالب) |

### 1.4 Logic أساسي
- **القواعد**: عند إنشاء وصل من قالب، نفّذ القواعد بالترتيب → احسب المبالغ → أنشئ `ReceiptItem`s.
- **الرسوم**: تقرأ من `official_fees` حسب التصنيف المختار + تاريخ النفاذ.

---

## المرحلة 2: Frontend — واجهة المدير

### 2.1 صفحات جديدة
| الصفحة | المسار | الغرض |
|--------|--------|-------|
| `TransactionTemplateListPage` | `/transaction-templates` | قائمة القوالب + بحث + مفضلة |
| `TransactionTemplateFormPage` | `/transaction-templates/new` و `/transaction-templates/:id/edit` | إنشاء/تعديل قالب |
| `OfficialFeeLibraryPage` | `/official-fees` | مكتبة الرسوم |
| `OfficialFeeFormPage` | `/official-fees/new` و `/official-fees/:id/edit` | إضافة/تعديل رسوم |

### 2.2 مكونات مشتركة
- `TemplateBuilder` — سحب وإفلات لترتيب حقول القالب
- `RuleEditor` — محرر قواعد بسيط (if field = X then field = Y)
- `FeeSelector` — اختيار رسوم من المكتبة

---

## المرحلة 3: Frontend — واجهة الموظف (الموجهة)

### 3.1 تعديل `ReceiptCreatePage`
- قبل عرض النموذج: اختيار السجل → اختيار القالب (أو "بدون قالب — الوضع الحالي").
- إذا اختير قالب: اعرض فقط حقول القالب + حساب تلقائي للمبالغ.
- إذا لم يُختر قالب: الوضع الحالي (كل الحقول).

### 3.2 مكون جديد: `GuidedReceiptForm`
- عرض خطوات: (1) اختيار القالب → (2) إدخال البيانات الأساسية → (3) اختيار التصنيف → (4) مراجعة الإجمالي → (5) حفظ.
- اختصارات لوحة مفاتيح (`Ctrl+Enter` للحفظ السريع).
- المفضلة: `localStorage` يحفظ القوالب المستخدمة كثيراً.

---

## المرحلة 4: الربط والاختبار
- ربط `GuidedReceiptController` بـ `ReceiptController` (يستخدم نفس `StoreReceiptRequest`).
- التأكد أن القوالب تقرأ `official_fees` ديناميكياً.
- اختبار end-to-end: قالب → قاعدة → وصل.

---

## الملفات التي ستُعدل
- `backend/routes/api.php`
- `frontend/src/App.tsx`
- `frontend/src/pages/receipts/ReceiptCreatePage.tsx`

## ملاحظة هامة
لن نلمس `receipt_templates` / `TemplateController` / `TemplateDesignerPage` الموجودة (تخص الطباعة).
سنستخدم أسماء جديدة واضحة: `transaction_templates` و `TransactionTemplateController` و `TransactionTemplateDesignerPage`.
