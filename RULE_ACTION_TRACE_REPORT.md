# RULE ACTION TRACE REPORT — Field Value Updates

**التاريخ:** 2026-06-08
**النطاق:** تتبع دورة حياة إجراءات تغيير القيم (`set_value`, `calculate`, `set_fee`, `apply_discount`) من مطابقة القاعدة → عرض القيمة في واجهة المستخدم.

---

## 0. ملخص التنفيذ

تم تتبع كاملpipeline لتحديث القيم. الـ **backend يعمل بشكل صحيح** (جميع الاختبارات 379/379 تمر). المشكلة المحتملة في **عدم تطابق مفاتيح الحقول** بين ما تخزنه القواعد وما تستخدمه الواجهة.

---

## 1. التتبع الكامل — Rule Match → Value Display

### المرحلة 1: توليد الإجراء في EnterpriseRuleEngine

**الملف:** `backend/app/Services/EnterpriseRuleEngine.php:715-722`

```php
case 'set_value':
    $finalValues[$fieldId] = $value;
    $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_value', 'value' => $value];
```

**المخرج:** `$fieldEffects` يحتوي على `field_id` و `value`.

**الحالة:** ✅ صحيح.

---

### المرحلة 2: تحويل fieldEffects → allActions

**الملف:** `backend/app/Services/WorkflowExecutionService.php:254-281`

```php
$canonicalFieldId = $fieldIdToCanonical[$effect['field_id'] ?? ''] ?? ($effect['field_id'] ?? '');
$action = ['target_field_id' => $canonicalFieldId, 'action' => $effect['action']];
if (array_key_exists('value', $effect)) {
    $action['resolved_value'] = $effect['value'];
}
```

**خريطة المفاتيح (line 243-252):**
```php
$fieldIdToCanonical[$field->id] = $canonical;           // WorkflowField PK → canonical
$fieldIdToCanonical[$canonical] = $canonical;            // canonical → self
$fieldIdToCanonical['custom_'.$field->id] = $canonical;  // custom_<id> → canonical
$fieldIdToCanonical[$field->register_field_id] = $canonical; // register → canonical
```

**الحالة:** ✅ صحيح — التحويل يستخدم خريطة شاملة لكل مفاتيح النسخة.

---

### المرحلة 3: تطبيق الإجراءات على القيم

**الملف:** `backend/app/Services/WorkflowExecutionService.php:995-1021`

```php
if ($act === 'set_value' && $targetId) {
    $modified[$targetId] = $action['resolved_value'] ?? $action['value'] ?? '';
}
```

**المخرج:** `$modifiedValues` يحتوي على القيم المحدثة بمفاتيح canonical.

**الحالة:** ✅ صحيح — الاختبارات تثبت ذلك.

---

### المرحلة 4: النقل إلى الواجهة

**الملف:** `backend/app/Http/Controllers/Api/V1/WorkflowExecutionController.php:110`

```php
'modified_values' => $result['modified_values'],
```

**الاستجابة:** `{ data: { modified_values: { "<canonical_key>": "Premium", ... } } }`

**فك التغليف (client.ts:35-41):**
```typescript
if (data?.success === true && "data" in data) {
    response.data = data.data;  // ✅ modified_values متاح مباشرة
}
```

**الحالة:** ✅ صحيح.

---

### المرحلة 5: تطبيق القيم في الواجهة

**الملف:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx:160-161`

```typescript
if (data.modified_values) {
    setValues((prev) => ({ ...prev, ...data.modified_values }));
}
```

**الحالة:** ✅ صحيح — الدمج صحيح.

---

### المرحلة 6: عرض القيمة في الحقل

**الملف:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx:482-484`

```typescript
const fieldInput = (field: WorkflowField) => {
    const fid = resolveFieldId(field);  // register_field_id ?? custom_<id>
    const val = values[fid] ?? field.default_value ?? "";
```

**الحالة:** ⚠️ **هنا المشكلة المحتملة**.

---

## 2. الجذر المحتمل — عدم تطابق المفاتيح

### السيناريو

1. **القاعدة تُنشأ عبر الواجهة:**
   - SimpleRuleBuilder يخزن `target_field_id = fieldKey(f) = f.register_field_id ?? custom_${f.id}`

2. **القاعدة تُنشأ عبر الاختبار:**
   - الاختبار يستخدم `$this->textField1->id` (RegisterField ID)

3. **التنفيذ:**
   - الـ backend يحوّل `target_field_id` إلى canonical عبر `$fieldIdToCanonical`
   - `$modifiedValues` يُرجع القيم بمفاتيح canonical

4. **الواجهة:**
   - `resolveFieldId(field) = field.register_field_id ?? custom_${field.id}`
   - يبحث عن القيمة في `values[fid]`

### المشكلة

إذا كان `field.register_field_id` في الواجهة **مختلفاً** عن `register_field_id` في الـ backend، فإن المفاتيح لن تتطابق.

**أسباب محتملة:**
1. `WorkflowField` في الواجهة لا يحمل `register_field_id` (null)
2. `WorkflowField.id` في الواجهة مختلف عن `WorkflowField.id` في الـ backend
3. القاعدة ذُخّرت بـ `target_field_id` خاطئ (PK بدلاً من canonical)

---

## 3. التشخيص

### ما يعمل ✅
- `set_fee` → يعمل (الاختبارات تثبت)
- `calculate` → يعمل (الاختبارات تثبت)
- `set_value` → يعمل في الـ backend (الاختبارات تثبت)

### ما قد لا يعمل ⚠️
- **الواجهة لا تجد القيمة** لأن `fid` لا يطابق مفتاح `modified_values`

---

## 4. الإصلاحات المطلوبة

### الإصلاح #1: توحيد مصدر مفتاح الحقل

**الملف:** `frontend/src/pages/workflows/WorkflowExecutionPage.tsx`

التأكد من أن `resolveFieldId` يستخدم نفس المنطق مثل الـ backend:

```typescript
const resolveFieldId = (field: WorkflowField): string => {
    return field.register_field_id ?? `custom_${field.id}`;
};
```

هذا مطابق للـ backend:
```php
$canonical = $field->register_field_id ?? 'custom_'.$field->id;
```

**التحقق:** تأكد من أن `field.register_field_id` ليس `null` عند التنفيذ.

---

### الإصلاح #2: إضافة logging مؤقت للتشخيص

أضف هذا مؤقتاً في `onSuccess`:

```typescript
if (data.modified_values) {
    console.log('modified_values keys:', Object.keys(data.modified_values));
    console.log('current values keys:', Object.keys(prev));
    setValues((prev) => ({ ...prev, ...data.modified_values }));
}
```

ثم في `fieldInput`:
```typescript
const fid = resolveFieldId(field);
console.log('field fid:', fid, 'value:', values[fid], 'default:', field.default_value);
```

هذا سيكشف إذا كانت المفاتيح غير متطابقة.

---

### الإصلاح #3: التحقق من بيانات الحقل في الواجهة

تأكد من أن `field.register_field_id` موجود في بيانات الحقل القادمة من الـ API:

```typescript
// في WorkflowExecutionPage.tsx
console.log('stepFields:', stepFields.map(f => ({ id: f.id, register_field_id: f.register_field_id })));
```

إذا كان `register_field_id` = `null` لكل الحقول، فالمشكلة في الـ API response.

---

## 5. خلاصة

| المرحلة | الحالة | الملف |
|---------|--------|-------|
| Rule Match → fieldEffects | ✅ صحيح | EnterpriseRuleEngine.php:715-722 |
| fieldEffects → allActions | ✅ صحيح | WorkflowExecutionService.php:254-281 |
| allActions → modifiedValues | ✅ صحيح | WorkflowExecutionService.php:995-1021 |
| modifiedValues → API response | ✅ صحيح | WorkflowExecutionController.php:110 |
| API response → frontend values | ✅ صحيح | WorkflowExecutionPage.tsx:160-161 |
| values → field display | ⚠️ محتمل | WorkflowExecutionPage.tsx:482-484 |

**السبب الجذري المحتمل:** عدم تطابق بين `resolveFieldId(field)` في الواجهة ومفاتيح `modified_values` من الـ backend.

**الخطوة التالية:** أضف logging مؤقت في الواجهة لتحديد إذا كانت المفاتيح غير متطابقة.
