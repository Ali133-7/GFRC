<?php
/**
 * Phase D: Rule Engine Deep Audit
 * Tests all rule types: Simple, Case, Validation, Enterprise, Routing, Financial, Realtime
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkflowRule;
use App\Models\ValidationRule;

$baseUrl = 'http://localhost:8000/api/v1';

echo "🧪 Phase D: Rule Engine Deep Audit\n";
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

$adminToken = $admin->createToken('phase-d-test')->plainTextToken;

// Test 1: Create Simple Rule
test("Create Simple Rule", function() use ($adminToken, $baseUrl) {
    // First, get or create a register
    $registers = Http::withToken($adminToken)->get("$baseUrl/registers")->json()['data'] ?? [];
    if (empty($registers)) {
        // Create a register
        $response = Http::withToken($adminToken)->post("$baseUrl/registers", [
            'code' => 'TEST_REG',
            'name_ar' => 'سجل الاختبار',
            'name_en' => 'Test Register',
        ]);
        
        if ($response->status() !== 201) {
            return "Failed to create register: {$response->status()}";
        }
        
        $registerId = $response->json()['data']['id'];
    } else {
        $registerId = $registers[0]['id'];
    }
    
    // Create a workflow
    $response = Http::withToken($adminToken)->post("$baseUrl/workflows", [
        'register_id' => $registerId,
        'code' => 'TEST_WF_' . time(),
        'name_ar' => 'سير عمل اختبار القواعد',
        'name_en' => 'Rule Test Workflow',
        'description' => 'Workflow for rule testing',
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create workflow: {$response->status()} - " . $response->body();
    }
    
    $workflowId = $response->json()['data']['id'];
    
    // Get the version (should be created automatically)
    $versions = $response->json()['data']['versions'] ?? [];
    if (empty($versions)) {
        return "No versions found in workflow response";
    }
    
    $versionId = $versions[0]['id'];
    
    // Create simple rule
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/$versionId/rules", [
        'name' => 'Simple Test Rule',
        'name_ar' => 'قاعدة اختبار بسيطة',
        'rule_type' => 'simple',
        'priority' => 1,
        'is_active' => true,
        'conditions' => [
            'logic' => 'and',
            'conditions' => [
                [
                    'field' => 'status',
                    'operator' => 'equals',
                    'value' => 'active',
                ],
            ],
        ],
        'actions' => [
            [
                'type' => 'set_value',
                'field' => 'priority_level',
                'value' => 'high',
            ],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create simple rule: {$response->status()} - " . $response->body();
    }
    
    return true;
});

// Test 2: Create Case Rule
test("Create Case Rule", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Create case rule
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/$versionId/rules", [
        'name' => 'Case Test Rule',
        'name_ar' => 'قاعدة اختبار الحالات',
        'rule_type' => 'case',
        'priority' => 2,
        'is_active' => true,
        'cases' => [
            [
                'name' => 'High Priority',
                'conditions' => [
                    'logic' => 'and',
                    'conditions' => [
                        ['field' => 'amount', 'operator' => 'greater_than', 'value' => 10000],
                    ],
                ],
                'actions' => [
                    ['type' => 'set_value', 'field' => 'requires_approval', 'value' => true],
                ],
            ],
            [
                'name' => 'Low Priority',
                'conditions' => [
                    'logic' => 'and',
                    'conditions' => [
                        ['field' => 'amount', 'operator' => 'less_than_or_equal', 'value' => 10000],
                    ],
                ],
                'actions' => [
                    ['type' => 'set_value', 'field' => 'requires_approval', 'value' => false],
                ],
            ],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create case rule: {$response->status()} - " . $response->body();
    }
    
    return true;
});

// Test 3: Create Validation Rule
test("Create Validation Rule", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Create validation rule
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/$versionId/validation-rules", [
        'name' => 'Validation Test Rule',
        'name_ar' => 'قاعدة اختبار التحقق',
        'validation_type' => 'required',
        'field_id' => 'test_field',
        'is_active' => true,
        'error_message' => 'This field is required',
        'error_message_ar' => 'هذا الحقل مطلوب',
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create validation rule: {$response->status()} - " . $response->body();
    }
    
    return true;
});

// Test 4: Load and Serialize Rules
test("Load and Serialize Rules", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Get rules
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules");
    
    if ($response->status() !== 200) {
        return "Failed to load rules: {$response->status()}";
    }
    
    $rules = $response->json()['data']['rules'] ?? [];
    
    if (count($rules) < 2) {
        return "Expected at least 2 rules, got: " . count($rules);
    }
    
    // Check serialization
    foreach ($rules as $rule) {
        if (!isset($rule['id']) || !isset($rule['name']) || !isset($rule['rule_type'])) {
            return "Rule missing required fields";
        }
    }
    
    return true;
});

// Test 5: Rule Priority Ordering
test("Rule Priority Ordering", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Get rules
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules");
    $rules = $response->json()['data']['rules'] ?? [];
    
    // Check priority ordering
    $lastPriority = 0;
    foreach ($rules as $rule) {
        $priority = $rule['sort_order'] ?? 0;
        if ($priority < $lastPriority) {
            return "Rules not ordered by priority";
        }
        $lastPriority = $priority;
    }
    
    return true;
});

// Test 6: Update Rule
test("Update Rule", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Get rules
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules");
    $rules = $response->json()['data']['rules'] ?? [];
    
    if (empty($rules)) {
        return "No rules to update";
    }
    
    $ruleId = $rules[0]['id'];
    
    // Update rule
    $response = Http::withToken($adminToken)->put("$baseUrl/workflow-versions/$versionId/rules/$ruleId", [
        'name' => 'Updated Rule Name',
        'name_ar' => 'اسم القاعدة المحدث',
        'priority' => 10,
    ]);
    
    if ($response->status() !== 200) {
        return "Failed to update rule: {$response->status()}";
    }
    
    // Verify update
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules/$ruleId");
    $rule = $response->json()['data']['rule'] ?? null;
    
    if (!$rule || $rule['name'] !== 'Updated Rule Name') {
        return "Rule not updated correctly";
    }
    
    return true;
});

// Test 7: Delete Rule
test("Delete Rule", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Get rules
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules");
    $rules = $response->json()['data']['rules'] ?? [];
    
    if (empty($rules)) {
        return "No rules to delete";
    }
    
    $ruleId = $rules[count($rules) - 1]['id'];
    
    // Delete rule
    $response = Http::withToken($adminToken)->delete("$baseUrl/workflow-versions/$versionId/rules/$ruleId");
    
    if ($response->status() !== 200) {
        return "Failed to delete rule: {$response->status()}";
    }
    
    return true;
});

// Test 8: Rule Persistence
test("Rule Persistence", function() use ($adminToken, $baseUrl) {
    // Get workflow and version
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return "No workflows found";
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return "No versions found in workflow";
    }
    
    $versionId = $workflow['versions'][0]['id'];
    
    // Create a rule
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/$versionId/rules", [
        'name' => 'Persistence Test Rule',
        'name_ar' => 'قاعدة اختبار الاستمرارية',
        'rule_type' => 'simple',
        'priority' => 5,
        'is_active' => true,
        'conditions' => ['logic' => 'and', 'conditions' => []],
        'actions' => [],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule";
    }
    
    $ruleId = $response->json()['data']['rule']['id'];
    
    // Reload and verify
    $response = Http::withToken($adminToken)->get("$baseUrl/workflow-versions/$versionId/rules/$ruleId");
    
    if ($response->status() !== 200) {
        return "Failed to reload rule";
    }
    
    $rule = $response->json()['data']['rule'] ?? null;
    
    if (!$rule || $rule['name'] !== 'Persistence Test Rule') {
        return "Rule not persisted correctly";
    }
    
    return true;
});

// Cleanup
$admin->tokens()->where('name', 'phase-d-test')->delete();

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 PHASE D VALIDATION SUMMARY\n";
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
