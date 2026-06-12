<?php
/**
 * Phase C: Personal Workspace Validation Test
 * Tests dashboard inheritance, permissions, and login restoration
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Dashboard;
use App\Models\Department;
use App\Models\Role;

$baseUrl = 'http://localhost:8000/api/v1';

echo "🧪 Phase C: Personal Workspace Validation\n";
echo str_repeat("=", 70) . "\n\n";

$tests = [];
$passed = 0;
$failed = 0;

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

// Get admin user
$admin = User::where('username', 'admin')->first();
if (!$admin) {
    die("❌ Admin user not found\n");
}

$adminToken = $admin->createToken('phase-c-test')->plainTextToken;

// Test 1: Create System Dashboard
test("Create System Dashboard", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد النظام',
        'name_en' => 'System Dashboard',
        'scope' => 'system',
        'visibility' => 'public',
        'is_default' => true,
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 2: Create Department Dashboard
test("Create Department Dashboard", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد القسم',
        'name_en' => 'Department Dashboard',
        'scope' => 'department',
        'visibility' => 'department',
        'is_default' => true,
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 3: Create Role Dashboard
test("Create Role Dashboard", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد الدور',
        'name_en' => 'Role Dashboard',
        'scope' => 'role',
        'visibility' => 'role',
        'is_default' => true,
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 4: Create User Dashboard
test("Create User Dashboard", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد المستخدم',
        'name_en' => 'User Dashboard',
        'scope' => 'user',
        'visibility' => 'private',
        'is_default' => true,
    ]);
    
    if ($response->status() !== 201) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 5: Test Dashboard Inheritance - User should get user dashboard
test("Dashboard Inheritance - User gets user dashboard", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards");
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    $dashboard = $data['data']['dashboard'] ?? null;
    
    if (!$dashboard) {
        return "No dashboard returned";
    }
    
    // User should get their own dashboard (highest priority)
    if ($dashboard['scope'] !== 'user') {
        return "Expected user scope, got: {$dashboard['scope']}";
    }
    
    return true;
});

// Test 6: Test Available Dashboards
test("Get Available Dashboards", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards/available");
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    $dashboards = $data['data']['dashboards'] ?? [];
    
    if (count($dashboards) < 4) {
        return "Expected at least 4 dashboards, got: " . count($dashboards);
    }
    
    return true;
});

// Test 7: Test Set Default Dashboard
test("Set Default Dashboard", function() use ($adminToken, $baseUrl) {
    // Get available dashboards
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards/available");
    $dashboards = $response->json()['data']['dashboards'] ?? [];
    
    if (empty($dashboards)) {
        return "No dashboards available";
    }
    
    // Set first dashboard as default
    $dashboardId = $dashboards[0]['id'];
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards/set-default", [
        'dashboard_id' => $dashboardId,
    ]);
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    return true;
});

// Test 8: Test Dashboard Permissions - Public visibility
test("Dashboard Permissions - Public visibility", function() use ($adminToken, $baseUrl) {
    // Create a public dashboard
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد عام',
        'name_en' => 'Public Dashboard',
        'scope' => 'system',
        'visibility' => 'public',
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create public dashboard";
    }
    
    $dashboardId = $response->json()['data']['dashboard']['id'];
    
    // Try to access it
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards/$dashboardId");
    
    if ($response->status() !== 200) {
        return "Cannot access public dashboard";
    }
    
    return true;
});

// Test 9: Test Dashboard Permissions - Private visibility
test("Dashboard Permissions - Private visibility", function() use ($adminToken, $baseUrl) {
    // Create a private dashboard
    $response = Http::withToken($adminToken)->post("$baseUrl/dashboards", [
        'name_ar' => 'داشبورد خاص',
        'name_en' => 'Private Dashboard',
        'scope' => 'user',
        'visibility' => 'private',
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create private dashboard";
    }
    
    $dashboardId = $response->json()['data']['dashboard']['id'];
    
    // Owner should be able to access it
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards/$dashboardId");
    
    if ($response->status() !== 200) {
        return "Owner cannot access private dashboard";
    }
    
    return true;
});

// Test 10: Test Login Restoration - User preferences persist
test("Login Restoration - User preferences persist", function() use ($adminToken, $baseUrl) {
    // Update preferences
    $response = Http::withToken($adminToken)->put("$baseUrl/dashboards/preferences", [
        'theme' => 'dark',
        'font_size' => 'large',
    ]);
    
    if ($response->status() !== 200) {
        return "Failed to update preferences";
    }
    
    // Get preferences again (simulating login restoration)
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards/preferences");
    
    if ($response->status() !== 200) {
        return "Failed to get preferences";
    }
    
    $preferences = $response->json()['data']['preferences'] ?? null;
    
    if (!$preferences) {
        return "No preferences returned";
    }
    
    // Check if preferences persisted
    if (($preferences['theme'] ?? null) !== 'dark') {
        return "Theme preference not persisted";
    }
    
    return true;
});

// Test 11: Test Default Dashboard Resolution
test("Default Dashboard Resolution", function() use ($adminToken, $baseUrl) {
    // Get current dashboard (should resolve to default)
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards");
    
    if ($response->status() !== 200) {
        return "Status: {$response->status()}";
    }
    
    $data = $response->json();
    $dashboard = $data['data']['dashboard'] ?? null;
    
    if (!$dashboard) {
        return "No dashboard resolved";
    }
    
    // Should have a valid dashboard
    if (!isset($dashboard['id'])) {
        return "Dashboard has no ID";
    }
    
    return true;
});

// Test 12: Test Dashboard Override - User dashboard overrides system
test("Dashboard Override - User overrides system", function() use ($adminToken, $baseUrl) {
    // Get current dashboard
    $response = Http::withToken($adminToken)->get("$baseUrl/dashboards");
    $dashboard = $response->json()['data']['dashboard'] ?? null;
    
    if (!$dashboard) {
        return "No dashboard returned";
    }
    
    // User dashboard should have highest priority
    if ($dashboard['scope'] !== 'user') {
        return "User dashboard should override system dashboard";
    }
    
    return true;
});

// Cleanup
$admin->tokens()->where('name', 'phase-c-test')->delete();

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 PHASE C VALIDATION SUMMARY\n";
echo str_repeat("=", 70) . "\n";
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
