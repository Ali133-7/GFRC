<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dashboard;
use App\Models\DashboardSection;
use App\Models\User;

class DefaultDashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating default dashboards...');

        // Get admin user
        $admin = User::where('username', 'admin')->first();
        
        if (!$admin) {
            $this->command->warn('Admin user not found!');
            return;
        }

        // Check if admin already has a dashboard
        $existingDashboard = Dashboard::where('user_id', $admin->id)
            ->where('scope', 'user')
            ->first();

        if ($existingDashboard) {
            $this->command->info('  → Admin dashboard already exists');
            return;
        }

        // Create default dashboard for admin
        $dashboard = Dashboard::create([
            'name_ar' => 'داشبوري الشخصي',
            'name_en' => 'My Dashboard',
            'description' => 'الداشبورد الشخصي الافتراضي',
            'scope' => 'user',
            'visibility' => 'private',
            'user_id' => $admin->id,
            'created_by' => $admin->id,
            'is_default' => true,
            'is_active' => true,
            'status' => 'published',
            'version' => 1,
            'layout_config' => [],
            'theme_config' => [],
        ]);

        $this->command->info('  → Created dashboard: ' . $dashboard->name_ar);

        // Create default section
        $section = DashboardSection::create([
            'dashboard_id' => $dashboard->id,
            'name_ar' => 'النظرة العامة',
            'layout_type' => 'grid',
            'sort_order' => 0,
            'is_visible' => true,
            'is_collapsible' => false,
            'padding' => 16,
            'created_by' => $admin->id,
        ]);

        $this->command->info('  → Created section: ' . $section->name_ar);

        $this->command->info('✅ Default dashboard created successfully!');
    }
}
