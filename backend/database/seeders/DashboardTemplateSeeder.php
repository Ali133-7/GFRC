<?php

namespace Database\Seeders;

use App\Models\DashboardTemplate;
use App\Models\Dashboard;
use App\Models\DashboardSection;
use App\Models\DashboardWidget;
use Illuminate\Database\Seeder;

class DashboardTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cashier Dashboard Template
        $cashierTemplate = DashboardTemplate::create([
            'name_ar' => 'داشبورد الصراف',
            'name_en' => 'Cashier Dashboard',
            'description' => 'داشبورد مخصص للصرافين لعرض الوصولات والمقبوضات',
            'category' => 'financial',
            'role_type' => 'cashier',
            'is_active' => true,
            'is_system' => true,
            'layout_config' => ['columns' => 12, 'spacing' => 16],
            'default_widgets' => [
                ['type' => 'kpi_card', 'title' => 'مقبوضات اليوم'],
                ['type' => 'kpi_card', 'title' => 'عدد الوصولات'],
                ['type' => 'table', 'title' => 'آخر الوصولات'],
            ],
        ]);

        // Auditor Dashboard Template
        $auditorTemplate = DashboardTemplate::create([
            'name_ar' => 'داشبورد المدقق',
            'name_en' => 'Auditor Dashboard',
            'description' => 'داشبورد مخصص للمدققين لعرض التدقيقات والملاحظات',
            'category' => 'audit',
            'role_type' => 'auditor',
            'is_active' => true,
            'is_system' => true,
            'layout_config' => ['columns' => 12, 'spacing' => 16],
            'default_widgets' => [
                ['type' => 'kpi_card', 'title' => 'عمليات التدقيق'],
                ['type' => 'list', 'title' => 'الملاحظات المعلقة'],
                ['type' => 'table', 'title' => 'سجل التدقيقات'],
            ],
        ]);

        // Manager Dashboard Template
        $managerTemplate = DashboardTemplate::create([
            'name_ar' => 'داشبورد المدير',
            'name_en' => 'Manager Dashboard',
            'description' => 'داشبورد مخصص لمديري الأقسام',
            'category' => 'operations',
            'role_type' => 'manager',
            'is_active' => true,
            'is_system' => true,
            'layout_config' => ['columns' => 12, 'spacing' => 16],
            'default_widgets' => [
                ['type' => 'kpi_card', 'title' => 'أداء القسم'],
                ['type' => 'chart', 'title' => 'الإحصائيات الشهرية'],
                ['type' => 'table', 'title' => 'المعاملات المعلقة'],
            ],
        ]);

        // Executive Dashboard Template
        $executiveTemplate = DashboardTemplate::create([
            'name_ar' => 'داشبورد تنفيذي',
            'name_en' => 'Executive Dashboard',
            'description' => 'داشبورد تنفيذي للمدراء العامين والوزراء',
            'category' => 'executive',
            'role_type' => 'director',
            'is_active' => true,
            'is_system' => true,
            'layout_config' => ['columns' => 12, 'spacing' => 16],
            'default_widgets' => [
                ['type' => 'kpi_card', 'title' => 'إجمالي المقبوضات'],
                ['type' => 'kpi_card', 'title' => 'عدد المعاملات'],
                ['type' => 'chart', 'title' => 'الأداء الشهري'],
                ['type' => 'chart', 'title' => 'المقارنة السنوية'],
            ],
        ]);

        // Create actual dashboards from templates
        $this->createDashboardFromTemplate($cashierTemplate->id, 'cashier');
        $this->createDashboardFromTemplate($auditorTemplate->id, 'auditor');
        $this->createDashboardFromTemplate($managerTemplate->id, 'manager');
        $this->createDashboardFromTemplate($executiveTemplate->id, 'director');

        $this->command->info('Dashboard templates seeded successfully!');
    }

    /**
     * Create a dashboard from template
     */
    protected function createDashboardFromTemplate(int $templateId, string $roleName): void
    {
        $template = DashboardTemplate::find($templateId);
        if (!$template) {
            $this->command->warn("Template {$templateId} not found");
            return;
        }
        
        // Get or create role
        $role = \App\Models\Role::where('name', 'like', "%{$roleName}%")->first();
        
        if (!$role) {
            // Create system-level dashboard instead
            $dashboard = Dashboard::create([
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'description' => $template->description,
                'scope' => 'system',
                'template_id' => $template->id,
                'visibility' => 'public',
                'is_default' => false,
                'is_active' => true,
                'status' => 'published',
                'layout_config' => $template->layout_config,
                'created_by' => null,
                'organization_id' => null,
                'department_id' => null,
                'user_id' => null,
                'role_id' => null,
            ]);
            $this->command->info("Created system dashboard: {$template->name_ar}");
        } else {
            $dashboard = Dashboard::create([
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'description' => $template->description,
                'scope' => 'role',
                'role_id' => $role->id,
                'template_id' => $template->id,
                'visibility' => 'role',
                'is_default' => true,
                'is_active' => true,
                'status' => 'published',
                'layout_config' => $template->layout_config,
                'created_by' => null,
                'organization_id' => null,
                'department_id' => null,
                'user_id' => null,
            ]);
            $this->command->info("Created dashboard for role: {$role->name}");
        }

        // Create default section
        $section = DashboardSection::create([
            'dashboard_id' => $dashboard->id,
            'name_ar' => 'القسم الرئيسي',
            'name_en' => 'Main Section',
            'layout_type' => 'grid',
            'layout_config' => ['columns' => 12],
            'sort_order' => 1,
            'is_visible' => true,
            'created_by' => null,
        ]);

        // Create default widgets based on template
        $widgets = $template->default_widgets ?? [];
        $sortOrder = 1;

        foreach ($widgets as $widgetConfig) {
            DashboardWidget::create([
                'section_id' => $section->id,
                'name_ar' => $widgetConfig['title'] ?? 'Widget',
                'widget_type' => $widgetConfig['type'] ?? 'kpi_card',
                'grid_width' => 4,
                'grid_height' => 4,
                'sort_order' => $sortOrder++,
                'data_config' => [],
                'display_config' => [
                    'title' => $widgetConfig['title'],
                    'color' => 'blue',
                ],
                'is_visible' => true,
                'is_editable' => true,
                'is_removable' => true,
                'created_by' => null,
            ]);
        }

        if ($role) {
            $this->command->info("Created dashboard for role: {$role->name}");
        }
    }
}
