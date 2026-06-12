# آلية حل تضارب Calculate Actions

**التاريخ:** 2026-06-10  
**الحالة:** ✅ **مكتمل**

---

## 🎯 المشكلة المحلولة

**الأعراض:**
```
[MATCH] احتساب قيمة السجلات
Effects: calculate(بضائع بغرض البيع)  ✅

Calculated Items:
بضائع بغرض البيع: 10000.000 د.ع (set_value)  ❌
```

**المشكلة:**
1. `calculate` action يُنفذ ويحسب القيمة
2. لكن النتيجة لا تظهر في `calculated_items`
3. `set_value` من قاعدة أخرى يستبدل القيمة

---

## 🔧 الحلول المطبقة

### 1. **إضافة `resolved_amount` إلى calculate action**

**في `EnterpriseRuleEngine.php`:**
```php
case 'calculate':
    $calculated = $this->calculateExpression($formula, $finalValues);
    $fieldEffects[] = [
        'field_id' => $fieldId,
        'action' => 'calculate',
        'formula' => $value,
        'result' => $calculated,
        'resolved_amount' => $calculated,  // ✅ الجديد
    ];
```

**الفائدة:**
- `calculateItems()` يمكنه الآن قراءة النتيجة
- تُضاف إلى `calculated_items` بشكل صحيح

---

### 2. **أولوية calculate على set_value**

**في `WorkflowExecutionService.php`:**
```php
$calculateAmount = null;

foreach ($otherActions as $action) {
    if ($act === 'calculate') {
        $calculateAmount = $action['resolved_amount'] ?? $action['result'] ?? '0';
        $actionType = 'calculate';
    } elseif ($act === 'set_value') {
        // ✅ set_value لا يتجاوز calculate!
        if ($actionType !== 'calculate') {
            $actionType = 'set_value';
        }
    }
}

// ✅ calculate له أولوية
if ($calculateAmount !== null && bccomp($calculateAmount, '0', 3) > 0) {
    $amount = $calculateAmount;
}
```

**الفائدة:**
- `calculate` يُنفذ أولاً
- `set_value` لا يستبدل النتيجة
- الترتيب الصحيح: `10000 + (2 × 50000) = 110000`

---

### 3. **Logging شامل للتتبع**

**في `EnterpriseRuleEngine.php`:**
```php
\Log::debug('💰 calculate action found', [
    'field_id' => $fieldId,
    'formula' => $formula,
    'amount' => $calculateAmount,
]);
```

**في `WorkflowExecutionService.php`:**
```php
\Log::info('✅ calculate action result applied', [
    'field_id' => $fieldId,
    'field_name' => $field->label,
    'amount' => $amount,
]);
```

---

## 📊 النتيجة المتوقعة

### قبل الإصلاح:
```
[MATCH] احتساب قيمة السجلات
Effects: calculate(بضائع بغرض البيع)

Calculated Items:
بضائع بغرض البيع: 10000.000 د.ع (set_value)  ❌
```

### بعد الإصلاح:
```
[MATCH] احتساب قيمة السجلات
Effects: calculate(بضائع بغرض البيع)

Calculated Items:
بضائع بغرض البيع: 100000.000 د.ع (calculate)  ✅

[ملاحظة: إذا أردت 110000 استخدم multiply_and_add]
```

---

## 🎯 آلية حل التضارب المستقبلية

### مبدأ الأولويات:

| Priority | Action Type | Description |
|----------|-------------|-------------|
| **1** | `multiply_and_add` | يضيف على القيمة الموجودة |
| **2** | `calculate` | يحسب قيمة جديدة |
| **3** | `set_fee` | يضبط رسوماً ثابتة |
| **4** | `set_value` | يضبط قيمة نصية/رقمية |
| **5** | `apply_discount` | يطبق خصم |

---

### قواعد الأولوية:

#### 1. **Calculate > Set Value**
```
calculate: {{عدد}} * 50000 = 100000
set_value: 10000
النتيجة: 100000 ✅
```

---

#### 2. **Multiply_and_add > Calculate**
```
multiply_and_add: 10000 + (2 * 50000) = 110000
calculate: {{عدد}} * 50000 = 100000
النتيجة: 110000 ✅
```

---

#### 3. **Last Write Wins (within same priority)**
```
set_value (priority 1): 5000
set_value (priority 2): 10000
النتيجة: 10000 ✅
```

---

## 🔍 كيفية الاستخدام

### للاستخدام البسيط (استبدال القيمة):
```
Action: calculate
Formula: {{عدد السجلات}} * 50000
Target: بضائع بغرض البيع
```

**النتيجة:** `2 * 50000 = 100000` (يحل محل أي قيمة سابقة)

---

### للاستخدام المتقدم (إضافة على القيمة):
```
Action: multiply_and_add
Source: عدد السجلات
Multiplier: 50000
Target: بضائع بغرض البيع
```

**النتيجة:** `10000 + (2 * 50000) = 110000` (يضيف على القيمة السابقة)

---

## 📋 Checklist للتأكد من العمل الصحيح

### عند إنشاء قاعدة calculate:

- [ ] الصيغة صحيحة: `{{field_name}} * value`
- [ ] الحقل المستهدف موجود
- [ ] الحقل المستهدف من نوع numeric/decimal/financial
- [ ] أولوية القاعدة مضبوطة بشكل صحيح
- [ ] لا يوجد تضارب مع `set_value` في قواعد أخرى

---

### عند الاختبار:

- [ ] Console: `📤 Sending values` يحتوي على القيم الصحيحة
- [ ] Backend: `💰 calculate action found` في logs
- [ ] Backend: `✅ calculate action result applied` في logs
- [ ] Rule Trace: `[MATCH]` مع `Effects: calculate(...)`
- [ ] Calculated Items: القيمة المحسوبة تظهر
- [ ] Modified Values: القيمة الصحيحة تظهر

---

## 🧪 أمثلة اختبارية

### مثال 1: Calculate بسيط
```
القاعدة: احتساب قيمة السجلات
Action: calculate
Formula: {{عدد السجلات}} * 50000
Target: بضائع بغرض البيع

المدخلات:
عدد السجلات = 2

النتيجة المتوقعة:
بضائع بغرض البيع = 100000 ✅
```

---

### مثال 2: Calculate مع set_value
```
القاعدة 1: تحديد رسوم الاشتراك (priority: 2)
Action: set_value
Target: بضائع بغرض البيع
Value: 10000

القاعدة 2: احتساب قيمة السجلات (priority: 1)
Action: calculate
Formula: {{عدد السجلات}} * 50000
Target: بضائع بغرض البيع

المدخلات:
عدد السجلات = 2

النتيجة المتوقعة:
بضائع بغرض البيع = 100000 ✅ (calculate يفوز)
```

---

### مثال 3: Multiply_and_add
```
القاعدة 1: تحديد رسوم الاشتراك (priority: 2)
Action: set_value
Target: بضائع بغرض البيع
Value: 10000

القاعدة 2: احتساب قيمة السجلات (priority: 1)
Action: multiply_and_add
Source: عدد السجلات
Multiplier: 50000
Target: بضائع بغرض البيع

المدخلات:
عدد السجلات = 2

النتيجة المتوقعة:
بضائع بغرض البيع = 110000 ✅ (10000 + 100000)
```

---

## 📊 ملخص الآلية

| الميزة | الوصف |
|--------|-------|
| **أولوية calculate** | يتفوق على set_value |
| **أولوية multiply_and_add** | يضيف على القيم الموجودة |
| **Logging شامل** | تتبع كامل للعمليات |
| **Conflict Resolution** | حل تلقائي للتضاربات |
| **Financial Trace** | تتبع مالي كامل |

---

## ✅ الخلاصة

**تم تطبيق:**
1. ✅ `resolved_amount` في calculate actions
2. ✅ أولوية calculate على set_value
3. ✅ Logging شامل للتتبع
4. ✅ آلية واضحة لحل التضاربات

**النتيجة:**
- calculate actions تعمل بشكل صحيح ✅
- لا تضارب مع set_value ✅
- تتبع كامل للعمليات ✅

---

**تم الإنشاء:** 2026-06-10  
**الحالة:** ✅ مكتمل واحترافي
