<?php

namespace Tests;

use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected User $manager;
    protected User $auditor;
    protected Register $register;
    protected RegisterField $financialField;
    protected RegisterField $textField;
    protected OfficialFee $officialFee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRolesAndPermissions();
        $this->createUsers();
        $this->createRegister();
        $this->createOfficialFee();
    }

    protected function createRolesAndPermissions(): void
    {
        $adminId = (string) Str::uuid();
        $cashierId = (string) Str::uuid();
        $managerId = (string) Str::uuid();
        $auditorId = (string) Str::uuid();

        \DB::table('roles')->insert([
            ['id' => $adminId, 'name' => 'admin', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $cashierId, 'name' => 'cashier', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $managerId, 'name' => 'manager', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $auditorId, 'name' => 'auditor', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $allPermissions = [
            'receipts.create', 'receipts.view', 'receipts.issue', 'receipts.*', 'view-receipt',
            'create-receipt', 'issue-receipt', 'cancel-receipt', 'revise-receipt', 'print-receipt',
            'view-all-receipts', 'manage-registers',
            'registers.*', 'registers.view', 'registers.manage', 'registers.manageFields',
            'workflows.*', 'workflows.view', 'workflows.manage', 'manage-settings',
            'audit-logs.view', 'view-audit-logs', 'reports.view', 'view-reports',
            'users.*', 'users.view', 'users.manage', 'users.updateRoles',
            'roles.*', 'roles.view', 'roles.manage',
            'view-registers', 'reset', 'system.reset',
        ];

        $permIds = [];
        foreach ($allPermissions as $perm) {
            $permId = (string) Str::uuid();
            $permIds[$perm] = $permId;
            \DB::table('permissions')->insert([
                'id' => $permId,
                'name' => $perm,
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolePerms = [
            $adminId => $allPermissions,
            $cashierId => ['receipts.create', 'receipts.view', 'receipts.issue', 'view-receipt', 'create-receipt', 'issue-receipt', 'print-receipt'],
            $managerId => ['receipts.*', 'registers.*', 'workflows.*', 'view-receipt', 'create-receipt', 'issue-receipt', 'cancel-receipt', 'revise-receipt', 'print-receipt', 'view-all-receipts', 'manage-registers', 'registers.view', 'registers.manage', 'workflows.view', 'workflows.manage'],
            $auditorId => ['receipts.view', 'view-receipt', 'audit-logs.view', 'view-audit-logs', 'reports.view', 'view-reports', 'view-all-receipts', 'print-receipt'],
        ];

        foreach ($rolePerms as $roleId => $perms) {
            foreach ($perms as $perm) {
                \DB::table('role_has_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permIds[$perm],
                ]);
            }
        }

        // Clear Spatie Permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function createUsers(): void
    {
        $this->admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');

        $this->cashier = User::create([
            'name' => 'Cashier User',
            'username' => 'cashier',
            'email' => 'cashier@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->cashier->assignRole('cashier');

        $this->manager = User::create([
            'name' => 'Manager User',
            'username' => 'manager',
            'email' => 'manager@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');

        $this->auditor = User::create([
            'name' => 'Auditor User',
            'username' => 'auditor',
            'email' => 'auditor@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->auditor->assignRole('auditor');
    }

    protected function createRegister(): void
    {
        $this->register = Register::create([
            'code' => 'REG-001',
            'name_ar' => 'سجل الاختبار',
            'name_en' => 'Test Register',
            'fiscal_year' => 2026,
            'current_sequence' => 0,
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->financialField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'service_fee',
            'label_ar' => 'رسوم الخدمة',
            'field_type' => 'number',
            'is_required' => true,
            'is_financial' => true,
            'sort_order' => 1,
        ]);

        $this->textField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'customer_name',
            'label_ar' => 'اسم العميل',
            'field_type' => 'text',
            'is_required' => false,
            'is_financial' => false,
            'sort_order' => 2,
        ]);
    }

    protected function createOfficialFee(): void
    {
        $category = OfficialFeeCategory::create([
            'name_ar' => 'رسوم حكومية',
            'name_en' => 'Government Fees',
            'code' => 'GOV-CAT',
            'sort_order' => 1,
        ]);

        $this->officialFee = OfficialFee::create([
            'category_id' => $category->id,
            'fee_code' => 'GOV-001',
            'name_ar' => 'رسوم إصدار شهادة',
            'name_en' => 'Certificate Issuance Fee',
            'description_ar' => 'رسوم إصدار شهادة رسمية',
            'description_en' => 'Fee for official certificate issuance',
            'is_active' => true,
        ]);

        \App\Models\FeeVersion::create([
            'fee_id' => $this->officialFee->id,
            'amount' => '15.500',
            'version' => 1,
            'effective_from' => now()->subYear(),
        ]);
    }

    protected function createWorkflow(array $overrides = []): Workflow
    {
        return Workflow::create(array_merge([
            'register_id' => $this->register->id,
            'code' => 'WF-001',
            'name_ar' => 'سير العمل',
            'name_en' => 'Test Workflow',
            'description' => 'A test workflow',
            'created_by' => $this->admin->id,
            'is_active' => true,
        ], $overrides));
    }

    protected function createWorkflowVersion(Workflow $workflow, array $overrides = []): WorkflowVersion
    {
        return WorkflowVersion::create(array_merge([
            'workflow_id' => $workflow->id,
            'version' => 1,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    protected function createWorkflowStep(WorkflowVersion $version, array $overrides = []): WorkflowStep
    {
        static $sortOrder = 1;
        $step = WorkflowStep::create(array_merge([
            'workflow_version_id' => $version->id,
            'title_ar' => 'خطوة ' . $sortOrder,
            'sort_order' => $sortOrder++,
            'condition_logic' => [],
        ], $overrides));
        $sortOrder = 1;
        return $step;
    }

    protected function createWorkflowField(WorkflowVersion $version, RegisterField $registerField, array $overrides = []): WorkflowField
    {
        return WorkflowField::create(array_merge([
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerField->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => $registerField->is_required ?? false,
            'is_readonly' => false,
            'is_financial' => $registerField->is_financial ?? false,
            'is_insured' => $registerField->is_insured ?? false,
            'insurance_value' => $registerField->insurance_value,
            'field_type' => $registerField->field_type ?? 'text',
            'priority' => 0,
            'label' => $registerField->label_ar,
            'sort_order' => 1,
        ], $overrides));
    }

    protected function createWorkflowRule(WorkflowVersion $version, array $overrides = []): WorkflowRule
    {
        return WorkflowRule::create(array_merge([
            'workflow_version_id' => $version->id,
            'name' => 'Test Rule',
            'condition_logic' => ['logic' => 'AND', 'conditions' => []],
            'actions' => [],
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    protected function actingAsAdmin(): self
    {
        Sanctum::actingAs($this->admin);
        return $this;
    }

    protected function actingAsCashier(): self
    {
        Sanctum::actingAs($this->cashier);
        return $this;
    }

    protected function actingAsManager(): self
    {
        Sanctum::actingAs($this->manager);
        return $this;
    }

    protected function actingAsAuditor(): self
    {
        Sanctum::actingAs($this->auditor);
        return $this;
    }
}
