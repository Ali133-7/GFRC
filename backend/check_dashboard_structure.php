<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Dashboard;
use App\Models\User;
use App\Services\DashboardService;

$user = User::where('username', 'admin')->first();
if (!$user) {
    echo "Admin user not found\n";
    exit(1);
}

// Create a test dashboard
$dashboard = Dashboard::create([
    'name_ar' => 'داشبورد اختبار البنية',
    'name_en' => 'Structure Test Dashboard',
    'description' => 'Test dashboard for structure check',
    'scope' => 'user',
    'visibility' => 'private',
    'user_id' => $user->id,
    'is_active' => true,
    'status' => 'published',
    'version' => 1,
    'created_by' => $user->id,
]);

echo "Created Dashboard ID: {$dashboard->id}\n\n";

echo "Dashboard Model:\n";
echo json_encode($dashboard->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$service = app(DashboardService::class);
$dashboardData = $service->getDashboardWithContent($dashboard, $user);

echo "Dashboard With Content:\n";
echo json_encode($dashboardData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Clean up
$dashboard->delete();
echo "\nCleaned up test dashboard\n";
