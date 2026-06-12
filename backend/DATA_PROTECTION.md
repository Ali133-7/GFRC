# 🛡️ حماية البيانات - Data Protection Guide

## ⚠️ تحذيرات مهمة

### ❌ NEVER USE في Production:
```bash
php artisan migrate:fresh          # ❌ يحذف كل البيانات
php artisan migrate:fresh --seed   # ❌ يحذف كل البيانات ثم يعيد seeding
```

### ✅ USE Instead:
```bash
# لإضافة migration جديدة
php artisan migrate

# للتراجع عن آخر migration
php artisan migrate:rollback

# لاستعادة البيانات المفقودة
php artisan data:restore

# لتشغيل seeders بدون حذف البيانات
php artisan db:seed
```

---

## 🔧 إذا فقدت البيانات:

### الخيار 1: استعادة سريعة
```bash
php artisan data:restore
```

هذا الأمر يستعيد:
- ✅ Admin user (admin/password)
- ✅ Test users (cashier, auditor)  
- ✅ جميع الصلاحيات
- ✅ جميع الأدوار
- ✅ الأقسام والمؤسسات

### الخيار 2: إعادة Seeding كامل
```bash
php artisan db:seed
```

### الخيار 3: Migration آمن
```bash
# التراجع خطوة
php artisan migrate:rollback

# ثم إعادة التقدم
php artisan migrate

# ثم استعادة البيانات
php artisan data:restore
```

---

## 📋 البيانات المحمية

البيانات التالية **لن تُحذف** عند استخدام `data:restore`:

| النوع | البيانات |
|-------|----------|
| **Users** | admin, cashier, auditor |
| **Roles** | admin, manager, cashier, auditor, data_entry |
| **Permissions** | 67+ صلاحية شاملة |
| **Departments** | المالية, HR, IT, العمليات |
| **Organizations** | المقر الرئيسي |

---

## 🔍 التحقق من البيانات

```bash
# التحقق من المستخدمين
php artisan tinker
>>> App\Models\User::count()

# التحقق من الأدوار
>>> App\Models\Role::count()

# التحقق من الصلاحيات
>>> App\Models\Permission::count()
```

---

## 📝 Best Practices

### 1. قبل أي migration:
```bash
# Export البيانات المهمة
php artisan db:seed --class=DataExportSeeder
```

### 2. بعد أي migration:
```bash
# التحقق من البيانات
php artisan tinker
>>> App\Models\User::count()
```

### 3. في Production:
```bash
# دائماً backup أولاً
php artisan backup:run

# ثم migration
php artisan migrate
```

---

## 🆘 Emergency Contacts

إذا فقدت البيانات:

1. **لا تعمل panic!**
2. **لا تعمل migrate:fresh!**
3. استخدم: `php artisan data:restore`

---

## 📞 Default Credentials

| Username | Password | Role |
|----------|----------|------|
| admin | password | admin |
| cashier | password | cashier |
| auditor | password | auditor |

**Change these in production!**
