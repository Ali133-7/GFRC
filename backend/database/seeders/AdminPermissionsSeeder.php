<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create admin user
        $admin = User::where('username', 'admin')->first();
        
        if (!$admin) {
            $this->command->warn('Admin user not found!');
            return;
        }

        // Get or create admin role with ALL permissions
        $allPermissions = [
            // Receipt permissions
            'create-receipt',
            'view-receipt',
            'issue-receipt',
            'cancel-receipt',
            'revise-receipt',
            'print-receipt',
            'verify-receipt',
            'manage-receipts',
            
            // Register permissions
            'manage-registers',
            'view-registers',
            'create-register',
            'edit-register',
            'delete-register',
            
            // User permissions
            'manage-users',
            'view-users',
            'create-user',
            'edit-user',
            'delete-user',
            'assign-roles',
            'manage-permissions',
            
            // Report permissions
            'view-reports',
            'export-reports',
            'manage-reports',
            'view-analytics',
            
            // Audit permissions
            'view-audit-logs',
            'export-audit-logs',
            'manage-audit-logs',
            
            // Settings permissions
            'manage-settings',
            'system.reset',
            'system.backup',
            'system.restore',
            'system.import',
            'system.export',
            
            // Workflow permissions
            'manage-workflows',
            'view-workflows',
            'create-workflow',
            'edit-workflow',
            'delete-workflow',
            'execute-workflow',
            
            // Template permissions
            'manage-templates',
            'view-templates',
            'create-template',
            'edit-template',
            'delete-template',
            
            // Fee permissions
            'manage-fees',
            'view-fees',
            'create-fee',
            'edit-fee',
            'delete-fee',
            
            // Dashboard permissions
            'manage-dashboards',
            'view-dashboards',
            'create-dashboard',
            'edit-dashboard',
            'delete-dashboard',
            
            // Department permissions
            'manage-departments',
            'view-departments',
            
            // Organization permissions
            'manage-organizations',
            'view-organizations',
            
            // Branch permissions
            'manage-branches',
            'view-branches',
            
            // Notification permissions
            'manage-notifications',
            'send-notifications',
            
            // API permissions
            'api-access',
            'manage-api-keys',
            
            // Super admin
            'super-admin',
            'manage-roles',
            'manage-all',
        ];

        // Create/update admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api'],
            ['id' => (string) Str::uuid()]
        );

        // Create all permissions first
        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'api'],
                ['id' => (string) Str::uuid()]
            );
        }

        // Sync all permissions to admin role
        $adminRole->syncPermissions($allPermissions);

        // Assign admin role to admin user
        $admin->syncRoles([$adminRole]);

        $this->command->info('✅ Admin user granted ALL permissions!');
        $this->command->info('  Username: admin');
        $this->command->info('  Role: admin');
        $this->command->info('  Total Permissions: ' . count($allPermissions));
    }
}
