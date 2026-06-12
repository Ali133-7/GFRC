<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\WorkflowVersion;
use App\Models\WorkflowRule;
use App\Models\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WorkflowVersionController extends ApiController
{
    /**
     * List workflow versions for a specific workflow
     */
    public function index(string $workflowId): JsonResponse
    {
        try {
            $workflow = \App\Models\Workflow::find($workflowId);

            if (!$workflow) {
                return $this->error('Workflow not found', 404);
            }

            $versions = WorkflowVersion::where('workflow_id', $workflowId)
                ->with(['workflow'])
                ->orderBy('version', 'desc')
                ->get();

            return $this->success($versions, 'Versions retrieved successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] index error', [
                'error' => $e->getMessage(),
                'workflow_id' => $workflowId,
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific workflow version by ID
     */
    public function show(string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            // First get version without workflow filter to check if it exists
            $version = WorkflowVersion::where('id', $versionId)->first();

            if (!$version) {
                Log::warning('[WorkflowVersionController] Version not found', [
                    'version_id' => $versionId,
                    'workflow_id' => $workflowId,
                ]);
                return $this->error('Workflow version not found', 404);
            }

            // Check if version belongs to the requested workflow
            if ($version->workflow_id !== $workflowId) {
                Log::error('[WorkflowVersionController] Version mismatch', [
                    'version_id' => $versionId,
                    'version_workflow_id' => $version->workflow_id,
                    'requested_workflow_id' => $workflowId,
                ]);
                return $this->error('Workflow version does not belong to this workflow', 404);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->with(['workflow', 'steps', 'fields', 'rules', 'validationRules'])
                ->first();

            return $this->success([
                'version' => $version,
            ], 'Workflow version retrieved successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] show error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new workflow version
     */
    public function store(Request $request, string $workflowId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $workflow = \App\Models\Workflow::find($workflowId);

            if (!$workflow) {
                return $this->error('Workflow not found', 404);
            }

            $validated = $request->validate([
                'change_summary' => 'nullable|string|max:500',
            ]);

            $latestVersion = WorkflowVersion::where('workflow_id', $workflowId)
                ->orderBy('version', 'desc')
                ->first();

            $newVersionNumber = $latestVersion ? $latestVersion->version + 1 : 1;

            $version = WorkflowVersion::create([
                'workflow_id' => $workflowId,
                'version' => $newVersionNumber,
                'status' => 'draft',
                'change_summary' => $validated['change_summary'] ?? 'إنشاء نسخة جديدة',
            ]);

            Log::info('[WorkflowVersionController] Version created', [
                'version_id' => $version->id,
                'workflow_id' => $workflowId,
                'version_number' => $newVersionNumber,
                'user_id' => $user->id,
            ]);

            return $this->success([
                'version' => $version,
            ], 'Version created successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all rules for a workflow version
     */
    public function getRules(string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::find($versionId);

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $rules = WorkflowRule::where('workflow_version_id', $versionId)
                ->orderBy('sort_order')
                ->get();

            return $this->success([
                'rules' => $rules,
            ], 'Rules retrieved successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] getRules error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific rule
     */
    public function getRule(string $versionId, string $ruleId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $rule = WorkflowRule::where('workflow_version_id', $versionId)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return $this->error('Rule not found', 404);
            }

            return $this->success([
                'rule' => $rule,
            ], 'Rule retrieved successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] getRule error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new rule
     */
    public function createRule(Request $request, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::find($versionId);

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'rule_type' => 'required|in:simple,case,validation,enterprise,routing,financial,realtime',
                'priority' => 'required|integer|min:0',
                'is_active' => 'boolean',
                'condition_logic' => 'nullable|array',
                'conditions' => 'nullable|array',
                'actions' => 'nullable|array',
                'cases' => 'nullable|array',
            ]);

            $rule = WorkflowRule::create([
                'workflow_version_id' => $versionId,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'rule_type' => $validated['rule_type'],
                'condition_logic' => $validated['condition_logic'] ?? $validated['conditions'] ?? [],
                'actions' => $validated['actions'] ?? [],
                'cases' => $validated['cases'] ?? [],
                'sort_order' => $validated['priority'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            Log::info('[WorkflowVersionController] Rule created', [
                'rule_id' => $rule->id,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success([
                'rule' => $rule,
            ], 'Rule created successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] createRule error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a rule
     */
    public function updateRule(Request $request, string $versionId, string $ruleId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $rule = WorkflowRule::where('workflow_version_id', $versionId)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return $this->error('Rule not found', 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'priority' => 'sometimes|integer|min:0',
                'is_active' => 'boolean',
                'conditions' => 'nullable|array',
                'actions' => 'nullable|array',
                'cases' => 'nullable|array',
            ]);

            $rule->update($validated);

            Log::info('[WorkflowVersionController] Rule updated', [
                'rule_id' => $rule->id,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success([
                'rule' => $rule->fresh(),
            ], 'Rule updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] updateRule error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a rule
     */
    public function deleteRule(string $versionId, string $ruleId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $rule = WorkflowRule::where('workflow_version_id', $versionId)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return $this->error('Rule not found', 404);
            }

            $rule->delete();

            Log::info('[WorkflowVersionController] Rule deleted', [
                'rule_id' => $ruleId,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success([], 'Rule deleted successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] deleteRule error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all validation rules for a workflow version
     */
    public function getValidationRules(string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::find($versionId);

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $rules = ValidationRule::where('workflow_version_id', $versionId)
                ->orderBy('priority')
                ->get();

            return $this->success([
                'rules' => $rules,
            ], 'Validation rules retrieved successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] getValidationRules error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a validation rule
     */
    public function createValidationRule(Request $request, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::find($versionId);

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'validation_type' => 'required|string',
                'field_id' => 'required|string',
                'is_active' => 'boolean',
                'error_message' => 'nullable|string',
                'error_message_ar' => 'nullable|string',
                'priority' => 'nullable|integer|min:0',
            ]);

            $rule = ValidationRule::create([
                'workflow_version_id' => $versionId,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'validation_type' => $validated['validation_type'],
                'field_id' => $validated['field_id'],
                'is_active' => $validated['is_active'] ?? true,
                'error_message' => $validated['error_message'] ?? null,
                'error_message_ar' => $validated['error_message_ar'] ?? null,
                'priority' => $validated['priority'] ?? 0,
                'created_by' => $user->id,
            ]);

            Log::info('[WorkflowVersionController] Validation rule created', [
                'rule_id' => $rule->id,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success([
                'rule' => $rule,
            ], 'Validation rule created successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] createValidationRule error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }
}
