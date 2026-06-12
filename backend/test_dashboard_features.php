<?php
/**
 * Test new dashboard features: clone, export, import, versions
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;

$baseUrl = 'http://localhost:8000/api/v1';
$user = User::where('username', 'admin')->first();
$token = $user->createToken('test')->plainTextToken;

echo "🧪 Testing New Dashboard Features\n";
echo str_repeat("=", 60) . "\n\n";

// Create a test dashboard
echo "1. Creating test dashboard...\n";
$response = Http::withToken($token)->post("$baseUrl/dashboards", [
    'name_ar' => 'داشبورد اختبار الميزات',
    'name_en' => 'Features Test Dashboard',
    'scope' => 'user',
    'visibility' => 'private',
]);

if ($response->status() !== 201) {
    echo "❌ Failed to create dashboard: {$response->status()}\n";
    exit(1);
}

$dashboardId = $response->json()['data']['dashboard']['id'];
echo "✅ Created dashboard ID: $dashboardId\n\n";

// Add a section
echo "2. Adding section...\n";
$response = Http::withToken($token)->post("$baseUrl/dashboards/$dashboardId/sections", [
    'name_ar' => 'قسم الاختبار',
    'layout_type' => 'grid',
    'sort_order' => 1,
    'is_visible' => true,
]);

if ($response->status() !== 201) {
    echo "❌ Failed to add section: {$response->status()}\n";
    exit(1);
}

$sectionId = $response->json()['data']['section']['id'];
echo "✅ Added section ID: $sectionId\n\n";

// Add a widget
echo "3. Adding widget...\n";
$response = Http::withToken($token)->post("$baseUrl/dashboards/$dashboardId/sections/$sectionId/widgets", [
    'widget_type' => 'kpi_card',
    'name_ar' => 'مؤشر اختبار',
    'data_source' => 'receipts_total',
    'grid_x' => 0,
    'grid_y' => 0,
    'grid_width' => 4,
    'grid_height' => 2,
    'sort_order' => 1,
    'is_visible' => true,
]);

if ($response->status() !== 201) {
    echo "❌ Failed to add widget: {$response->status()}\n";
    exit(1);
}

echo "✅ Added widget\n\n";

// Test Clone
echo "4. Testing Clone...\n";
$response = Http::withToken($token)->post("$baseUrl/dashboards/$dashboardId/clone", [
    'name_ar' => 'نسخة من داشبورد الاختبار',
    'name_en' => 'Clone of Test Dashboard',
]);

if ($response->status() !== 201) {
    echo "❌ Clone failed: {$response->status()}\n";
    echo $response->body() . "\n";
    exit(1);
}

$clonedId = $response->json()['data']['dashboard']['id'];
echo "✅ Cloned to dashboard ID: $clonedId\n\n";

// Test Export
echo "5. Testing Export...\n";
$response = Http::withToken($token)->get("$baseUrl/dashboards/$dashboardId/export");

if ($response->status() !== 200) {
    echo "❌ Export failed: {$response->status()}\n";
    exit(1);
}

$exportData = $response->json()['data']['export'];
echo "✅ Exported dashboard (version: {$exportData['version']})\n";
echo "   Sections: " . count($exportData['dashboard']['sections']) . "\n";
echo "   Widgets: " . count($exportData['dashboard']['sections'][0]['widgets'] ?? []) . "\n\n";

// Test Import
echo "6. Testing Import...\n";
$exportData['dashboard']['name_ar'] = 'داشبورد مستورد';
$exportData['dashboard']['name_en'] = 'Imported Dashboard';

$response = Http::withToken($token)->post("$baseUrl/dashboards/import", [
    'export' => $exportData,
]);

if ($response->status() !== 201) {
    echo "❌ Import failed: {$response->status()}\n";
    echo $response->body() . "\n";
    exit(1);
}

$importedId = $response->json()['data']['dashboard']['id'];
echo "✅ Imported to dashboard ID: $importedId\n\n";

// Test Versions
echo "7. Testing Versions...\n";
$response = Http::withToken($token)->get("$baseUrl/dashboards/$dashboardId/versions");

if ($response->status() !== 200) {
    echo "❌ Versions failed: {$response->status()}\n";
    exit(1);
}

$versions = $response->json()['data']['versions'];
echo "✅ Retrieved " . count($versions) . " version(s)\n\n";

// Cleanup
echo "8. Cleanup...\n";
Http::withToken($token)->delete("$baseUrl/dashboards/$dashboardId");
Http::withToken($token)->delete("$baseUrl/dashboards/$clonedId");
Http::withToken($token)->delete("$baseUrl/dashboards/$importedId");
echo "✅ Cleaned up test dashboards\n\n";

// Revoke token
$user->tokens()->where('name', 'test')->delete();

echo str_repeat("=", 60) . "\n";
echo "✅ All new features working correctly!\n";
echo str_repeat("=", 60) . "\n";
