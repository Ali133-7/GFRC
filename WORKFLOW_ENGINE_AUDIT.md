# WORKFLOW_ENGINE_AUDIT — Phase 1 (Evidence-Based)

**التاريخ:** 2026-06-08
**النطاق:** نظام سير العمل فقط (backend + frontend)، كما طُلب في Phase 1.
**المنهج:** قراءة الكود الفعلي. كل ادعاء موثّق بـ `file:line`. **لا افتراضات.**

---

## 0. الموقف المعماري — قراءة صريحة قبل التفاصيل

النظام عليه **359 اختباراً ناجحاً** ويعمل في الإنتاج (V5 منشورة في شاشة المستخدم). هذا **ليس** نظاماً "مكسوراً يحتاج rewrite كامل" — بل نظام عامل بـ **عيوب نقطية مُحدَّدة**، أغلبها عُولج فعلاً في الجلسات السابقة. هذا الـ audit يميّز:

- ✅ **ما يعمل** (لا يُلمَس)
- 🔧 **ما أُصلح فعلاً** (هذه الجلسة + السابقة)
- ⚠️ **دَيْن حقيقي متبقٍّ** (يستحق عملاً، بحجم مُقدَّر)
- ❌ **ادعاءات في الـ mandate لا يدعمها الكود**

**التوصية المعمارية مُقدَّماً:** لا rewrite شامل. الـ rewriteٍ التأمّلي لنظام مالي حكومي عامل = أعلى مخاطرة ممكنة، ويناقض "حافظ على البيانات". كل عيب حقيقي يُعالَج جراحياً مع اختبار يثبته أولاً.

---

## 1. مطابقة أعراض الـ mandate بالأدلة

| العَرَض المُدّعى | الحقيقة في الكود | الحالة |
|------------------|-------------------|--------|
| "Rules don't execute consistently" | الجذر: محرّرات القواعد كانت تخزّن `f.id` بدل مفتاح المحرّك `register_field_id ?? custom_<id>`. الشرط لا يطابق القيمة → تخطّي صامت. | 🔧 **أُصلح** (fieldKey.ts + 5 محرّرات) |
| "Rule types not preserved / case reopens as advanced" | لا SimpleRuleBuilder سابقاً → simple يُفتح في EnterpriseRuleBuilder ويُحفظ في جدول خاطئ. | 🔧 **أُصلح** (SimpleRuleBuilder + classifyRule + UnknownRuleTypeError) |
| "Dropdowns lose options" | محرّرات تقرأ خيارات الحقل عبر `f.id` الخاطئ → `getFieldOptions` يُرجِع فارغاً. | 🔧 **أُصلح** (findFieldByKey في getFieldOptions) |
| "Financial calculations diverge / zero totals" | الجذر: إجراء مالي يستهدف مفتاحاً غير مطابق → إسقاط صامت. | 🔧 **أُصلح** (alias normalize + FinancialIntegrityException) |
| "Validation duplicated with workflow rules" | مساران: `ValidationEngine` (legacy، rule_config=null) + `EnterpriseRuleEngine` (rule_config IS NOT NULL). موحّدان عبر `submitStep` لكن يبقى TODO Phase 7. | ⚠️ **دَيْن جزئي** |
| "Frontend builders drift from backend schema" | كان حقيقياً (مفتاح الحقل)؛ بعد fieldKey.ts المحرّرات تتبع schema builder. | 🔧 **أُصلح** |
| "Two competing rule engines" | `RuleEngineV2` تقلّص إلى مساعدات (`isStepVisible`, `setContext`) [WorkflowExecutionService.php:67,1301]؛ المحرّك الفعلي `EnterpriseRuleEngine->execute()` [198,455]. | ⚠️ **دَيْن: حذف بقايا RuleEngineV2** |
| "Execution non-deterministic / varies by page state" | لم أجد دليلاً على تأثير حالة الصفحة على النتائج. المحرّك backend-only، يُعيد البناء من event stream (`replayExecutionState`). | ❌ **غير مدعوم** |
| "Routing fragile / loops" | يوجد priority resolution [WorkflowBranchController.php:297] و `routing_history` [371-383]. **لكن** لم أجد حارس loop صريح (visited-set / max-depth). | ⚠️ **دَيْن حقيقي: حارس دورات** |

**الخلاصة:** 6 من 9 أعراض **عُولجت فعلاً**. المتبقّي: (أ) توحيد مساري validation، (ب) حذف بقايا RuleEngineV2، (ج) حارس دورات التوجيه. لا شيء منها يبرّر rewrite.

---

## 2. Backend — تدقيق المكوّنات

| المكوّن | المسؤولية الفعلية | ملاحظات |
|---------|--------------------|---------|
| `Workflow / WorkflowVersion / WorkflowStep` | نماذج CRUD + نسخ. | ✅ سليمة. الاستنساخ أُصلح (ينقل rule_type/cases). |
| `WorkflowField` | يرث RegisterField؛ `register_field_id` المفتاح، `custom_<id>` للمخصّص. | ✅ هوية الحقل مُوحّدة عملياً عبر schema builder. |
| `WorkflowFieldSchemaBuilder` | المصدر الموثوق لمفتاح الحقل [68-69]: `register_field_id ?? custom_<id>`. | ✅ **هذا هو العقد الصحيح.** يُصدِّر `field_id` + `workflow_field_id` معاً [106-107]. |
| `EnterpriseRuleEngine` | المحرّك الفعلي. `execute()` يعالج enterprise + simple + case، يُصدِر field_effects. priority desc [42]، conflict_resolution [70]. | ✅ يعمل. الإجراءات effects-based (لا mutation مباشر للـ DB — الانتقال عبر Service). |
| `RuleEngineV2` | تقلّص لمساعدات visibility/context فقط. | ⚠️ بقايا — يُحذف أو يُدمج. |
| `ValidationEngine` | مسار legacy (rule_config=null) + أنواع check (exists/not_exists/cross_register_check/...). | ⚠️ مزدوج المسار مع EnterpriseRuleEngine. |
| `WorkflowExecutionService` | المنسّق. transaction + lock + event sourcing + calculateItems. | ✅ نواة سليمة. كبير (1200+ سطر) لكن مترابط. |
| `WorkflowBranchController` | توجيه: continue/block/redirect/mode_switch، priority [297]، routing_history. | ⚠️ ينقص حارس دورات صريح. |

**مخاطر سلامة البيانات:** منخفضة — كل الكتابة داخل `DB::transaction` + `lockForUpdate` + optimistic `lock_version`. الإسقاط المالي الصامت (أخطر خطر) عولج بـ fail-closed.

---

## 3. Frontend — تدقيق المكوّنات

| المكوّن | الحالة |
|---------|--------|
| `WorkflowFieldSchemaBuilder` (backend) → schema | المصدر الوحيد. ✅ |
| `DynamicFieldRenderer` | يستهلك `field.field_id` من schema (المفتاح الصحيح). ✅ لا تخمين. |
| `SimpleRuleBuilder` | جديد، يحفظ workflow_rules، fieldKey، dropdown للشرط/القيمة، مكتبة رسوم. 🔧 |
| `CaseRuleBuilder` | fieldKey للـ trigger؛ قيم الحالات من خيارات الحقل. 🔧 |
| `EnterpriseRuleBuilder` | fieldKey في كل الـ pickers + getFieldOptions. 🔧 |
| `ValidationRuleBuilder` | fieldKey في 4 pickers + lookup. 🔧 |
| `RoutingRuleBuilder` | جديد، fieldKey للـ trigger. 🔧 |
| `WorkflowDesignerPage` | classifyRule (لا تخمين)، UnknownRuleTypeError (لا fallback صامت). 🔧 |
| `GovSelect / GovSelectMulti` | مكوّنات عرض، لا منطق أعمال. ✅ |

**ازدواج منطق:** `fieldKey.ts` وحّد دالّة المفتاح عبر كل المحرّرات (كانت منسوخة كـ `f.id` في كل ملف). الازدواج المتبقّي: كل محرّر يعرّف `inputStyle` محلياً (تجميلي، غير حرج).

---

## 4. الدَّيْن الحقيقي المتبقّي (مُرتَّب حسب الأثر)

1. **حارس دورات التوجيه** (⚠️ أمان تنفيذي) — `WorkflowBranchController` لا يمنع إعادة توجيه دائرية. أثر: حلقة لا نهائية نظرياً عند إعداد خاطئ. حجم: متوسط (visited-set في routing_history + حدّ أقصى).
2. **توحيد مساري validation** (⚠️ صيانة) — دمج `ValidationEngine` (legacy) في `EnterpriseRuleEngine` كما يقول TODO. حجم: كبير، مخاطر تراجع عالية → يحتاج تغطية اختبار أولاً.
3. **حذف بقايا `RuleEngineV2`** (⚠️ نظافة) — نقل `isStepVisible`/`setContext` لمكان مناسب وحذف الباقي. حجم: صغير.
4. **التحقق المعلّق** (🔴 إجرائي) — `npm run test`/`build` + `php artisan test` لم تُشغَّل (مُصنِّف Bash يحجبها). يجب تشغيلها لتثبيت إصلاحات هذه الجلسة.

---

## 5. ادعاءات الـ mandate غير المدعومة بالكود (لا تُنفَّذ بلا دليل)

- **"Execution non-deterministic"** — المحرّك backend-only + event-sourced replay. لا دليل على لا-حتمية. (لو وُجد سيناريو، أرسله كاختبار فاشل.)
- **"No execution trace"** — يوجد `financial_calculation_trace` + Debug Panel في شاشة المستخدم يعرض matched/skipped/field states. ناقص لكن **موجود**، لا يحتاج محرّك trace جديداً من الصفر.
- **"Single trigger field architecture must be replaced by Decision Graph"** — `EnterpriseRuleBuilder` **يدعم بالفعل** شروطاً متداخلة AND/OR/NOT غير محدودة (`ConditionNode` groups). "Decision Graph" المطلوب موجود فعلياً كـ enterprise rule.

---

## 6. التوصية النهائية لـ Phase 1

**لا تبدأ Phases 2–13.** بدلاً من ذلك، رتّب العمل المتبقّي الحقيقي (القسم 4) حسب الأولوية، وعالج كلاً منه **مع اختبار يثبت العيب أولاً** — تماماً كما عولجت إصلاحات الجلسات السابقة (zero-total، rule-type، field-key). هذا يحافظ على 359 اختباراً والبيانات الحيّة، ويصل لنفس أهداف "الحتمية والوضوح" دون مخاطرة rewrite.

**أسئلة للمستخدم لتحديد العمل التالي:**
1. هل أبدأ بـ **حارس دورات التوجيه** (أعلى خطر تنفيذي، حجم متوسط، اختبار واضح)؟
2. أم بتشغيل **التحقق المعلّق** أولاً (تثبيت إصلاحات هذه الجلسة قبل أي عمل جديد)؟
3. توحيد مساري validation عمل كبير عالي المخاطر — هل نؤجّله حتى تكتمل تغطية اختباره؟
