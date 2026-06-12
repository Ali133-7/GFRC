<?php
/**
 * GFRC Dashboard Zero-Gap Validation Test
 * Tests complete dashboard lifecycle: Create → Read → Update → Delete → Verify
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Dashboard;
use App\Models\DashboardSection;
use App\Models\DashboardWidget;
use Illuminate\Support\Facades\Http;

echo "🧪 GFRC Dashboard Zero-Gap Validation\n";
echo str_repeat("=", 80) . "\n\n";

$tests = [];
$passed = 0;
$failed = 0;

// Get admin user and create token
$admin = User::where('username', 'admin')->first();
if (!$admin) {
    die("❌ Admin user not found\n");
}

$token = $admin->createToken('validation-test')->plainTextToken;
$baseUrl = 'http://localhost:8000/api/v1';

function test($name, $callback) {
    global $tests, $passed, $failed;
    echo "Testing: $name ... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASS\n";
            $tests[] = ['name' => $name, 'status' => 'PASS'];
            $passed++;
            return true;
        } else {
            echo "❌ FAIL: $result\n";
            $tests[] = ['name' => $name, 'status' => 'FAIL', 'error' => $result];
            $failed++;
            return false;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $tests[] = ['name' => $name, 'status' => 'ERROR', 'error' => $e->getMessage()];
        $failed++;
        return false;
    }
}

// Test 1: List Dashboards
test("GET /dashboards - List all dashboards", function() use ($token, $baseUrl) {
    $response = Http::withToken($token)->get("$baseUrl/dashboards");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    $data = $response->json();
    if (!isset($data['data']['dashboard'])) {
        return "Missing dashboard data";
    }
    return true;
});

// Test 2: Get Available Dashboards
test("GET /dashboards/available - List available dashboards", function() use ($token, $baseUrl) {
    $response = Http::withToken($token)->get("$baseUrl/dashboards/available");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    $data = $response->json();
    if (!isset($data['data']['dashboards'])) {
        return "Missing dashboards array";
    }
    return true;
});

// Test 3: Create Dashboard
$createdDashboardId = null;
test("POST /dashboards - Create new dashboard", function() use ($token, $baseUrl, &$createdDashboardId) {
    $response = Http::withToken($token)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد اختبار Zero-Gap',
        'name_en' => 'Zero-Gap Test Dashboard',
        'description' => 'Dashboard created for validation testing',
        'scope' => 'user',
        'visibility' => 'private',
        'is_active' => true,
        'layout_config' => ['columns' => 12],
        'theme_config' => ['primary_color' => '#3b82f6'],
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()} - " . $response->body();
    }
    
    $data = $response->json();
    if (!isset($data['data']['dashboard']['id'])) {
        return "Missing dashboard ID in response";
    }
    
    $createdDashboardId = $data['data']['dashboard']['id'];
    echo "(ID: $createdDashboardId) ";
    return true;
});

// Test 4: Read Created Dashboard
test("GET /dashboards/{id} - Read created dashboard", function() use ($token, $baseUrl, $createdDashboardId) {
    if (!$createdDashboardId) {
        return "No dashboard ID from previous test";
    }
    
    $response = Http::withToken($token)->get("$baseUrl/dashboards/$createdDashboardId");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    if (!isset($data['data']['dashboard'])) {
        return "Missing dashboard data";
    }
    
    if ($data['data']['dashboard']['name_ar'] !== 'داشبورد اختبار Zero-Gap') {
        return "Dashboard name mismatch";
    }
    
    return true;
});

// Test 5: Update Dashboard
test("PUT /dashboards/{id} - Update dashboard", function() use ($token, $baseUrl, $createdDashboardId) {
    if (!$createdDashboardId) {
        return "No dashboard ID from previous test";
    }
    
    $response = Http::withToken($token)->put("$baseUrl/dashboards/$createdDashboardId", [
        'name_ar' => 'داشبورد محدث Zero-Gap',
        'description' => 'Updated description',
    ]);
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    // Verify update persisted
    $verifyResponse = Http::withToken($token)->get("$baseUrl/dashboards/$createdDashboardId");
    $data = $verifyResponse->json();
    
    if ($data['data']['dashboard']['name_ar'] !== 'داشبورد محدث Zero-Gap') {
        return "Update did not persist";
    }
    
    return true;
});

// Test 6: Add Section to Dashboard
$createdSectionId = null;
test("POST /dashboards/{id}/sections - Add section", function() use ($token, $baseUrl, $createdDashboardId, &$createdSectionId) {
    if (!$createdDashboardId) {
        return "No dashboard ID";
    }
    
    $response = Http::withToken($token)->post("$baseUrl/dashboards/$createdDashboardId/sections", [
        'name_ar' => 'قسم الاختبار',
        'name_en' => 'Test Section',
        'sort_order' => 1,
        'is_visible' => true,
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()} - " . $response->body();
    }
    
    $data = $response->json();
    if (!isset($data['data']['section']['id'])) {
        return "Missing section ID";
    }
    
    $createdSectionId = $data['data']['section']['id'];
    return true;
});

// Test 7: Add Widget to Section
$createdWidgetId = null;
test("POST /dashboards/{id}/sections/{sectionId}/widgets - Add widget", function() use ($token, $baseUrl, $createdDashboardId, $createdSectionId, &$createdWidgetId) {
    if (!$createdDashboardId || !$createdSectionId) {
        return "Missing dashboard or section ID";
    }
    
    $response = Http::withToken($token)->post("$baseUrl/dashboards/$createdDashboardId/sections/$createdSectionId/widgets", [
        'name_ar' => 'مؤشر الاختبار',
        'name_en' => 'Test KPI',
        'widget_type' => 'kpi_card',
        'grid_x' => 0,
        'grid_y' => 0,
        'grid_width' => 4,
        'grid_height' => 2,
        'data_source' => 'receipts_total',
        'data_config' => ['metric' => 'total'],
        'display_config' => ['color' => 'blue'],
        'refresh_interval' => 60,
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()} - " . $response->body();
    }
    
    $data = $response->json();
    if (!isset($data['data']['widget']['id'])) {
        return "Missing widget ID";
    }
    
    $createdWidgetId = $data['data']['widget']['id'];
    return true;
});

// Test 8: Get Widget Data
test("GET /dashboards/{id}/widgets/{widgetId}/data - Get widget data", function() use ($token, $baseUrl, $createdDashboardId, $createdWidgetId) {
    if (!$createdDashboardId || !$createdWidgetId) {
        return "Missing dashboard or widget ID";
    }
    
    $response = Http::withToken($token)->get("$baseUrl/dashboards/$createdDashboardId/widgets/$createdWidgetId/data");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    if (!isset($data['data'])) {
        return "Missing widget data";
    }
    
    return true;
});

// Test 9: Update Widget Positions
test("PUT /dashboards/{id}/widgets/positions - Update widget positions", function() use ($token, $baseUrl, $createdDashboardId, $createdWidgetId) {
    if (!$createdDashboardId || !$createdWidgetId) {
        return "Missing dashboard or widget ID";
    }
    
    $response = Http::withToken($token)->put("$baseUrl/dashboards/$createdDashboardId/widgets/positions", [
        'widgets' => [
            [
                'id' => $createdWidgetId,
                'grid_x' => 4,
                'grid_y' => 0,
                'grid_width' => 6,
                'grid_height' => 3,
            ]
        ]
    ]);
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 10: Get Fund Statistics
test("GET /dashboards/fund-statistics - Get fund statistics", function() use ($token, $baseUrl) {
    $response = Http::withToken($token)->get("$baseUrl/dashboards/fund-statistics?period=today");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    if (!isset($data['data']['statistics'])) {
        return "Missing statistics data";
    }
    
    return true;
});

// Test 11: Get Preferences
test("GET /dashboards/preferences - Get user preferences", function() use ($token, $baseUrl) {
    $response = Http::withToken($token)->get("$baseUrl/dashboards/preferences");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 12: Update Preferences
test("PUT /dashboards/preferences - Update preferences", function() use ($token, $baseUrl) {
    $response = Http::withToken($token)->put("$baseUrl/dashboards/preferences", [
        'theme' => 'dark',
        'font_size' => 'large',
        'auto_refresh_widgets' => true,
    ]);
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 13: Admin List Dashboards
test("GET /admin/dashboards - Admin list all dashboards", function() use ($token, $baseUrl) {
    $response = Http::withToken($token)->get("$baseUrl/admin/dashboards");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    if (!isset($data['data']['dashboards'])) {
        return "Missing dashboards array";
    }
    
    return true;
});

// Test 14: Persistence Check - Reload and Verify
test("PERSISTENCE - Reload dashboard and verify all data", function() use ($token, $baseUrl, $createdDashboardId, $createdSectionId, $createdWidgetId) {
    if (!$createdDashboardId) {
        return "No dashboard ID";
    }
    
    // Clear any caching
    \Illuminate\Support\Facades\Cache::flush();
    
    // Reload dashboard
    $response = Http::withToken($token)->get("$baseUrl/dashboards/$createdDashboardId");
    if ($response->status() !== 200) {
        return "Failed to reload dashboard";
    }
    
    $data = $response->json();
    $dashboard = $data['data']['dashboard'];
    
    // Verify dashboard data
    if ($dashboard['name_ar'] !== 'داشبورد محدث Zero-Gap') {
        return "Dashboard name not persisted";
    }
    
    // Verify sections exist
    if (empty($dashboard['sections'])) {
        return "Sections not loaded";
    }
    
    // Verify widgets exist in sections
    $hasWidgets = false;
    foreach ($dashboard['sections'] as $section) {
        if (!empty($section['widgets'])) {
            $hasWidgets = true;
            break;
        }
    }
    
    if (!$hasWidgets) {
        return "Widgets not loaded in sections";
    }
    
    return true;
});

// Test 15: Delete Dashboard
test("DELETE /dashboards/{id} - Delete dashboard", function() use ($token, $baseUrl, $createdDashboardId) {
    if (!$createdDashboardId) {
        return "No dashboard ID";
    }
    
    $response = Http::withToken($token)->delete("$baseUrl/dashboards/$createdDashboardId");
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    // Verify deletion
    $verifyResponse = Http::withToken($token)->get("$baseUrl/dashboards/$createdDashboardId");
    if ($verifyResponse->status() !== 404) {
        return "Dashboard still exists after deletion";
    }
    
    return true;
});

// Cleanup
$admin->tokens()->where('name', 'validation-test')->delete();

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 VALIDATION SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Success Rate: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";

if ($failed > 0) {
    echo "\n❌ FAILED TESTS:\n";
    foreach ($tests as $test) {
        if ($test['status'] !== 'PASS') {
            echo "  - {$test['name']}: {$test['error']}\n";
        }
    }
}

echo "\n";
exit($failed > 0 ? 1 : 0);
