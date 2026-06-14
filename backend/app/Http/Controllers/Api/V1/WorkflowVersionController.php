<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Workflow;
use App\Models\WorkflowField;
use App\Models\WorkflowVersion;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->with(['workflow', 'steps', 'fields', 'rules', 'validationRules'])
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            return $this->success($version, 'Workflow version retrieved successfully');
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

            return $this->success($version, 'Version created successfully', [], 201);
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
     * Update a workflow version.
     */
    public function update(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'change_summary' => 'nullable|string|max:500',
                'status' => 'nullable|in:draft,active,archived',
            ]);

            $version->update($validated);

            return $this->success($version->fresh(), 'Workflow version updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] update error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish a workflow version.
     */
    public function publish(string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $version->publish();
            $version->update(['published_by' => $user->id]);

            return $this->success($version->fresh(), 'Workflow version published successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] publish error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Archive a workflow version.
     */
    public function archive(string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $version->archive();

            return $this->success($version->fresh(), 'Workflow version archived successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] archive error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clone a workflow version with steps, fields, rules, and validations.
     */
    public function cloneVersion(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $source = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->with(['steps', 'fields', 'rules', 'validationRules'])
                ->first();

            if (!$source) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'change_summary' => 'nullable|string|max:500',
            ]);

            $cloned = DB::transaction(function () use ($source, $validated) {
                $newVersion = WorkflowVersion::create([
                    'workflow_id' => $source->workflow_id,
                    'version' => ((int) WorkflowVersion::where('workflow_id', $source->workflow_id)->max('version')) + 1,
                    'status' => 'draft',
                    'change_summary' => $validated['change_summary'] ?? 'نسخة مستنسخة',
                ]);

                $stepIdMap = [];

                foreach ($source->steps as $step) {
                    $newStep = WorkflowStep::create([
                        'workflow_version_id' => $newVersion->id,
                        'title_ar' => $step->title_ar,
                        'title_en' => $step->title_en,
                        'description' => $step->description,
                        'sort_order' => $step->sort_order,
                        'condition_logic' => $step->condition_logic,
                        'is_visible' => $step->is_visible,
                    ]);

                    $stepIdMap[$step->id] = $newStep->id;
                }

                $fieldIdMap = [];

                foreach ($source->fields as $field) {
                    $attributes = $field->only([
                        'register_field_id', 'label_override', 'custom_name', 'custom_label', 'placeholder',
                        'default_value', 'is_required', 'is_visible', 'is_editable', 'is_readonly', 'is_locked',
                        'is_financial', 'is_computed', 'is_insured', 'insurance_value', 'priority', 'sort_order',
                        'condition_logic', 'fee_code', 'calculation_formula', 'computed_formula',
                        'computed_dependencies', 'field_type', 'options', 'validation_rules',
                        'conditional_validation_rules', 'cross_field_validation_rules', 'parent_field_id',
                        'option_source_type', 'option_source_config', 'cascade_config',
                    ]);

                    $attributes['workflow_version_id'] = $newVersion->id;
                    $attributes['step_id'] = $field->step_id ? ($stepIdMap[$field->step_id] ?? null) : null;
                    $attributes['parent_field_id'] = null;

                    $newField = WorkflowField::create($attributes);
                    $fieldIdMap[$field->id] = $newField->id;
                }

                foreach ($source->rules as $rule) {
                    WorkflowRule::create([
                        'workflow_version_id' => $newVersion->id,
                        'name' => $rule->name,
                        'name_ar' => $rule->name_ar,
                        'rule_type' => $rule->rule_type ?? 'simple',
                        'trigger_field_id' => $this->remapCustomFieldIds($rule->trigger_field_id, $fieldIdMap),
                        'condition_logic' => $this->remapCustomFieldIds($rule->condition_logic ?? [], $fieldIdMap),
                        'actions' => $this->remapCustomFieldIds($rule->actions ?? [], $fieldIdMap),
                        'cases' => $this->remapCustomFieldIds($rule->cases ?? [], $fieldIdMap),
                        'default_actions' => $this->remapCustomFieldIds($rule->default_actions ?? [], $fieldIdMap),
                        'sort_order' => $rule->sort_order ?? 0,
                        'is_active' => $rule->is_active ?? true,
                    ]);
                }

                foreach ($source->validationRules as $rule) {
                    ValidationRule::create([
                        'workflow_version_id' => $newVersion->id,
                        'name' => $rule->name,
                        'name_ar' => $rule->name_ar,
                        'validation_type' => $rule->validation_type,
                        'field_id' => $this->remapCustomFieldIds($rule->field_id, $fieldIdMap),
                        'trigger_field_id' => $this->remapCustomFieldIds($rule->trigger_field_id, $fieldIdMap),
                        'target_fields' => $this->remapCustomFieldIds($rule->target_fields ?? [], $fieldIdMap),
                        'query_conditions' => $this->remapCustomFieldIds($rule->query_conditions ?? [], $fieldIdMap),
                        'lookup_config' => $this->remapCustomFieldIds($rule->lookup_config ?? [], $fieldIdMap),
                        'rule_config' => $this->remapCustomFieldIds($rule->rule_config ?? [], $fieldIdMap),
                        'route_config' => $this->remapCustomFieldIds($rule->route_config ?? [], $fieldIdMap),
                        'field_effects' => $this->remapCustomFieldIds($rule->field_effects ?? [], $fieldIdMap),
                        'is_active' => $rule->is_active,
                        'error_message' => $rule->error_message,
                        'error_message_ar' => $rule->error_message_ar,
                        'priority' => $rule->priority ?? 0,
                        'created_by' => $rule->created_by,
                    ]);
                }

                return $newVersion;
            });

            return $this->success(
                $cloned->fresh(['steps', 'fields', 'rules', 'validationRules']),
                'Workflow version cloned successfully',
                [],
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] cloneVersion error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a workflow step for a version.
     */
    public function storeStep(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'title_ar' => 'required|string|max:200',
                'title_en' => 'nullable|string|max:200',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
                'condition_logic' => 'nullable|array',
                'is_visible' => 'nullable|boolean',
            ]);

            $step = WorkflowStep::create([
                'workflow_version_id' => $version->id,
                'title_ar' => $validated['title_ar'],
                'title_en' => $validated['title_en'] ?? null,
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'] ?? ((int) $version->steps()->max('sort_order') + 1),
                'condition_logic' => $validated['condition_logic'] ?? [],
                'is_visible' => $validated['is_visible'] ?? true,
            ]);

            return $this->success($step->fresh(), 'Workflow step created successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] storeStep error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a workflow step.
     */
    public function updateStep(Request $request, string $workflowId, string $versionId, string $stepId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $step = WorkflowStep::where('id', $stepId)
                ->where('workflow_version_id', $versionId)
                ->whereHas('version', fn ($q) => $q->where('workflow_id', $workflowId))
                ->first();

            if (!$step) {
                return $this->error('Workflow step not found', 404);
            }

            $validated = $request->validate([
                'title_ar' => 'sometimes|string|max:200',
                'title_en' => 'nullable|string|max:200',
                'description' => 'nullable|string',
                'sort_order' => 'sometimes|integer|min:0',
                'condition_logic' => 'nullable|array',
                'is_visible' => 'nullable|boolean',
            ]);

            $step->update($validated);

            return $this->success($step->fresh(), 'Workflow step updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] updateStep error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'step_id' => $stepId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a workflow step.
     */
    public function destroyStep(string $workflowId, string $versionId, string $stepId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $step = WorkflowStep::where('id', $stepId)
                ->where('workflow_version_id', $versionId)
                ->whereHas('version', fn ($q) => $q->where('workflow_id', $workflowId))
                ->first();

            if (!$step) {
                return $this->error('Workflow step not found', 404);
            }

            $step->delete();

            return $this->success([], 'Workflow step deleted successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] destroyStep error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'step_id' => $stepId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reorder workflow steps.
     */
    public function reorderSteps(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'steps' => 'required|array|min:1',
                'steps.*.id' => 'required|uuid',
                'steps.*.sort_order' => 'required|integer|min:0',
            ]);

            DB::transaction(function () use ($version, $validated) {
                foreach ($validated['steps'] as $item) {
                    WorkflowStep::where('id', $item['id'])
                        ->where('workflow_version_id', $version->id)
                        ->update(['sort_order' => $item['sort_order']]);
                }
            });

            return $this->success(
                $version->steps()->get(),
                'Workflow steps reordered successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] reorderSteps error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a workflow field for a version.
     */
    public function storeField(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'register_field_id' => 'nullable|uuid|exists:register_fields,id',
                'step_id' => 'nullable|uuid|exists:workflow_steps,id',
                'label_override' => 'nullable|string|max:200',
                'custom_name' => 'nullable|string|max:200|required_without:register_field_id',
                'custom_label' => 'nullable|string|max:200',
                'placeholder' => 'nullable|string|max:200',
                'default_value' => 'nullable',
                'is_required' => 'nullable|boolean',
                'is_visible' => 'nullable|boolean',
                'is_editable' => 'nullable|boolean',
                'is_readonly' => 'nullable|boolean',
                'is_locked' => 'nullable|boolean',
                'is_financial' => 'nullable|boolean',
                'is_computed' => 'nullable|boolean',
                'is_insured' => 'nullable|boolean',
                'insurance_value' => 'nullable|numeric',
                'priority' => 'nullable|integer',
                'sort_order' => 'nullable|integer|min:0',
                'condition_logic' => 'nullable|array',
                'fee_code' => 'nullable|string|max:50',
                'calculation_formula' => 'nullable|string',
                'computed_formula' => 'nullable|string',
                'computed_dependencies' => 'nullable|array',
                'field_type' => 'nullable|string|max:50',
                'options' => 'nullable|array',
                'validation_rules' => 'nullable|array',
                'conditional_validation_rules' => 'nullable|array',
                'cross_field_validation_rules' => 'nullable|array',
                'parent_field_id' => 'nullable|uuid|exists:workflow_fields,id',
                'option_source_type' => 'nullable|string|max:50',
                'option_source_config' => 'nullable|array',
                'cascade_config' => 'nullable|array',
            ]);

            if (!empty($validated['step_id'])) {
                $stepExists = WorkflowStep::where('id', $validated['step_id'])
                    ->where('workflow_version_id', $version->id)
                    ->exists();
                if (!$stepExists) {
                    return $this->error('Selected step does not belong to this workflow version', 422);
                }
            }

            $field = WorkflowField::create([
                ...$validated,
                'workflow_version_id' => $version->id,
                'sort_order' => $validated['sort_order'] ?? ((int) $version->fields()->max('sort_order') + 1),
                'condition_logic' => $validated['condition_logic'] ?? [],
                'options' => $validated['options'] ?? null,
                'validation_rules' => $validated['validation_rules'] ?? null,
                'conditional_validation_rules' => $validated['conditional_validation_rules'] ?? null,
                'cross_field_validation_rules' => $validated['cross_field_validation_rules'] ?? null,
                'computed_dependencies' => $validated['computed_dependencies'] ?? null,
                'option_source_config' => $validated['option_source_config'] ?? null,
                'cascade_config' => $validated['cascade_config'] ?? null,
                'is_required' => $validated['is_required'] ?? false,
                'is_visible' => $validated['is_visible'] ?? true,
                'is_editable' => $validated['is_editable'] ?? true,
                'is_readonly' => $validated['is_readonly'] ?? false,
                'is_locked' => $validated['is_locked'] ?? false,
                'is_financial' => $validated['is_financial'] ?? false,
                'is_computed' => $validated['is_computed'] ?? false,
                'is_insured' => $validated['is_insured'] ?? false,
            ]);

            return $this->success($field->fresh(), 'Workflow field created successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] storeField error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a workflow field.
     */
    public function updateField(Request $request, string $workflowId, string $versionId, string $fieldId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $field = WorkflowField::where('id', $fieldId)
                ->where('workflow_version_id', $versionId)
                ->whereHas('version', fn ($q) => $q->where('workflow_id', $workflowId))
                ->first();

            if (!$field) {
                return $this->error('Workflow field not found', 404);
            }

            $validated = $request->validate([
                'register_field_id' => 'nullable|uuid|exists:register_fields,id',
                'step_id' => 'nullable|uuid|exists:workflow_steps,id',
                'label_override' => 'nullable|string|max:200',
                'custom_name' => 'nullable|string|max:200',
                'custom_label' => 'nullable|string|max:200',
                'placeholder' => 'nullable|string|max:200',
                'default_value' => 'nullable',
                'is_required' => 'nullable|boolean',
                'is_visible' => 'nullable|boolean',
                'is_editable' => 'nullable|boolean',
                'is_readonly' => 'nullable|boolean',
                'is_locked' => 'nullable|boolean',
                'is_financial' => 'nullable|boolean',
                'is_computed' => 'nullable|boolean',
                'is_insured' => 'nullable|boolean',
                'insurance_value' => 'nullable|numeric',
                'priority' => 'nullable|integer',
                'sort_order' => 'nullable|integer|min:0',
                'condition_logic' => 'nullable|array',
                'fee_code' => 'nullable|string|max:50',
                'calculation_formula' => 'nullable|string',
                'computed_formula' => 'nullable|string',
                'computed_dependencies' => 'nullable|array',
                'field_type' => 'nullable|string|max:50',
                'options' => 'nullable|array',
                'validation_rules' => 'nullable|array',
                'conditional_validation_rules' => 'nullable|array',
                'cross_field_validation_rules' => 'nullable|array',
                'parent_field_id' => 'nullable|uuid|exists:workflow_fields,id',
                'option_source_type' => 'nullable|string|max:50',
                'option_source_config' => 'nullable|array',
                'cascade_config' => 'nullable|array',
            ]);

            if (array_key_exists('step_id', $validated) && !empty($validated['step_id'])) {
                $stepExists = WorkflowStep::where('id', $validated['step_id'])
                    ->where('workflow_version_id', $versionId)
                    ->exists();
                if (!$stepExists) {
                    return $this->error('Selected step does not belong to this workflow version', 422);
                }
            }

            $field->update($validated);

            return $this->success($field->fresh(), 'Workflow field updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] updateField error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'field_id' => $fieldId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a workflow field.
     */
    public function destroyField(string $workflowId, string $versionId, string $fieldId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $field = WorkflowField::where('id', $fieldId)
                ->where('workflow_version_id', $versionId)
                ->whereHas('version', fn ($q) => $q->where('workflow_id', $workflowId))
                ->first();

            if (!$field) {
                return $this->error('Workflow field not found', 404);
            }

            $field->delete();

            return $this->success([], 'Workflow field deleted successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] destroyField error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'field_id' => $fieldId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reorder workflow fields.
     */
    public function reorderFields(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $version = WorkflowVersion::where('id', $versionId)
                ->where('workflow_id', $workflowId)
                ->first();

            if (!$version) {
                return $this->error('Workflow version not found', 404);
            }

            $validated = $request->validate([
                'fields' => 'required|array|min:1',
                'fields.*.workflow_field_id' => 'required|uuid',
                'fields.*.sort_order' => 'required|integer|min:0',
            ]);

            DB::transaction(function () use ($version, $validated) {
                foreach ($validated['fields'] as $item) {
                    WorkflowField::where('id', $item['workflow_field_id'])
                        ->where('workflow_version_id', $version->id)
                        ->update(['sort_order' => $item['sort_order']]);
                }
            });

            return $this->success(
                $version->fields()->get(),
                'Workflow fields reordered successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] reorderFields error', [
                'workflow_id' => $workflowId,
                'version_id' => $versionId,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
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

            return $this->success($rules, 'Rules retrieved successfully');
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

            return $this->success($rule, 'Rule retrieved successfully');
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
    public function storeRule(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        return $this->createRule($request, $versionId);
    }

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
                'rule_type' => 'nullable|in:simple,case_based,validation,enterprise,routing,financial,realtime',
                'trigger_field_id' => 'nullable|string|max:100',
                'priority' => 'nullable|integer|min:0',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'condition_logic' => 'nullable|array',
                'conditions' => 'nullable|array',
                'actions' => 'nullable|array',
                'cases' => 'nullable|array',
                'default_actions' => 'nullable|array',
            ]);

            $rule = WorkflowRule::create([
                'workflow_version_id' => $versionId,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'rule_type' => $validated['rule_type'] ?? 'simple',
                'trigger_field_id' => $validated['trigger_field_id'] ?? null,
                'condition_logic' => $validated['condition_logic'] ?? $validated['conditions'] ?? [],
                'actions' => $validated['actions'] ?? [],
                'cases' => $validated['cases'] ?? [],
                'default_actions' => $validated['default_actions'] ?? [],
                'sort_order' => $validated['priority'] ?? $validated['sort_order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            Log::info('[WorkflowVersionController] Rule created', [
                'rule_id' => $rule->id,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success($rule, 'Rule created successfully', [], 201);
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
    public function updateRule(Request $request, string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $rule = WorkflowRule::where('id', $ruleId)
                ->whereHas('version', fn ($q) => $q->where('id', $versionId)->where('workflow_id', $workflowId))
                ->first();

            if (!$rule) {
                return $this->error('Rule not found', 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'rule_type' => 'nullable|in:simple,case_based,validation,enterprise,routing,financial,realtime',
                'trigger_field_id' => 'nullable|string|max:100',
                'priority' => 'sometimes|integer|min:0',
                'sort_order' => 'sometimes|integer|min:0',
                'is_active' => 'boolean',
                'condition_logic' => 'nullable|array',
                'conditions' => 'nullable|array',
                'actions' => 'nullable|array',
                'cases' => 'nullable|array',
                'default_actions' => 'nullable|array',
            ]);

            $updateData = $validated;
            if (array_key_exists('conditions', $validated) && !array_key_exists('condition_logic', $validated)) {
                $updateData['condition_logic'] = $validated['conditions'];
                unset($updateData['conditions']);
            }

            $rule->update($updateData);

            Log::info('[WorkflowVersionController] Rule updated', [
                'rule_id' => $rule->id,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success($rule->fresh(), 'Rule updated successfully');
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
    public function destroyRule(string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        return $this->deleteRule($versionId, $ruleId);
    }

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

            return $this->success($rules, 'Validation rules retrieved successfully');
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
    public function storeValidationRule(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        return $this->createValidationRule($request, $versionId);
    }

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
                'field_id' => 'nullable|string',
                'trigger_field_id' => 'nullable|string',
                'target_register_id' => 'nullable|uuid|exists:registers,id',
                'target_fields' => 'nullable|array',
                'query_conditions' => 'nullable|array',
                'lookup_config' => 'nullable|array',
                'expectation' => 'nullable|string',
                'rule_config' => 'nullable|array',
                'route_config' => 'nullable|array',
                'field_effects' => 'nullable|array',
                'is_active' => 'boolean',
                'response_type' => 'nullable|string',
                'error_message' => 'nullable|string',
                'error_message_ar' => 'nullable|string',
                'priority' => 'nullable|integer|min:0',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            $rule = ValidationRule::create([
                'workflow_version_id' => $versionId,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'validation_type' => $validated['validation_type'],
                'field_id' => $validated['field_id'] ?? null,
                'trigger_field_id' => $validated['trigger_field_id'] ?? null,
                'target_register_id' => $validated['target_register_id'] ?? null,
                'target_fields' => $validated['target_fields'] ?? null,
                'query_conditions' => $validated['query_conditions'] ?? null,
                'lookup_config' => $validated['lookup_config'] ?? null,
                'expectation' => $validated['expectation'] ?? null,
                'rule_config' => $validated['rule_config'] ?? null,
                'route_config' => $validated['route_config'] ?? null,
                'field_effects' => $validated['field_effects'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'response_type' => $validated['response_type'] ?? 'error',
                'error_message' => $validated['error_message'] ?? null,
                'error_message_ar' => $validated['error_message_ar'] ?? null,
                'priority' => $validated['priority'] ?? $validated['sort_order'] ?? 0,
                'sort_order' => $validated['sort_order'] ?? $validated['priority'] ?? 0,
                'created_by' => $user->id,
            ]);

            Log::info('[WorkflowVersionController] Validation rule created', [
                'rule_id' => $rule->id,
                'version_id' => $versionId,
                'user_id' => $user->id,
            ]);

            return $this->success($rule, 'Validation rule created successfully', [], 201);
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

    public function destroyValidationRule(string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $rule = ValidationRule::where('workflow_version_id', $versionId)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return $this->error('Validation rule not found', 404);
            }

            $rule->delete();

            return $this->success([], 'Validation rule deleted successfully');
        } catch (\Exception $e) {
            Log::error('[WorkflowVersionController] destroyValidationRule error', [
                'version_id' => $versionId,
                'rule_id' => $ruleId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remap references that use the custom_<workflow_field_id> convention after
     * a workflow version has been cloned (new workflow field ids are generated).
     */
    protected function remapCustomFieldIds(mixed $data, array $fieldIdMap): mixed
    {
        if (is_string($data)) {
            return preg_replace_callback(
                '/custom_([a-f0-9-]{36})/i',
                fn ($matches) => 'custom_' . ($fieldIdMap[$matches[1]] ?? $matches[1]),
                $data
            );
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $newKey = is_string($key) ? $this->remapCustomFieldIds($key, $fieldIdMap) : $key;
                $result[$newKey] = $this->remapCustomFieldIds($value, $fieldIdMap);
            }
            return $result;
        }

        return $data;
    }
}
