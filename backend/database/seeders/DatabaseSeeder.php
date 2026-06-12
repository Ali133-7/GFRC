<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ⚠️ WARNING / تحذير ⚠️
 * 
 * This seeder is ONLY for initial setup.
 * DO NOT run 'migrate:fresh' in production - it will DELETE ALL DATA!
 * 
 * إذا فقدت البيانات، استخدم الأمر التالي لاستعادتها:
 * php artisan db:seed --class=KeepDataSeeder
 * 
 * This will restore:
 * - Admin user (admin/password)
 * - Test users (cashier, auditor)
 * - All permissions
 * - All roles
 * - Departments & Organizations
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            KeepDataSeeder::class,      // ← Essential data (always run)
            RolesSeeder::class,         // Additional roles
            AdminUserSeeder::class,     // Additional admin users
            SettingsSeeder::class,      // System settings
            // DemoDataSeeder::class,   // ← Comment out in production
        ]);

        $this->command->info('');
        $this->command->info('✅ Seeding completed!');
        $this->command->info('');
        $this->command->warn('⚠️  IMPORTANT: DO NOT run "migrate:fresh" - it will delete all data!');
        $this->command->warn('   If you need to fix migrations, use:');
        $this->command->warn('   - php artisan migrate:rollback');
        $this->command->warn('   - php artisan migrate:fresh --seed');
        $this->command->warn('');
    }
}
