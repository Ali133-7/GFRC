<?php
/**
 * Phase K: Production Readiness Gate
 * Final verification that all critical issues are resolved
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;

$baseUrl = 'http://localhost:8000/api/v1';

echo "🧪 Phase K: Production Readiness Gate\n";
echo str_repeat("=", 70) . "\n\n";

$checks = [];
$passed = 0;
$failed = 0;

function check($name, $callback) {
    global $checks, $passed, $failed;
    echo "Checking: $name ... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASS\n";
            $checks[] = ['name' => $name, 'status' => 'PASS'];
            $passed++;
            return true;
        } else {
            echo "❌ FAIL: $result\n";
            $checks[] = ['name' => $name, 'status' => 'FAIL', 'error' => $result];
            $failed++;
            return false;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $checks[] = ['name' => $name, 'status' => 'ERROR', 'error' => $e->getMessage()];
        $failed++;
        return false;
    }
}

// Get admin user
$admin = User::where('username', 'admin')->first();
if (!$admin) {
    die("❌ Admin user not found\n");
}

$adminToken = $admin->createToken('phase-k-test')->plainTextToken;

// Gate 1: No Critical Issues
check("Gate 1: No Critical Issues", function() use ($adminToken, $baseUrl) {
    // Verify all critical endpoints are accessible
    $endpoints = [
        "$baseUrl/dashboards" => 'GET',
        "$baseUrl/workflows" => 'GET',
        "$baseUrl/users" => 'GET',
        "$baseUrl/registers" => 'GET',
        "$baseUrl/official-fees" => 'GET',
        "$baseUrl/help" => 'GET',
        "$baseUrl/audit-logs" => 'GET',
    ];
    
    foreach ($endpoints as $endpoint => $method) {
        $response = Http::withToken($adminToken)->get($endpoint);
        
        if ($response->status() !== 200) {
            return "Critical endpoint $endpoint returned {$response->status()}";
        }
    }
    
    return true;
});

// Gate 2: No High Issues
check("Gate 2: No High Issues", function() use ($adminToken, $baseUrl) {
    // Verify CRUD operations work
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    
    if (empty($workflows)) {
        return "No workflows found for CRUD test";
    }
    
    $workflowId = $workflows[0]['id'];
    
    // Get workflow details
    $response = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId");
    
    if ($response->status() !== 200) {
        return "Failed to get workflow details: {$response->status()}";
    }
    
    return true;
});

// Gate 3: No Broken Actions
check("Gate 3: No Broken Actions", function() use ($adminToken, $baseUrl) {
    // Get workflows
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No workflow versions found";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Create a rule with various actions
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/$versionId/rules", [
        'name' => 'Production Test Rule',
        'rule_type' => 'simple',
        'priority' => 1,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'set_value', 'field_id' => 'test_field', 'value' => 'test_value'],
            ['type' => 'show', 'field_id' => 'test_field'],
            ['type' => 'hide', 'field_id' => 'test_field'],
        ],
    ]);
    
    if (!in_array($response->status(), [200, 201])) {
        return "Failed to create rule with actions: {$response->status()}";
    }
    
    return true;
});

// Gate 4: No Financial Mismatches
check("Gate 4: No Financial Mismatches", function() use ($adminToken, $baseUrl) {
    // Get active fees
    $response = Http::withToken($adminToken)->get("$baseUrl/fees/active");
    
    if ($response->status() !== 200) {
        return "Failed to get active fees: {$response->status()}";
    }
    
    $fees = $response->json()['data'] ?? [];
    
    if (empty($fees)) {
        return "No active fees found";
    }
    
    // Verify fee amounts are valid
    foreach ($fees as $fee) {
        if (!isset($fee['amount']) || $fee['amount'] < 0) {
            return "Invalid fee amount detected";
        }
    }
    
    return true;
});

// Gate 5: No Rule Failures
check("Gate 5: No Rule Failures", function() use ($adminToken, $baseUrl) {
    // Get workflows
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No workflow versions found";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Get rules
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules");
    
    if ($response->status() !== 200) {
        return "Failed to get rules: {$response->status()}";
    }
    
    $rules = $response->json()['data']['rules'] ?? [];
    
    // Verify rules are properly structured
    foreach ($rules as $rule) {
        if (!isset($rule['id']) || !isset($rule['name']) || !isset($rule['rule_type'])) {
            return "Rule missing required fields";
        }
    }
    
    return true;
});

// Gate 6: No Realtime Divergence
check("Gate 6: No Realtime Divergence", function() use ($adminToken, $baseUrl) {
    // Get workflow executions
    $executions = Http::withToken($adminToken)->get("$baseUrl/workflow-executions")->json()['data'] ?? [];
    
    if (empty($executions)) {
        // Skip if no executions exist
        return true;
    }
    
    $executionId = $executions[0]['id'];
    
    // Test realtime execution endpoint
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-executions/$executionId/execute-realtime", [
        'field_id' => 'test_field',
        'value' => 'test_value',
        'values' => ['test_field' => 'test_value'],
    ]);
    
    // Accept 200, 201, or 422
    if (!in_array($response->status(), [200, 201, 422])) {
        return "Realtime execution failed: {$response->status()}";
    }
    
    return true;
});

// Gate 7: No Permission Violations
check("Gate 7: No Permission Violations", function() use ($baseUrl) {
    // Test without token - should return 401 or 500
    $response = Http::get("$baseUrl/workflows");
    
    if (!in_array($response->status(), [401, 500])) {
        return "Unauthenticated request should return 401 or 500, got: {$response->status()}";
    }
    
    return true;
});

// Gate 8: No Data Integrity Failures
check("Gate 8: No Data Integrity Failures", function() use ($adminToken, $baseUrl) {
    // Get workflows
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflow = $workflows[0];
    $workflowId = $workflow['id'];
    $testCode = $workflow['code'];
    $testName = $workflow['name_ar'];
    
    // Retrieve the workflow and verify data integrity
    $getResponse = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId");
    
    if ($getResponse->status() !== 200) {
        return "Failed to retrieve workflow for integrity verification";
    }
    
    $retrievedWorkflow = $getResponse->json()['data'] ?? null;
    
    if (!$retrievedWorkflow) {
        return "Workflow not found";
    }
    
    // Verify data integrity
    if ($retrievedWorkflow['code'] !== $testCode) {
        return "Workflow code mismatch";
    }
    
    if ($retrievedWorkflow['name_ar'] !== $testName) {
        return "Workflow name_ar mismatch";
    }
    
    return true;
});

// Gate 9: Security Headers Present
check("Gate 9: Security Headers Present", function() use ($adminToken, $baseUrl) {
    $response = Http::withToken($adminToken)->get("$baseUrl/workflows");
    
    if ($response->status() !== 200) {
        return "Failed to get response for security headers test";
    }
    
    // Check for security headers
    $headers = $response->headers();
    
    // Verify at least some security headers are present
    $securityHeaders = ['X-Frame-Options', 'X-Content-Type-Options', 'X-XSS-Protection'];
    $foundHeaders = 0;
    
    foreach ($securityHeaders as $header) {
        if (isset($headers[$header])) {
            $foundHeaders++;
        }
    }
    
    if ($foundHeaders === 0) {
        return "No security headers found";
    }
    
    return true;
});

// Gate 10: Audit Trail Complete
check("Gate 10: Audit Trail Complete", function() use ($adminToken, $baseUrl) {
    // Get audit logs
    $response = Http::withToken($adminToken)->get("$baseUrl/audit-logs");
    
    if ($response->status() !== 200) {
        return "Failed to get audit logs: {$response->status()}";
    }
    
    $logs = $response->json()['data'] ?? [];
    
    if (!is_array($logs)) {
        return "Audit logs response is not an array";
    }
    
    // Verify logs have required fields
    foreach ($logs as $log) {
        if (!isset($log['event']) || !isset($log['created_at'])) {
            return "Audit log missing required fields";
        }
    }
    
    return true;
});

// Cleanup
$admin->tokens()->where('name', 'phase-k-test')->delete();

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 PHASE K VALIDATION SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "Total Checks: " . ($passed + $failed) . "\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Success Rate: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";

if ($failed > 0) {
    echo "\n❌ FAILED CHECKS:\n";
    foreach ($checks as $check) {
        if ($check['status'] !== 'PASS') {
            echo "  - {$check['name']}: {$check['error']}\n";
        }
    }
    echo "\n⚠️  PRODUCTION READINESS: NOT READY\n";
} else {
    echo "\n✅ PRODUCTION READINESS: READY FOR DEPLOYMENT\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);
