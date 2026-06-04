<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FeeVersion;
use App\Models\IdempotencyKey;
use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\Permission;
use App\Models\Receipt;
use App\Models\ReceiptCalculationSnapshot;
use App\Models\ReceiptEvent;
use App\Models\ReceiptItem;
use App\Models\ReceiptRevision;
use App\Models\ReceiptTemplate;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TemplateElement;
use App\Models\TemplateRule;
use App\Models\TemplateStyle;
use App\Models\TransactionTemplate;
use App\Models\TransactionTemplateField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\LogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class SystemController extends ApiController
{
    public function export(): JsonResponse
    {
        $this->authorize('reset', User::class);

        $data = [
            'exported_at' => now()->toDateTimeString(),
            'version' => '1.0',
            'users' => User::withTrashed()->get()->makeVisible(['password'])->toArray(),
            'roles' => Role::all()->toArray(),
            'permissions' => Permission::all()->toArray(),
            'model_has_roles' => DB::table('model_has_roles')->get(),
            'model_has_permissions' => DB::table('model_has_permissions')->get(),
            'role_has_permissions' => DB::table('role_has_permissions')->get(),
            'registers' => Register::withTrashed()->get()->toArray(),
            'register_fields' => RegisterField::withTrashed()->get()->toArray(),
            'receipts' => Receipt::withTrashed()->get()->toArray(),
            'receipt_items' => ReceiptItem::all()->toArray(),
            'receipt_revisions' => ReceiptRevision::all()->toArray(),
            'settings' => Setting::all()->toArray(),
            'activity_log' => Activity::all()->toArray(),
        ];

        return response()->json($data, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="gfrc_backup_' . now()->format('Y-m-d_H-i-s') . '.json"',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('reset', User::class);

        $data = $request->all();

        // Validate structure
        $validator = Validator::make($data, [
            'version' => 'required|string',
            'exported_at' => 'required|string',
            'users' => 'required|array',
            'roles' => 'required|array',
            'permissions' => 'required|array',
            'registers' => 'required|array',
            'register_fields' => 'required|array',
            'receipts' => 'required|array',
            'receipt_items' => 'required|array',
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error('ملف النسخة الاحتياطية غير صالح أو ناقص', $validator->errors()->toArray(), 'INVALID_BACKUP');
        }

        try {
            DB::transaction(function () use ($data) {
                $isUuid = fn($id) => is_string($id) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);

                // 1. Clear existing data (reverse dependency order)
                DB::table('role_has_permissions')->delete();
                DB::table('model_has_permissions')->delete();
                DB::table('model_has_roles')->delete();
                DB::table('personal_access_tokens')->delete();
                DB::table('sessions')->delete();
                ReceiptItem::query()->delete();
                ReceiptRevision::query()->delete();
                Receipt::query()->forceDelete();
                RegisterField::query()->forceDelete();
                Register::query()->forceDelete();
                Setting::query()->delete();
                Activity::query()->delete();
                User::where('username', '!=', 'admin')->forceDelete();
                DB::table('permissions')->delete();
                DB::table('roles')->delete();

                // 2. Insert permissions
                $permissionIdMap = [];
                foreach ($data['permissions'] ?? [] as $permData) {
                    $permData = (array) $permData;
                    $oldId = $permData['id'] ?? null;
                    if (empty($permData['id']) || !$isUuid($permData['id'])) {
                        $permData['id'] = (string) Str::uuid();
                    }
                    $permissionIdMap[$oldId] = $permData['id'];
                    DB::table('permissions')->insert($permData);
                }

                // 3. Insert roles
                $roleIdMap = [];
                foreach ($data['roles'] ?? [] as $roleData) {
                    $roleData = (array) $roleData;
                    $oldId = $roleData['id'] ?? null;
                    if (empty($roleData['id']) || !$isUuid($roleData['id'])) {
                        $roleData['id'] = (string) Str::uuid();
                    }
                    $roleIdMap[$oldId] = $roleData['id'];
                    DB::table('roles')->insert($roleData);
                }

                // 4. Insert role_has_permissions with mapped IDs
                foreach ($data['role_has_permissions'] ?? [] as $row) {
                    $row = (array) $row;
                    $permId = $permissionIdMap[$row['permission_id'] ?? ''] ?? null;
                    $roleId = $roleIdMap[$row['role_id'] ?? ''] ?? null;
                    if ($permId && $roleId) {
                        $row['permission_id'] = $permId;
                        $row['role_id'] = $roleId;
                        DB::table('role_has_permissions')->insert($row);
                    }
                }

                // 5. Insert users (skip admin to preserve current credentials)
                $exportedAdminId = null;
                foreach ($data['users'] ?? [] as $userData) {
                    $userData = (array) $userData;
                    if ($userData['username'] === 'admin') {
                        $exportedAdminId = $userData['id'] ?? null;
                        continue;
                    }
                    if (empty($userData['id']) || !$isUuid($userData['id'])) {
                        $userData['id'] = (string) Str::uuid();
                    }
                    DB::table('users')->insert($userData);
                }

                // 6. Insert model_has_roles with mapped IDs
                foreach ($data['model_has_roles'] ?? [] as $row) {
                    $row = (array) $row;
                    if (($row['model_id'] ?? '') === $exportedAdminId) {
                        continue;
                    }
                    $roleId = $roleIdMap[$row['role_id'] ?? ''] ?? null;
                    if ($roleId && !empty($row['model_id'])) {
                        $row['role_id'] = $roleId;
                        DB::table('model_has_roles')->insert($row);
                    }
                }

                // 7. Insert model_has_permissions with mapped IDs
                foreach ($data['model_has_permissions'] ?? [] as $row) {
                    $row = (array) $row;
                    if (($row['model_id'] ?? '') === $exportedAdminId) {
                        continue;
                    }
                    $permId = $permissionIdMap[$row['permission_id'] ?? ''] ?? null;
                    if ($permId && !empty($row['model_id'])) {
                        $row['permission_id'] = $permId;
                        DB::table('model_has_permissions')->insert($row);
                    }
                }

                // 8. Insert registers
                foreach ($data['registers'] ?? [] as $registerData) {
                    $registerData = (array) $registerData;
                    if (empty($registerData['id']) || !$isUuid($registerData['id'])) {
                        $registerData['id'] = (string) Str::uuid();
                    }
                    Register::create($registerData);
                }

                // 9. Insert register fields
                foreach ($data['register_fields'] ?? [] as $fieldData) {
                    $fieldData = (array) $fieldData;
                    if (empty($fieldData['id']) || !$isUuid($fieldData['id'])) {
                        $fieldData['id'] = (string) Str::uuid();
                    }
                    RegisterField::create($fieldData);
                }

                // 10. Insert receipts
                foreach ($data['receipts'] ?? [] as $receiptData) {
                    $receiptData = (array) $receiptData;
                    if (empty($receiptData['id']) || !$isUuid($receiptData['id'])) {
                        $receiptData['id'] = (string) Str::uuid();
                    }
                    Receipt::create($receiptData);
                }

                // 11. Insert receipt items
                foreach ($data['receipt_items'] ?? [] as $itemData) {
                    $itemData = (array) $itemData;
                    if (empty($itemData['id']) || !$isUuid($itemData['id'])) {
                        $itemData['id'] = (string) Str::uuid();
                    }
                    ReceiptItem::create($itemData);
                }

                // 12. Insert receipt revisions
                foreach ($data['receipt_revisions'] ?? [] as $revData) {
                    $revData = (array) $revData;
                    if (empty($revData['id']) || !$isUuid($revData['id'])) {
                        $revData['id'] = (string) Str::uuid();
                    }
                    ReceiptRevision::create($revData);
                }

                // 13. Insert settings
                foreach ($data['settings'] ?? [] as $settingData) {
                    $settingData = (array) $settingData;
                    if (empty($settingData['id']) || !$isUuid($settingData['id'])) {
                        $settingData['id'] = (string) Str::uuid();
                    }
                    Setting::create($settingData);
                }

                // 14. Insert activity log (auto-increment id)
                foreach ($data['activity_log'] ?? [] as $logData) {
                    $logData = (array) $logData;
                    unset($logData['id']);
                    Activity::create($logData);
                }

                // Ensure admin user always has super_admin role after import
                $adminUser = User::where('username', 'admin')->first();
                if ($adminUser && !$adminUser->hasRole('super_admin')) {
                    $adminUser->assignRole('super_admin');
                }
            });
        } catch (\Exception $e) {
            return $this->error('فشل استيراد البيانات: ' . $e->getMessage(), [], 'IMPORT_FAILED');
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->success([], 'تم استيراد البيانات بنجاح. جميع البيانات المُصدرة تمت استعادتها.');
    }

    public function reset(Request $request): JsonResponse
    {
        if (!auth()->user()->hasPermissionTo('system.reset')) {
            abort(403, 'This action is unauthorized.');
        }

        $request->validate([
            'confirmation' => 'required|in:DELETE',
        ]);

        $user = auth()->user();

        DB::statement('PRAGMA foreign_keys = OFF');

        $stats = DB::transaction(function () {
            $stats = [
                'workflow_execution_events' => WorkflowExecutionEvent::count(),
                'workflow_executions' => WorkflowExecution::count(),
                'receipt_events' => ReceiptEvent::count(),
                'receipt_calculation_snapshots' => ReceiptCalculationSnapshot::count(),
                'receipt_items' => ReceiptItem::count(),
                'receipt_revisions' => ReceiptRevision::count(),
                'receipts' => Receipt::count(),
                'fee_versions' => FeeVersion::count(),
                'official_fees' => OfficialFee::count(),
                'official_fee_categories' => OfficialFeeCategory::count(),
                'workflow_versions' => WorkflowVersion::count(),
                'workflow_steps' => WorkflowStep::count(),
                'workflow_fields' => WorkflowField::count(),
                'workflow_rules' => WorkflowRule::count(),
                'workflows' => Workflow::count(),
                'template_rules' => TemplateRule::count(),
                'template_elements' => TemplateElement::count(),
                'template_styles' => TemplateStyle::count(),
                'receipt_templates' => ReceiptTemplate::count(),
                'transaction_template_fields' => TransactionTemplateField::count(),
                'transaction_templates' => TransactionTemplate::count(),
                'idempotency_keys' => IdempotencyKey::count(),
                'register_fields' => RegisterField::count(),
                'registers' => Register::count(),
                'settings' => Setting::count(),
                'activity_logs' => Activity::count(),
                'non_admin_users' => User::where('username', '!=', 'admin')->count(),
            ];

            WorkflowExecutionEvent::query()->delete();
            WorkflowExecution::query()->delete();
            ReceiptEvent::query()->delete();
            ReceiptCalculationSnapshot::query()->delete();
            ReceiptItem::query()->delete();
            ReceiptRevision::query()->delete();
            Receipt::query()->forceDelete();
            FeeVersion::query()->delete();
            OfficialFee::query()->delete();
            OfficialFeeCategory::query()->delete();
            WorkflowVersion::query()->delete();
            WorkflowStep::query()->delete();
            WorkflowField::query()->delete();
            WorkflowRule::query()->delete();
            Workflow::query()->delete();
            TemplateRule::query()->delete();
            TemplateElement::query()->delete();
            TemplateStyle::query()->delete();
            ReceiptTemplate::query()->delete();
            TransactionTemplateField::query()->delete();
            TransactionTemplate::query()->delete();
            IdempotencyKey::query()->delete();
            RegisterField::query()->forceDelete();
            Register::query()->forceDelete();
            Setting::where('key', '!=', 'system_logo')->delete();
            Activity::query()->delete();
            User::where('username', '!=', 'admin')->forceDelete();

            return $stats;
        });

        DB::statement('PRAGMA foreign_keys = ON');

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\Seeders\RolesSeeder']);

        $adminUser = User::where('username', 'admin')->first();
        if ($adminUser && !$adminUser->hasRole('super_admin')) {
            $adminUser->assignRole('super_admin');
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        activity()
            ->causedBy($user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'deleted_records' => $stats,
            ])
            ->event('system_reset')
            ->tap(function ($activity) use ($stats) {
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
                $activity->properties = $activity->properties->merge(['deleted_records' => $stats]);
            })
            ->log('system_reset');

        return $this->success([
            'deleted_records' => $stats,
            'admin_preserved' => true,
        ], 'تم إعادة تعيين النظام بنجاح. تم حذف جميع البيانات مع الحفاظ على حساب الأدمن.');
    }

    public function uploadLogo(Request $request, LogoService $logoService): JsonResponse
    {
        $this->authorize('reset', User::class);

        $request->validate([
            'logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        try {
            $oldLogoUrl = Setting::get('system_logo');
            if ($oldLogoUrl) {
                $logoService->deleteLogo($oldLogoUrl);
            }

            $newUrl = $logoService->uploadLogo($request->file('logo'));

            $setting = Setting::where('key', 'system_logo')->first();
            if ($setting) {
                $setting->update(['value' => $newUrl, 'type' => 'string']);
            } else {
                Setting::create([
                    'key' => 'system_logo',
                    'value' => $newUrl,
                    'type' => 'string',
                    'label_ar' => 'شعار النظام',
                ]);
            }

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->event('logo_uploaded')
                ->tap(function ($activity) {
                    $activity->ip_address = request()->ip();
                    $activity->user_agent = request()->userAgent();
                })
                ->log('logo_uploaded');

            return $this->success(['url' => $newUrl], 'تم رفع الشعار بنجاح');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), [], 'INVALID_LOGO');
        } catch (\Exception $e) {
            return $this->error('فشل رفع الشعار: ' . $e->getMessage(), [], 'UPLOAD_FAILED');
        }
    }
}
