<?php
/**
 * Phase E: Action Engine Deep Audit
 * Tests all action types: show, hide, set_visibility, set_required, set_readonly, set_value, calculate, set_fee, etc.
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

$baseUrl = 'http://localhost:8000/api/v1';

echo "🧪 Phase E: Action Engine Deep Audit\n";
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

$adminToken = $admin->createToken('phase-e-test')->plainTextToken;

// Helper function to get workflow and version
function getWorkflowAndVersion($adminToken, $baseUrl) {
    $workflows = Http::withToken($adminToken)->get("$baseUrl/workflows")->json()['data'] ?? [];
    if (empty($workflows)) {
        return null;
    }
    
    $workflowId = $workflows[0]['id'];
    $workflow = Http::withToken($adminToken)->get("$baseUrl/workflows/$workflowId")->json()['data'] ?? null;
    
    if (!$workflow || empty($workflow['versions'])) {
        return null;
    }
    
    return [
        'workflow_id' => $workflowId,
        'version_id' => $workflow['versions'][0]['id'],
    ];
}

// Test 1: Action - show
test("Action: show", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Show Action Test',
        'rule_type' => 'simple',
        'priority' => 1,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'show', 'field_id' => 'test_field'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with show action: {$response->status()}";
    }
    
    return true;
});

// Test 2: Action - hide
test("Action: hide", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Hide Action Test',
        'rule_type' => 'simple',
        'priority' => 2,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'hide', 'field_id' => 'test_field'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with hide action: {$response->status()}";
    }
    
    return true;
});

// Test 3: Action - set_visibility
test("Action: set_visibility", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Set Visibility Action Test',
        'rule_type' => 'simple',
        'priority' => 3,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'set_visibility', 'field_id' => 'test_field', 'value' => 'visible'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with set_visibility action: {$response->status()}";
    }
    
    return true;
});

// Test 4: Action - set_required
test("Action: set_required", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Set Required Action Test',
        'rule_type' => 'simple',
        'priority' => 4,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'set_required', 'field_id' => 'test_field', 'value' => true],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with set_required action: {$response->status()}";
    }
    
    return true;
});

// Test 5: Action - set_readonly
test("Action: set_readonly", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Set Readonly Action Test',
        'rule_type' => 'simple',
        'priority' => 5,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'set_readonly', 'field_id' => 'test_field', 'value' => true],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with set_readonly action: {$response->status()}";
    }
    
    return true;
});

// Test 6: Action - set_value
test("Action: set_value", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Set Value Action Test',
        'rule_type' => 'simple',
        'priority' => 6,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'set_value', 'field_id' => 'test_field', 'value' => 'test_value'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with set_value action: {$response->status()}";
    }
    
    return true;
});

// Test 7: Action - calculate
test("Action: calculate", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Calculate Action Test',
        'rule_type' => 'simple',
        'priority' => 7,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'calculate', 'field_id' => 'total_field', 'formula' => 'field1 + field2'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with calculate action: {$response->status()}";
    }
    
    return true;
});

// Test 8: Action - set_fee
test("Action: set_fee", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Set Fee Action Test',
        'rule_type' => 'simple',
        'priority' => 8,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'set_fee', 'field_id' => 'fee_field', 'fee_code' => 'TEST_FEE', 'amount' => 100],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with set_fee action: {$response->status()}";
    }
    
    return true;
});

// Test 9: Action - apply_discount
test("Action: apply_discount", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Apply Discount Action Test',
        'rule_type' => 'simple',
        'priority' => 9,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'apply_discount', 'field_id' => 'discount_field', 'percentage' => 10],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with apply_discount action: {$response->status()}";
    }
    
    return true;
});

// Test 10: Action - redirect_workflow
test("Action: redirect_workflow", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Redirect Workflow Action Test',
        'rule_type' => 'simple',
        'priority' => 10,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'redirect_workflow', 'workflow_id' => 'target_workflow_id'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with redirect_workflow action: {$response->status()}";
    }
    
    return true;
});

// Test 11: Action - redirect_step
test("Action: redirect_step", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Redirect Step Action Test',
        'rule_type' => 'simple',
        'priority' => 11,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'redirect_step', 'step_id' => 'target_step_id'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with redirect_step action: {$response->status()}";
    }
    
    return true;
});

// Test 12: Action - switch_mode
test("Action: switch_mode", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Switch Mode Action Test',
        'rule_type' => 'simple',
        'priority' => 12,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'switch_mode', 'mode' => 'edit'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with switch_mode action: {$response->status()}";
    }
    
    return true;
});

// Test 13: Action - pause_execution
test("Action: pause_execution", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Pause Execution Action Test',
        'rule_type' => 'simple',
        'priority' => 13,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'pause_execution'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with pause_execution action: {$response->status()}";
    }
    
    return true;
});

// Test 14: Action - resume_execution
test("Action: resume_execution", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Resume Execution Action Test',
        'rule_type' => 'simple',
        'priority' => 14,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'resume_execution'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with resume_execution action: {$response->status()}";
    }
    
    return true;
});

// Test 15: Action - create_record
test("Action: create_record", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Create Record Action Test',
        'rule_type' => 'simple',
        'priority' => 15,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'create_record', 'register_id' => 'test_register', 'data' => ['field1' => 'value1']],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with create_record action: {$response->status()}";
    }
    
    return true;
});

// Test 16: Action - update_record
test("Action: update_record", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Update Record Action Test',
        'rule_type' => 'simple',
        'priority' => 16,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'update_record', 'record_id' => 'test_record_id', 'data' => ['field1' => 'new_value']],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with update_record action: {$response->status()}";
    }
    
    return true;
});

// Test 17: Action - clone_execution
test("Action: clone_execution", function() use ($adminToken, $baseUrl) {
    $wf = getWorkflowAndVersion($adminToken, $baseUrl);
    if (!$wf) return "No workflow found";
    
    $response = Http::withToken($adminToken)->post("$baseUrl/workflow-versions/{$wf['version_id']}/rules", [
        'name' => 'Clone Execution Action Test',
        'rule_type' => 'simple',
        'priority' => 17,
        'is_active' => true,
        'condition_logic' => ['logic' => 'and', 'conditions' => []],
        'actions' => [
            ['type' => 'clone_execution'],
        ],
    ]);
    
    if ($response->status() !== 201) {
        return "Failed to create rule with clone_execution action: {$response->status()}";
    }
    
    return true;
});

// Cleanup
$admin->tokens()->where('name', 'phase-e-test')->delete();

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 PHASE E VALIDATION SUMMARY\n";
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
