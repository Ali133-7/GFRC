<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'create-receipt',
            'view-receipt',
            'issue-receipt',
            'cancel-receipt',
            'revise-receipt',
            'print-receipt',
            'manage-registers',
            'view-registers',
            'manage-users',
            'view-users',
            'view-reports',
            'export-reports',
            'view-audit-logs',
            'manage-settings',
            'system.reset',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['id' => (string) Str::uuid()]
            );
        }

        $roles = [
            'super_admin' => $permissions,
            'manager' => [
                'view-receipt',
                'issue-receipt',
                'cancel-receipt',
                'view-registers',
                'view-users',
                'view-reports',
                'export-reports',
                'view-audit-logs',
            ],
            'cashier' => [
                'create-receipt',
                'view-receipt',
                'issue-receipt',
                'print-receipt',
                'view-registers',
            ],
            'auditor' => [
                'view-receipt',
                'view-registers',
                'view-reports',
                'view-audit-logs',
            ],
            'data_entry' => [
                'create-receipt',
                'view-receipt',
                'view-registers',
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
}
