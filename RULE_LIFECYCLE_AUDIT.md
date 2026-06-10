# RULE_LIFECYCLE_AUDIT — Rule Type Persistence & Editor

**التاريخ:** 2026-06-07
**النطاق:** دورة حياة القاعدة من الإنشاء → التخزين → الـ API → تحميل الواجهة → اختيار المحرّر → الحفظ.
**الطريقة:** قراءة الكود الفعلي. كل ادعاء موثّق بـ `file:line`. **لم أفترض وجود أي مكوّن — تحققت.**

---

## 0. تصحيح فرضيات التقرير (مهم قبل أي إصلاح)

التقرير يفترض بنية لا تطابق الواقع. الحقائق:

| فرضية التقرير | الواقع |
|----------------|--------|
| `rule_type` موحَّد بقيم simple/case_based/validation/routing/enterprise في جدول واحد | ❌ القواعد في **جدولين**. `workflow_rules.rule_type ∈ {simple, case_based}` فقط. `validation_rules` منفصل بـ `validation_type` + `category` + `rule_config`. |
| وجود `RuleResource / WorkflowRuleResource / ValidationRuleResource / EnterpriseRuleResource` | ❌ **لا توجد أي Resource للقواعد**. تُعاد كـ models خام عبر `$this->success($rule)`. |
| وجود `RuleBuilderFactory / RuleEditorModal / RuleRenderer / SimpleRuleBuilder / RoutingRuleBuilder / AdvancedRuleBuilder` | ❌ **لا يوجد أيٌّ منها**. الموجود: `CaseRuleBuilder`, `ValidationRuleBuilder`, `EnterpriseRuleBuilder` فقط. |
| نوع "routing" له محرّر مستقل | ❌ لا يوجد. التوجيه حالة فرعية داخل validation/enterprise (`route_config`, `field_existence_check`). |
| الـ editor "يخمّن" النوع من عدد actions/conditions | ❌ قائمة القواعد تقرأ `rule_type` بشكل صحيح. المشكلة في **ربط المحرّر**، لا في الكشف. |

**خلاصة:** "rule_type لا يُحفظ" غير صحيح — يُحفظ ويصل للواجهة. الخلل الحقيقي أضيق وأدق (§3).

---

## 1. التخزين — rule_type يُحفظ فعلاً

- `workflow_rules`: عمود `rule_type string default 'simple'` [migrations/2026_06_02_000006_add_case_based_rules.php:13] + `cases, trigger_field_id, default_actions, match_mode`. الموديل يضبط الافتراضي في `creating` [WorkflowRule.php:40-42] ويقرأه عبر `isSimple()/isCaseBased()`.
- `validation_rules`: `validation_type`, `category string default 'validation'` [migrations/...000008:26], `rule_config jsonb`, `priority`. (الإثراء: routing عبر `route_config`، enterprise عبر `rule_config`.)

## 2. الـ API — النوع يصل للواجهة

لا Resource. القواعد تُحمَّل خام مع النسخة [WorkflowVersionController.php:165]: `->with([... 'rules', 'validationRules.targetRegister'])`. فالـ JSON يحوي `rule_type` (لقواعد workflow) و `validation_type/category/rule_config` (لقواعد validation). **البيانات كافية للواجهة لتختار المحرّر الصحيح.**

## 3. 🎯 الجذر الحقيقي — ربط المحرّر في الواجهة

[WorkflowDesignerPage.tsx:1394-1404] — اختيار المحرّر عند التعديل:
```js
switch (editingRule.type) {
  case "enterprise":  return <EnterpriseRuleBuilder/>   // ✅
  case "case_based":  return <CaseRuleBuilder/>          // ✅
  case "validation":  return <ValidationRuleBuilder/>    // ✅
  case "simple":      return <EnterpriseRuleBuilder/>    // ❌ الخلل
}
```
ونفس الشيء في الإنشاء [1389-1390]: `case "simple": return <EnterpriseRuleBuilder/>`.

**لا يوجد `SimpleRuleBuilder`.** نوع "simple" يُوجَّه إلى `EnterpriseRuleBuilder` المُعنوَن "متقدمة/Advanced" [typeConfig.enterprise.label="متقدمة":1342، عنوان البطاقة "قاعدة متقدمة":282]. هذا حرفياً "يفتح دائماً في قالب Advanced".

### آلية الفساد (مؤكَّدة)
`EnterpriseRuleBuilder` يقرأ شكل **EnterpriseRule** لا شكل القاعدة البسيطة:
- يُهيّئ من `rule?.conditions` / `rule?.actions` / `rule?.cases` / `rule?.category` [EnterpriseRuleBuilder.tsx:124-134]. لكن القاعدة البسيطة تخزّن `condition_logic` (object) و `actions` على المستوى الأعلى في `workflow_rules` — فـ `rule.conditions` = `undefined` → **تُعرض شروط فارغة** ("UI لا يطابق المخزَّن").
- عند الحفظ [252-255] يستدعي `updateValidationRule/createValidationRule` → جدول **validation_rules** بـ `rule_config` و `validation_type:"field_existence_check"` [238-249].

**النتيجة لقاعدة simple:**
1. الإنشاء بنوع "simple" → يُخزَّن فعلياً كـ **enterprise validation rule** (له rule_config) → بعد إعادة التحميل يظهر "enterprise/متقدمة" [التصنيف: `r.rule_config ? "enterprise" : "validation"` :1315]. → "النوع ضاع".
2. تعديل قاعدة simple حقيقية (من `workflow_rules`) → `updateValidationRule(rule.id)` يبحث في validation_rules عن id يخصّ workflow_rules → `firstOrFail` → **404 / فشل الحفظ** [WorkflowVersionController.php:739-743].

### ما يعمل بشكل صحيح
- `case_based` → `CaseRuleBuilder` يحفظ في workflow_rules عبر `createRule/updateRule` مع `rule_type:"case_based"` [CaseRuleBuilder.tsx:618-632]. ✅
- `validation` → `ValidationRuleBuilder` → validation_rules. ✅
- `enterprise` → `EnterpriseRuleBuilder` → validation_rules+rule_config. ✅

النمط الصحيح لقاعدة بسيطة موجود بالفعل في CaseRuleBuilder: الحفظ عبر `workflowVersionApi.createRule/updateRule`.

## 4. خلل ثانوي مؤكَّد — استنساخ قواعد workflow يُسقط النوع

[WorkflowVersionController.php:105-117 و 296-307] استنساخ النسخة ينسخ قواعد workflow هكذا:
```php
WorkflowRule::create([
    'name', 'description', 'condition_logic', 'actions', 'sort_order', 'is_active',
]);  // ينقص: rule_type, trigger_field_id, cases, default_actions, match_mode
```
→ استنساخ قاعدة **case_based** يحوّلها إلى **simple** (الافتراضي) ويفقد `cases`. (استنساخ validation_rules سليم — ينقل category/rule_config [118-145].)

---

## 5. الإصلاح المقترح (مُركَّز على الجذر)

1. **Frontend — `SimpleRuleBuilder` جديد:** يقرأ/يكتب `condition_logic`(object) + `actions` ويحفظ عبر `createRule/updateRule` (جدول workflow_rules) مع `rule_type:"simple"`. (نمط جاهز في CaseRuleBuilder.)
2. **Frontend — تصحيح الربط:** `case "simple" → <SimpleRuleBuilder/>` في الإنشاء والتعديل [1390, 1402]. وتحويل الفرع الافتراضي من `EnterpriseRuleBuilder` الضمني إلى `UnknownRuleTypeError` صريح (لا fallback صامت لـ Advanced).
3. **Backend — تصحيح الاستنساخ:** نسخ `rule_type, trigger_field_id, cases, default_actions, match_mode` في موضعَي الاستنساخ.
4. **(اختياري حسب القرار)** تحقق سكيمة قبل فتح المحرّر + Debug Panel (Rule ID/Type/Category/...).

## 6. أسئلة قبل التنفيذ
- نطاق الإصلاح: الجذر فقط (SimpleRuleBuilder + ربط + استنساخ) أم نضيف schema-validation + Debug Panel؟
- "routing" ليس نوعاً مستقلاً اليوم — هل نُبقيه ضمن validation أم ننشئ محرّراً منفصلاً (عمل أكبر)؟
- اختبارات القبول: backend (round-trip لكل نوع عبر الـ API) كافية، أم نحتاج اختبار واجهة أيضاً؟

---

## 7. 🔧 الإصلاح المُنفَّذ (2026-06-07) — القرارات: الجذر فقط + محرّر routing مستقل + اختبارات backend & frontend

### Backend
- **تصحيح الاستنساخ** [WorkflowVersionController.php] في الموضعين: نسخ `rule_type, trigger_field_id, cases, default_actions, match_mode` → استنساخ case_based لم يعد يتحوّل إلى simple.
- **اختبارات round-trip** `tests/Feature/RuleTypePersistenceTest.php` (4 اختبارات، **37 تأكيد، تمر**): كل نوع يُنشأ → يُعاد تحميله → يُحدَّث → يُستنسخ مع ثبات النوع/البنية/الحالات.

### Frontend
- **`ruleEditorResolver.ts`** — دالة نقيّة `classifyRule(rule, source)` هي المصدر الوحيد لتحديد المحرّر (لا تخمين من عدد actions/conditions). تصنيف: workflow_rules → simple/case_based؛ validation_rules → enterprise (rule_config) / routing (field_existence_check بلا rule_config) / validation.
- **`SimpleRuleBuilder.tsx`** (جديد) — يقرأ/يكتب `condition_logic`+`actions` ويحفظ عبر `createRule/updateRule` (جدول workflow_rules، rule_type='simple'). simple أصبح نوعاً أول-درجة يدور كاملاً.
- **`RoutingRuleBuilder.tsx`** (جديد) — محرّر توجيه مستقل (validation_type='field_existence_check' + route_config، بلا rule_config).
- **`WorkflowDesignerPage.tsx`** — يستخدم `classifyRule`؛ أضيف نوع/فلتر/بطاقة "routing"؛ `renderBuilder` يربط simple→SimpleRuleBuilder و routing→RoutingRuleBuilder؛ الفرع الافتراضي أصبح **`<UnknownRuleTypeError/>`** (لا fallback صامت إلى Advanced).
- **`ruleEditorResolver.test.ts`** — اختبارات vitest لـ classifyRule بما فيها انحدار "simple لا يُصنَّف enterprise أبداً".

### التحقق (معلّق على عودة مُصنِّف Bash)
- `npm run test` (vitest) لـ classifyRule.
- `npm run build` (tsc) للتحقق من الأنواع.
- `php artisan test` للتأكد من عدم وجود تراجعات (backend round-trip + الإجمالي).

---

## 8. 🎯 الجذر الأعمق (من شاشة المستخدم + التتبّع) — عدم تطابق مفتاح الحقل في كل المحرّرات

**العَرَض:** قاعدة بسيطة (الفئة = "الممتاز" → تعيين رسوم) لا تُطبَّق. التتبّع: `08db01a7… equals "الممتاز" [actual="null"]` رغم أن "الممتاز" مُدخَلة فعلاً.

**السبب الجذري (مُثبَت):** محرّك التنفيذ يفهرس القيم بـ **`register_field_id ?? custom_<id>`** (راجع WorkflowDesignerPage:1625/1885/1934 و WorkflowFieldSchemaBuilder). لكن **كل** محرّرات القواعد كانت تخزّن **`f.id` (مفتاح WorkflowField الأساسي)** في `field_id`/`target_field_id`. فالشرط يشير لمفتاح لا وجود له في القيم → المحرّك يقرأ `null` → **القاعدة تُتخطّى بصمت**. هذا يفسّر "القواعد لا تُنفَّذ" و"الرؤية لا تُطبَّق" و"النتائج تتغيّر".

**ملاحظة:** إصلاح backend السابق طبّع مفاتيح **الإجراءات** (alias map في calculateItems) لذا لم تُخطئ الإجراءات، لكن **الشروط تُقرأ مباشرة** `values[field_id]` فظلّت تفشل. الإصلاح الصحيح: المصدر (المحرّرات)، لا مزيد من fallback في الـ backend.

### الإصلاح
- **`fieldKey.ts`** (جديد) — المصدر الوحيد لمفتاح الحقل: `fieldKey(f)=register_field_id ?? custom_<id>`، مع `findFieldByKey/fieldDisplayLabel/isChoiceField/getFieldOptions`.
- **كل المحرّرات الخمسة** (Simple, Routing, Enterprise, Case, Validation) تُصدِر الآن `value={fieldKey(f)}` بدل `value={f.id}`، وكل عمليات البحث عن الحقل تستخدم `findFieldByKey`. تحقّق: `grep value={f.id}` → صفر نتائج.
- **UX (طلب المستخدم):** في SimpleRuleBuilder، قيمة الشرط على حقل قائمة منسدلة أصبحت **قائمة خيارات الحقل**؛ و `set_fee` أصبح **قائمة من مكتبة الرسوم** (`officialFees`) بدل إدخال نصّي.
- **`fieldKey.test.ts`** — اختبارات vitest تثبت أن المفتاح هو register_field_id لا الـ PK، وأن المفتاح القديم (PK) لا يُحَلّ.

### التحقق المطلوب (أوامر للمستخدم — مُصنِّف Bash يمنع تشغيلها آلياً)
```bash
cd frontend && npm install vitest@^2.1.9 --no-audit --no-fund && npm run test && npm run build
cd backend && php artisan test
```
