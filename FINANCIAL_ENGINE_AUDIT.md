# FINANCIAL_ENGINE_AUDIT — Phase 9

**التاريخ:** 2026-06-07
**النطاق:** تتبّع المسار المالي الحيّ من إدخال القيم حتى `total_amount` في الـ response.
**الطريقة:** قراءة الكود الفعلي + تشغيل الاختبارات. كل ادعاء موثّق بـ `file:line`.

---

## ملخّص تنفيذي (TL;DR)

| السؤال | الجواب القصير |
|--------|----------------|
| أين تصبح المجاميع صفراً؟ | **ليس** في `FeeEngine::resolve()` (سليم)، **ولا** في `FinancialCalculationPipeline` (كود ميّت غير موصول). المسار الحيّ هو `WorkflowExecutionService::calculateItems()`. الصفر المحتمل مصدره **عدم تطابق مفتاح الحقل** للرسوم القادمة من *rule actions*. |
| fee_code للاختبار؟ | `GOV-001` — مبذور في `TestCase::createOfficialFee()` بنسخة نشطة `15.500`، `effective_from = now()->subYear()`، `effective_to = null`. يُحَلّ بنجاح عبر المسارين. |
| هل `calculate` يصل إلى `calculated_items`؟ | نعم، السلسلة **متّسقة** end-to-end — *شرط* تطابق `field_id`. لكن `calculateExpression()` يُعيد **float** (مخالفة لمبدأ "لا float"). |
| شكل `calculated_items`؟ | `array` (افتراضي `[]`)، يُملأ للحقول المالية/الرسوم. يصبح `[]`/صفر عند انعدام حقل مالي أو عند فقدان مفتاح الإجراء. |

> **اكتشاف محوري:** `FinancialCalculationPipeline.php` (الملف الجديد) **غير موصول بأي شيء** — مرجعيّته الوحيدة هي اختباره الخاص. ينجح في اختباراته معزولاً لكنه **لا يُشغَّل في مسار الوصل الحقيقي**. **[تم حذفه ككود ميّت — 2026-06-07].**

---

## ✅ إثبات تجريبي (2026-06-07) — الجذر مؤكَّد

`tests/Feature/FinancialEngineZeroTotalTest.php` يقود المسار الحيّ عبر الخدمة:

| السيناريو | `total_amount` | الحكم |
|-----------|----------------|-------|
| `set_fee` + `target_field_id = register_field_id` | **15.500** | ✅ سليم |
| `calculate` + `target_field_id = register_field_id` | **75.000** | ✅ سليم |
| `set_fee` + `target_field_id = workflow_field.id` (خطأ تأليف) | **0.000، items=0** | ❌ **إسقاط صامت** |

**الخلاصة:** المحرك سليم تحت الاتفاقية (`target_field_id = register_field_id`). الصفر يحدث **حصراً** حين تستهدف الـ rule مُعرِّف `workflow_field.id` بدل `register_field_id` — فلا يطابق أي حقل في `calculateItems` → يسقط البند **بصمت** (لا خطأ/تحذير). هذا انتهاك مباشر لمبدأ **"لا state corruption صامت"**.

---

## 🔧 الإصلاح المُنفَّذ (2026-06-07)

1. **تطبيع المفتاح في `calculateItems`** [WorkflowExecutionService.php]: خريطة aliases تقبل `register_field_id` و `workflow_field.id` و `custom_<id>` → المفتاح القانوني. الـ rule المؤلَّفة على أي مُعرِّف تُطابَق الآن. **بلا migration بيانات.**
2. **fail-closed على الإسقاط الصامت:** إجراء `set_fee`/`calculate` بمبلغ>0 يستهدف حقلاً **مجهولاً للنسخة بأكملها** → يرمي `FinancialIntegrityException` (422، `error_code: FINANCIAL_INTEGRITY_ERROR`). أمّا الحقل في **خطوة أخرى** فيُؤجَّل لحسابها (ليس خطأً).
3. **إصلاح float:** `calculateExpression()` يُعيد الآن سلسلة BC (FormulaEvaluator يُعيد string أصلاً) بدل `(float)`.
4. **حذف الكود الميّت:** `FinancialCalculationPipeline` + اختباره.

**الاختبارات:** `tests/Feature/FinancialEngineZeroTotalTest.php` (4 اختبارات: set_fee/calculate سليم، تطبيع workflow_field.id، fail-closed). **الإجمالي 358/358 يمرّ، بلا تراجعات.**

---

## 1. أين بالضبط تصبح المجاميع صفراً؟

### المسار الحيّ الفعلي
```
Controller::submitStep()  [WorkflowExecutionController.php:88-122]
   ↓
Service::submitStep()
   ├── enterpriseEngine->execute()                    ← ينتج field_effects
   ├── bridge: field_effects → $allActions            [WorkflowExecutionService.php:236-262]
   ├── calculateItems($visibleFields, $values, $allActions)  [902-1026]  ← مكان بناء البنود
   ├── sumItems($calculatedItems)                     [1080-1089]        ← مجموع الخطوة
   ├── newTotal = bcadd(replayedState, stepTotal)     [305-306]
   └── persist total_amount = $newTotal               [339]
```

### الحُكم على كل مشتبَه

**(أ) `FeeEngine::resolve()` — سليم.** [FeeEngine.php:53-67] يُرجِع النسخة النشطة الصحيحة (`effective_from <= asOf AND (effective_to IS NULL OR >= asOf)`). مسار `set_fee` يستخدم `scopeActiveAt()` [FeeVersion.php:66-75] وهو مطابق ومضبوط. **ليس مصدر الصفر.**

**(ب) `FinancialCalculationPipeline` — كود ميّت.** غير محقون في `WorkflowExecutionService` (الـ constructor لا يحتويه [WorkflowExecutionService.php:24-36])، وغير مسجَّل في `bootstrap/` أو `config/`. **لا يؤثر على أي وصل حالياً.** (به عيب كامن مُوثّق في §1-ج للمستقبل.)

**(ج) `calculateItems()` — المسار الحيّ، وهنا الخطر الحقيقي:**

1. **عدم تطابق مفتاح الحقل (المشتبَه الأول):**
   - البنود تُفهرَس بـ `register_field_id`: `$fieldId = $field->register_field_id ?? 'custom_'.$field->id` [930].
   - إجراءات الرسوم تُلتقَط عبر نفس المفتاح: `$actionsByField[$fieldId]` [932].
   - لكن الـ effect يحمل `field_id` **كما كُتب في إعداد الـ rule مباشرة**: `$fieldId = $action['field_id']` [EnterpriseRuleEngine.php:693] → `'field_id' => $fieldId` في الـ effect [734-738, 866-868].
   - **النتيجة:** إذا أُنشئت الـ rule على `workflow_field.id` (أو أي مُعرِّف ≠ `register_field_id`)، فإن `set_fee`/`calculate` **لا يطابق أي حقل** في `calculateItems` → `$amount` يبقى `'0'` [950] → البند يسقط → **المجموع صفر**.
   - الحقول ذات `fee_code` الثابت **محصّنة** (تُحَلّ مباشرة [962-963] دون مرور بالإجراءات)، لذا يظهر الصفر **تحديداً للرسوم المدفوعة عبر rule actions**.

2. **شرط الإدراج في المجموع** [993-1001]: البند يُدرَج فقط إذا `amountIsPositive || textValue !== null`. لو فشل (أ) فالـ amount صفر؛ وإن كان الحقل بلا `text_value` يسقط البند كلياً.

### في الـ response؟
- `total_amount` **يُسطَّح** للـ frontend [WorkflowExecutionController.php:105]. لا فقدان هنا.
- لكن `financial_calculation_trace` يُحسَب [WorkflowExecutionService.php:370] و**لا يُعيده الـ controller** → الـ frontend بلا أثر لتشخيص الصفر.
- لا وجود لـ `grand_total` في response الخطوة إطلاقاً.

---

## 2. fee_code للاختبار + صحّة النسخة الزمنية

- **fee_code:** `GOV-001`.
- **المصدر:** [tests/TestCase.php:175-200] `createOfficialFee()`:
  - `OfficialFee{ fee_code:'GOV-001', is_active:true }`
  - `FeeVersion{ amount:'15.500', version:1, effective_from: now()->subYear(), effective_to: null }`
- **التحقق:** كلا المسارين يحلّانه:
  - `FeeEngine::resolve('GOV-001')` → نسخة نشطة ✅
  - `set_fee`: `OfficialFee::where('fee_code','GOV-001')->where('is_active',true)` ثم `feeVersions()->activeAt()` ✅
- **الخلاصة:** نعم، توجد نسخة نشطة بتاريخ صحيح. `GOV-001` مرجع اختبار صالح.

---

## 3. هل `calculate`/`set_fee` تصل إلى `calculated_items`؟

**نعم — السلسلة متّسقة، بشرط تطابق `field_id` (§1-ج).** خريطة المفاتيح:

| الإجراء | يصدره EnterpriseRuleEngine كـ | الجسر يقرأ | calculateItems يستهلك |
|---------|------------------------------|------------|------------------------|
| `calculate` | `result` [738] | `$effect['result']`→`resolved_amount` [249] | `act==='calculate'` → `resolved_amount` [950] |
| `set_fee` | `amount` [868] | `$effect['amount']`→`resolved_amount` [247] | `feeActions` → `resolved_amount` [974] |
| `apply_discount` | `value` [891] | `$effect['value']`→`resolved_amount` [251] | عبر الخصومات |

**لا يضيع في الـ pipeline** — لكن مُلاحظتان:
- ⚠️ **مخالفة مبدأ "لا float":** `calculateExpression()` تُصرّح `: float` وتُعيد `(float) $result` [EnterpriseRuleEngine.php:1166-1191]. هذا يحوّل ناتج BC-decimal إلى float ثم يُعاد تحويله لنص — خطر دقّة في نظام مالي. يجب أن يُعيد سلسلة BC (كما يفعل `FeeEngine::calculate`).
- المسار الحيّ يمرّ عبر **EnterpriseRuleEngine** لا `RuleEngineV2`. مسار `calculate` في RuleEngineV2 [386-389] (يستخدم `FeeEngine::calculate` بسلسلة BC صحيحة) هو المسار القديم/البسيط وغير مُستدعى في تدفّق الوصل.

---

## 4. شكل `calculated_items` و `financial_trace` الحالي

**`workflow_executions.calculated_items`:** cast `array` [WorkflowExecution.php:31]، افتراضي `[]` [WorkflowExecutionService.php:83, 671]. **ليس فارغاً بطبيعته** — يُملأ ويُضاف تراكمياً عبر `array_merge` [338].

شكل البند الواحد [calculateItems:1009-1021]:
```json
{
  "field_id": "...", "field_name": "...", "label": "...",
  "amount": "15.500", "text_value": null,
  "fee_code": "GOV-001", "fee_version_id": "...",
  "action": "set_fee", "is_insured": false,
  "insurance_value": null, "field_type": "number"
}
```

**الأثر المالي** `buildFinancialTrace()` [1029-1067] (لكل حقل مالي):
```json
{ "field_id":"...", "fee_code":"GOV-001", "raw_value":"...",
  "is_numeric":true, "calculated_amount":"15.500", "included_in_total":true }
```
يُعاد كـ `financial_calculation_trace` [370] لكن **الـ controller لا يُسطّحه** → غير مرئي للـ frontend.

**متى يصبح `[]`/صفر؟** (1) لا حقل `is_financial`/`fee_code`/`formula` أصلاً؛ أو (2) رسوم مدفوعة عبر rule action مع عدم تطابق `field_id` (§1-ج).

---

## التوصية — خطوة الإصلاح الأولى (قبل أي تعديل)

**لا تُصلِح بالتخمين.** المشتبَه الأول (تطابق `field_id`) يحتاج **إثباتاً تجريبياً**:

1. اكتب اختبار تكامل فاشل يقود قاعدة `set_fee` فعلية (rule.field_id = `workflow_field.id`) عبر `PUT /step` ويؤكّد `total_amount > 0`. إن فشل (صفر) → تأكّد جذر السبب.
2. القرار المعماري الناتج: إمّا توحيد المفتاح في `calculateItems` (تطبيع `field_id` للـ effect إلى `register_field_id`)، أو تطبيع عند بناء الـ rule.
3. بالتوازي: قرار حول `FinancialCalculationPipeline` — **يُوصَل ويحلّ محلّ `calculateItems`** أم **يُحذف ككود ميّت**؟ (لا يصحّ بقاؤه معزولاً ينجح اختبارات لا تعكس الإنتاج.)
4. إصلاح float في `calculateExpression()` → إرجاع سلسلة BC.

### عيب كامن في الـ pipeline الميّت (لو وُصِل لاحقاً)
[FinancialCalculationPipeline.php:93] `isset($feeAmounts[$field->id])` — `isset()` تُرجِع `false` للقيمة `null`، فإن أعاد `resolve()` قيمة `null` (رسم منتهٍ) يُتجاهَل الحقل بصمت → صفر صامت. استخدم `array_key_exists`.

---

## أسئلة تحتاج قراراً قبل الكود

1. **اتفاقية مفتاح الحقل في الـ rules:** هل `rule.action.field_id` يجب أن يكون `register_field_id` أم `workflow_field.id`؟ (يحدّد مكان التطبيع.)
2. **مصير `FinancialCalculationPipeline`:** وصل (واستبدال `calculateItems`) أم حذف؟
3. **هل نُسطّح `financial_calculation_trace` و `grand_total` في response الخطوة** لتمكين تشخيص الـ frontend؟
