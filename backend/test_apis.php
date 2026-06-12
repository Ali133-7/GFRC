<?php
/**
 * GFRC Platform - Post-Deployment API Test Script
 * 
 * This script tests all critical API endpoints to ensure the platform is working correctly.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;

echo "🧪 GFRC Platform - Post-Deployment API Tests\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Authentication
echo "1️⃣  Testing Authentication...\n";
$admin = User::where('username', 'admin')->first();
if ($admin) {
    $token = $admin->createToken('test-token')->plainTextToken;
    echo "   ✅ Admin token created: " . substr($token, 0, 20) . "...\n";
} else {
    echo "   ❌ Admin user not found!\n";
    exit(1);
}

// Test 2: Dashboard APIs
echo "\n2️⃣  Testing Dashboard APIs...\n";

// Test GET /api/v1/dashboards
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/dashboards');
if ($response->successful()) {
    echo "   ✅ GET /dashboards - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /dashboards - Status: {$response->status()}\n";
}

// Test GET /api/v1/dashboards/available
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/dashboards/available');
if ($response->successful()) {
    echo "   ✅ GET /dashboards/available - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /dashboards/available - Status: {$response->status()}\n";
}

// Test GET /api/v1/dashboards/fund-statistics
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/dashboards/fund-statistics?period=today');
if ($response->successful()) {
    $data = $response->json();
    echo "   ✅ GET /dashboards/fund-statistics - Status: {$response->status()}\n";
    echo "      📊 Total Receipts: " . ($data['data']['statistics']['total_receipts'] ?? 0) . "\n";
    echo "      💰 Total Amount: " . ($data['data']['statistics']['total_amount'] ?? 0) . " IQD\n";
} else {
    echo "   ❌ GET /dashboards/fund-statistics - Status: {$response->status()}\n";
}

// Test GET /api/v1/dashboards/preferences
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/dashboards/preferences');
if ($response->successful()) {
    echo "   ✅ GET /dashboards/preferences - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /dashboards/preferences - Status: {$response->status()}\n";
}

// Test 3: Create Dashboard
echo "\n3️⃣  Testing Dashboard Creation...\n";
$response = Http::withToken($token)->post('http://localhost:8000/api/v1/dashboards', [
    'name_ar' => 'داشبورد اختبار',
    'name_en' => 'Test Dashboard',
    'scope' => 'user',
    'visibility' => 'private',
    'is_active' => true,
]);

if ($response->successful()) {
    $dashboard = $response->json()['data']['dashboard'];
    echo "   ✅ POST /dashboards - Status: {$response->status()}\n";
    echo "      📋 Dashboard ID: {$dashboard['id']}\n";
    echo "      📝 Name: {$dashboard['name_ar']}\n";
    
    // Test Update
    $updateResponse = Http::withToken($token)->put("http://localhost:8000/api/v1/dashboards/{$dashboard['id']}", [
        'name_ar' => 'داشبورد محدث',
    ]);
    
    if ($updateResponse->successful()) {
        echo "   ✅ PUT /dashboards/{$dashboard['id']} - Status: {$updateResponse->status()}\n";
    } else {
        echo "   ❌ PUT /dashboards/{$dashboard['id']} - Status: {$updateResponse->status()}\n";
    }
    
    // Test Delete
    $deleteResponse = Http::withToken($token)->delete("http://localhost:8000/api/v1/dashboards/{$dashboard['id']}");
    
    if ($deleteResponse->successful()) {
        echo "   ✅ DELETE /dashboards/{$dashboard['id']} - Status: {$deleteResponse->status()}\n";
    } else {
        echo "   ❌ DELETE /dashboards/{$dashboard['id']} - Status: {$deleteResponse->status()}\n";
    }
} else {
    echo "   ❌ POST /dashboards - Status: {$response->status()}\n";
    echo "      Error: " . $response->body() . "\n";
}

// Test 4: Admin APIs
echo "\n4️⃣  Testing Admin APIs...\n";
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/admin/dashboards');
if ($response->successful()) {
    $dashboards = $response->json()['data']['dashboards'];
    echo "   ✅ GET /admin/dashboards - Status: {$response->status()}\n";
    echo "      📊 Total Dashboards: " . count($dashboards) . "\n";
} else {
    echo "   ❌ GET /admin/dashboards - Status: {$response->status()}\n";
}

// Test 5: Receipts API
echo "\n5️⃣  Testing Receipts API...\n";
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/receipts');
if ($response->successful()) {
    echo "   ✅ GET /receipts - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /receipts - Status: {$response->status()}\n";
}

// Test 6: Workflows API
echo "\n6️⃣  Testing Workflows API...\n";
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/workflows');
if ($response->successful()) {
    echo "   ✅ GET /workflows - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /workflows - Status: {$response->status()}\n";
}

// Test 7: Registers API
echo "\n7️⃣  Testing Registers API...\n";
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/registers');
if ($response->successful()) {
    echo "   ✅ GET /registers - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /registers - Status: {$response->status()}\n";
}

// Test 8: Users API
echo "\n8️⃣  Testing Users API...\n";
$response = Http::withToken($token)->get('http://localhost:8000/api/v1/users');
if ($response->successful()) {
    echo "   ✅ GET /users - Status: {$response->status()}\n";
} else {
    echo "   ❌ GET /users - Status: {$response->status()}\n";
}

// Cleanup
$admin->tokens()->delete();
echo "\n🧹 Cleanup: Test tokens deleted\n";

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Post-Deployment Tests Complete!\n";
echo str_repeat("=", 60) . "\n";
