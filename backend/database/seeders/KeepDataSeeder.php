<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class KeepDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder preserves essential data that should never be lost.
     * Run this AFTER migrate:fresh to restore critical data.
     */
    public function run(): void
    {
        $this->command->info('🔄 Restoring essential data...');

        // 1. Create Organizations
        $this->createOrganizations();

        // 2. Create Departments
        $this->createDepartments();

        // 3. Create Permissions
        $this->createPermissions();

        // 4. Create Roles
        $this->createRoles();

        // 5. Create Admin User
        $this->createAdminUser();

        // 6. Create Test Users
        $this->createTestUsers();

        $this->command->info('✅ Essential data restored successfully!');
        $this->command->info('');
        $this->command->info('📋 Default Credentials:');
        $this->command->info('   Admin:     admin / password');
        $this->command->info('   Cashier:   cashier / password');
        $this->command->info('   Auditor:   auditor / password');
        $this->command->info('');
    }

    protected function createOrganizations(): void
    {
        $this->command->info('  → Creating organizations...');
        
        // Organization model may not exist - skip if not available
        if (!class_exists('App\Models\Organization')) {
            $this->command->warn('    Organization model not found, skipping...');
            return;
        }
        
        Organization::firstOrCreate(
            ['code' => 'HQ'],
            [
                'id' => (string) Str::uuid(),
                'name_ar' => 'المقر الرئيسي',
                'name_en' => 'Headquarters',
                'is_active' => true,
            ]
        );
    }

    protected function createDepartments(): void
    {
        $this->command->info('  → Creating departments...');
        
        // Department model may not exist
        if (!class_exists('App\Models\Department')) {
            $this->command->warn('    Department model not found, skipping...');
            return;
        }
        
        $departments = [
            ['code' => 'FIN', 'name_ar' => 'المالية', 'name_en' => 'Finance'],
            ['code' => 'HR', 'name_ar' => 'الموارد البشرية', 'name_en' => 'Human Resources'],
            ['code' => 'IT', 'name_ar' => 'تقنية المعلومات', 'name_en' => 'Information Technology'],
            ['code' => 'OPS', 'name_ar' => 'العمليات', 'name_en' => 'Operations'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(
                ['code' => $dept['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name_ar' => $dept['name_ar'],
                    'name_en' => $dept['name_en'],
                    'is_active' => true,
                ]
            );
        }
    }

    protected function createPermissions(): void
    {
        $this->command->info('  → Creating permissions...');
        
        $permissions = [
            // Receipt
            'create-receipt', 'view-receipt', 'issue-receipt',
            'cancel-receipt', 'revise-receipt', 'print-receipt',
            'verify-receipt', 'manage-receipts',
            
            // Register
            'manage-registers', 'view-registers', 'create-register',
            'edit-register', 'delete-register',
            
            // User
            'manage-users', 'view-users', 'create-user', 'edit-user',
            'delete-user', 'assign-roles', 'manage-permissions',
            
            // Report
            'view-reports', 'export-reports', 'manage-reports', 'view-analytics',
            
            // Audit
            'view-audit-logs', 'export-audit-logs', 'manage-audit-logs',
            
            // Settings
            'manage-settings', 'system.reset', 'system.backup',
            'system.restore', 'system.import', 'system.export',
            
            // Workflow
            'manage-workflows', 'view-workflows', 'create-workflow',
            'edit-workflow', 'delete-workflow', 'execute-workflow',
            
            // Template
            'manage-templates', 'view-templates', 'create-template',
            'edit-template', 'delete-template',
            
            // Fee
            'manage-fees', 'view-fees', 'create-fee', 'edit-fee', 'delete-fee',
            
            // Dashboard
            'manage-dashboards', 'view-dashboards', 'create-dashboard',
            'edit-dashboard', 'delete-dashboard',
            
            // Super admin
            'super-admin', 'manage-roles', 'manage-all',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['id' => (string) Str::uuid()]
            );
        }
    }

    protected function createRoles(): void
    {
        $this->command->info('  → Creating roles...');
        
        $roles = [
            'admin' => Permission::all()->pluck('name')->toArray(),
            'manager' => [
                'view-receipt', 'issue-receipt', 'cancel-receipt',
                'view-registers', 'view-users', 'view-reports',
                'export-reports', 'view-audit-logs', 'manage-workflows',
            ],
            'cashier' => [
                'create-receipt', 'view-receipt', 'issue-receipt',
                'print-receipt', 'view-registers',
            ],
            'auditor' => [
                'view-receipt', 'view-registers', 'view-reports',
                'view-audit-logs', 'export-reports',
            ],
            'data_entry' => [
                'create-receipt', 'view-receipt', 'view-registers',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'api'],
                ['id' => (string) Str::uuid()]
            );
            $role->syncPermissions($rolePermissions);
        }
    }

    protected function createAdminUser(): void
    {
        $this->command->info('  → Creating admin user...');
        
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Administrator',
                'email' => 'admin@gov.krd',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->syncRoles([$adminRole]);
        }
    }

    protected function createTestUsers(): void
    {
        $this->command->info('  → Creating test users...');
        
        $users = [
            [
                'username' => 'cashier',
                'name' => 'Test Cashier',
                'email' => 'cashier@gov.krd',
                'role' => 'cashier',
            ],
            [
                'username' => 'auditor',
                'name' => 'Test Auditor',
                'email' => 'auditor@gov.krd',
                'role' => 'auditor',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['username' => $userData['username']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            $role = Role::where('name', $userData['role'])->first();
            if ($role) {
                $user->syncRoles([$role]);
            }
        }
    }
}
