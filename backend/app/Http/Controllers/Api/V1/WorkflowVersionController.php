<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Workflow;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowVersionController extends ApiController
{
    public function index(string $workflowId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $this->authorize('view', $workflow);

        $versions = $workflow->versions()->with(['steps', 'publisher'])->get();
        return $this->success($versions);
    }

    public function store(Request $request, string $workflowId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $this->authorize('update', $workflow);

        $data = $request->validate([
            'change_summary' => 'nullable|string',
        ]);

        // Clone from active version, or latest version if no active exists
        $sourceVersion = $workflow->versions()->where('status', 'active')->first()
            ?? $workflow->versions()->orderByDesc('version')->first();

        $latestVersion = $workflow->versions()->max('version') ?? 0;
        $newVersionNumber = $latestVersion + 1;

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => $newVersionNumber,
            'status' => 'draft',
            'change_summary' => $data['change_summary'] ?? 'نسخة جديدة',
        ]);

        // Clone all data from source version if one exists
        if ($sourceVersion) {
            DB::transaction(function () use ($sourceVersion, $version) {
                // Clone steps with ID remapping
                $stepMap = [];
                foreach ($sourceVersion->steps as $step) {
                    $newStep = WorkflowStep::create([
                        'workflow_version_id' => $version->id,
                        'title_ar' => $step->title_ar,
                        'title_en' => $step->title_en,
                        'description' => $step->description,
                        'sort_order' => $step->sort_order,
                        'condition_logic' => $step->condition_logic,
                        'is_visible' => $step->is_visible,
                    ]);
                    $stepMap[$step->id] = $newStep->id;
                }

                // Clone fields with remapped step_id
                foreach ($sourceVersion->fields as $field) {
                    WorkflowField::create([
                        'workflow_version_id' => $version->id,
                        'register_field_id' => $field->register_field_id,
                        'step_id' => $field->step_id ? ($stepMap[$field->step_id] ?? null) : null,
                        'label_override' => $field->label_override,
                        'custom_name' => $field->custom_name,
                        'custom_label' => $field->custom_label,
                        'placeholder' => $field->placeholder,
                        'default_value' => $field->default_value,
                        'is_required' => $field->is_required,
                        'is_visible' => $field->is_visible,
                        'is_editable' => $field->is_editable,
                        'is_readonly' => $field->is_readonly,
                        'is_locked' => $field->is_locked,
                        'is_financial' => $field->is_financial,
                        'is_insured' => $field->is_insured,
                        'insurance_value' => $field->insurance_value,
                        'priority' => $field->priority,
                        'is_computed' => $field->is_computed,
                        'sort_order' => $field->sort_order,
                        'condition_logic' => $field->condition_logic,
                        'fee_code' => $field->fee_code,
                        'calculation_formula' => $field->calculation_formula,
                        'field_type' => $field->field_type,
                        'options' => $field->options,
                        'validation_rules' => $field->validation_rules,
                        'conditional_validation_rules' => $field->conditional_validation_rules,
                        'cross_field_validation_rules' => $field->cross_field_validation_rules,
                        'computed_formula' => $field->computed_formula,
                        'computed_dependencies' => $field->computed_dependencies,
                        'parent_field_id' => $field->parent_field_id,
                        'option_source_type' => $field->option_source_type,
                        'option_source_config' => $field->option_source_config,
                        'cascade_config' => $field->cascade_config,
                    ]);
                }

                // Clone workflow rules
                foreach ($sourceVersion->rules as $rule) {
                    WorkflowRule::create([
                        'workflow_version_id' => $version->id,
                        'name' => $rule->name,
                        'description' => $rule->description,
                        'rule_type' => $rule->rule_type,
                        'trigger_field_id' => $rule->trigger_field_id,
                        'cases' => $rule->cases,
                        'default_actions' => $rule->default_actions,
                        'match_mode' => $rule->match_mode,
                        'condition_logic' => $rule->condition_logic,
                        'actions' => $rule->actions,
                        'sort_order' => $rule->sort_order,
                        'is_active' => $rule->is_active,
                    ]);
                }

                // Clone validation rules (including enterprise rule_config)
                foreach ($sourceVersion->validationRules as $vRule) {
                    \App\Models\ValidationRule::create([
                        'workflow_version_id' => $version->id,
                        'name' => $vRule->name,
                        'description' => $vRule->description,
                        'validation_type' => $vRule->validation_type,
                        'target_register_id' => $vRule->target_register_id,
                        'trigger_field_id' => $vRule->trigger_field_id,
                        'trigger_conditions' => $vRule->trigger_conditions,
                        'target_fields' => $vRule->target_fields,
                        'query_conditions' => $vRule->query_conditions,
                        'sql_query' => $vRule->sql_query,
                        'sql_condition' => $vRule->sql_condition,
                        'route_config' => $vRule->route_config,
                        'lookup_config' => $vRule->lookup_config,
                        'field_effects' => $vRule->field_effects,
                        'response_type' => $vRule->response_type,
                        'error_message_ar' => $vRule->error_message_ar,
                        'error_message_en' => $vRule->error_message_en,
                        'confirm_message_ar' => $vRule->confirm_message_ar,
                        'confirm_message_en' => $vRule->confirm_message_en,
                        'sort_order' => $vRule->sort_order,
                        'is_active' => $vRule->is_active,
                        'rule_config' => $vRule->rule_config,
                        'priority' => $vRule->priority,
                        'category' => $vRule->category,
                    ]);
                }
            });
        }

        return $this->success(
            $version->load(['steps', 'fields.registerField', 'rules', 'validationRules']),
            'تم إنشاء نسخة جديدة',
            [],
            201
        );
    }

    public function show(string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $this->authorize('view', $workflow);

        $version = $workflow->versions()
            ->where('id', $versionId)
            ->with(['steps.fields.registerField', 'fields.registerField', 'rules', 'validationRules.targetRegister', 'publisher'])
            ->firstOrFail();

        return $this->success($version);
    }

    public function update(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();

        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'change_summary' => 'nullable|string',
        ]);

        $version->update($data);

        return $this->success($version);
    }

    public function publish(string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();

        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('النسخة ليست مسودة', 422);
        }

        // Archive any currently active version
        $workflow->versions()->where('status', 'active')->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        $version->publish();

        return $this->success($version->fresh(), 'تم نشر النسخة بنجاح');
    }

    public function archive(string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();

        $this->authorize('update', $workflow);

        $version->archive();

        return $this->success($version->fresh(), 'تم أرشفة النسخة بنجاح');
    }

    public function cloneVersion(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $sourceVersion = $workflow->versions()->where('id', $versionId)->firstOrFail();

        $this->authorize('update', $workflow);

        $newVersion = DB::transaction(function () use ($workflow, $sourceVersion, $request) {
            $latestVersion = $workflow->versions()->max('version') ?? 0;

            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version' => $latestVersion + 1,
                'status' => 'draft',
                'change_summary' => $request->input('change_summary', 'نسخة مستنسخة من V' . $sourceVersion->version),
            ]);

            // Clone steps
            $stepMap = [];
            foreach ($sourceVersion->steps as $step) {
                $newStep = WorkflowStep::create([
                    'workflow_version_id' => $version->id,
                    'title_ar' => $step->title_ar,
                    'title_en' => $step->title_en,
                    'description' => $step->description,
                    'sort_order' => $step->sort_order,
                    'condition_logic' => $step->condition_logic,
                    'is_visible' => $step->is_visible,
                ]);
                $stepMap[$step->id] = $newStep->id;
            }

            // Clone fields
            foreach ($sourceVersion->fields as $field) {
                WorkflowField::create([
                    'workflow_version_id' => $version->id,
                    'register_field_id' => $field->register_field_id,
                    'step_id' => $field->step_id ? ($stepMap[$field->step_id] ?? null) : null,
                    'label_override' => $field->label_override,
                    'custom_name' => $field->custom_name,
                    'custom_label' => $field->custom_label,
                    'placeholder' => $field->placeholder,
                    'default_value' => $field->default_value,
                    'is_required' => $field->is_required,
                    'is_visible' => $field->is_visible,
                    'is_editable' => $field->is_editable,
                    'is_readonly' => $field->is_readonly,
                    'is_locked' => $field->is_locked,
                    'is_financial' => $field->is_financial,
                    'is_insured' => $field->is_insured,
                    'insurance_value' => $field->insurance_value,
                    'priority' => $field->priority,
                    'is_computed' => $field->is_computed,
                    'sort_order' => $field->sort_order,
                    'condition_logic' => $field->condition_logic,
                    'fee_code' => $field->fee_code,
                    'calculation_formula' => $field->calculation_formula,
                    'field_type' => $field->field_type,
                    'options' => $field->options,
                    'validation_rules' => $field->validation_rules,
                    'conditional_validation_rules' => $field->conditional_validation_rules,
                    'cross_field_validation_rules' => $field->cross_field_validation_rules,
                    'computed_formula' => $field->computed_formula,
                    'computed_dependencies' => $field->computed_dependencies,
                    'parent_field_id' => $field->parent_field_id,
                    'option_source_type' => $field->option_source_type,
                    'option_source_config' => $field->option_source_config,
                    'cascade_config' => $field->cascade_config,
                ]);
            }

            // Clone rules
            foreach ($sourceVersion->rules as $rule) {
                WorkflowRule::create([
                    'workflow_version_id' => $version->id,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'rule_type' => $rule->rule_type,
                    'trigger_field_id' => $rule->trigger_field_id,
                    'cases' => $rule->cases,
                    'default_actions' => $rule->default_actions,
                    'match_mode' => $rule->match_mode,
                    'condition_logic' => $rule->condition_logic,
                    'actions' => $rule->actions,
                    'sort_order' => $rule->sort_order,
                    'is_active' => $rule->is_active,
                ]);
            }

            // Clone validation rules (including enterprise rule_config)
            foreach ($sourceVersion->validationRules as $vRule) {
                \App\Models\ValidationRule::create([
                    'workflow_version_id' => $version->id,
                    'name' => $vRule->name,
                    'description' => $vRule->description,
                    'validation_type' => $vRule->validation_type,
                    'target_register_id' => $vRule->target_register_id,
                    'trigger_field_id' => $vRule->trigger_field_id,
                    'trigger_conditions' => $vRule->trigger_conditions,
                    'target_fields' => $vRule->target_fields,
                    'query_conditions' => $vRule->query_conditions,
                    'sql_query' => $vRule->sql_query,
                    'sql_condition' => $vRule->sql_condition,
                    'route_config' => $vRule->route_config,
                    'lookup_config' => $vRule->lookup_config,
                    'field_effects' => $vRule->field_effects,
                    'response_type' => $vRule->response_type,
                    'error_message_ar' => $vRule->error_message_ar,
                    'error_message_en' => $vRule->error_message_en,
                    'confirm_message_ar' => $vRule->confirm_message_ar,
                    'confirm_message_en' => $vRule->confirm_message_en,
                    'sort_order' => $vRule->sort_order,
                    'is_active' => $vRule->is_active,
                    'rule_config' => $vRule->rule_config,
                    'priority' => $vRule->priority,
                    'category' => $vRule->category,
                ]);
            }

            return $version;
        });

        return $this->success($newVersion->load(['steps', 'fields.registerField', 'rules', 'validationRules']), 'تم استنساخ النسخة بنجاح', [], 201);
    }

    // --- Steps ---

    public function storeStep(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'title_ar' => 'required|string|max:200',
            'title_en' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'condition_logic' => 'nullable|array',
            'is_visible' => 'nullable|boolean',
        ]);
        $data['workflow_version_id'] = $version->id;

        $step = WorkflowStep::create($data);
        return $this->success($step, '', [], 201);
    }

    public function updateStep(Request $request, string $workflowId, string $versionId, string $stepId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $step = $version->steps()->where('id', $stepId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $step->update($request->only([
            'title_ar', 'title_en', 'description', 'sort_order', 'condition_logic', 'is_visible'
        ]));

        return $this->success($step->fresh());
    }

    public function destroyStep(string $workflowId, string $versionId, string $stepId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $step = $version->steps()->where('id', $stepId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $step->delete();
        return $this->success([], 'تم حذف الخطوة');
    }

    public function reorderSteps(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        $data = $request->validate(['steps' => 'required|array', 'steps.*.id' => 'required|string', 'steps.*.sort_order' => 'required|integer']);

        foreach ($data['steps'] as $stepData) {
            $version->steps()->where('id', $stepData['id'])->update(['sort_order' => $stepData['sort_order']]);
        }

        return $this->success($version->steps()->orderBy('sort_order')->get());
    }

    // --- Fields ---

    public function storeField(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'register_field_id' => 'nullable|string|exists:register_fields,id',
            'custom_name' => 'nullable|string|max:100',
            'custom_label' => 'nullable|string|max:200',
            'step_id' => 'nullable|string|exists:workflow_steps,id',
            'label_override' => 'nullable|string|max:200',
            'placeholder' => 'nullable|string|max:200',
            'default_value' => 'nullable|string',
            'is_required' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'is_readonly' => 'nullable|boolean',
            'is_editable' => 'nullable|boolean',
            'is_locked' => 'nullable|boolean',
            'is_financial' => 'nullable|boolean',
            'is_insured' => 'nullable|boolean',
            'insurance_value' => 'nullable|numeric',
            'priority' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'condition_logic' => 'nullable|array',
            'fee_code' => 'nullable|string|max:50',
            'calculation_formula' => 'nullable|string',
            'field_type' => 'nullable|string|max:30',
            'options' => 'nullable|array',
            'validation_rules' => 'nullable|array',
        ]);

        if (empty($data['register_field_id']) && empty($data['custom_name'])) {
            return $this->error('يجب تحديد register_field_id أو custom_name', 422);
        }

        // Snapshot register field properties when adding to workflow
        if (!empty($data['register_field_id']) && empty($data['custom_name'])) {
            $registerField = \App\Models\RegisterField::find($data['register_field_id']);
            if ($registerField) {
                $data['field_type'] = $data['field_type'] ?? $registerField->field_type;
                $data['options'] = $data['options'] ?? $registerField->options;
                $data['default_value'] = $data['default_value'] ?? $registerField->default_value;
                $data['validation_rules'] = $data['validation_rules'] ?? $registerField->validation_rules;
                $data['is_required'] = $data['is_required'] ?? $registerField->is_required;
                $data['is_visible'] = $data['is_visible'] ?? $registerField->is_visible;
                $data['is_editable'] = $data['is_editable'] ?? $registerField->is_editable;
                $data['is_locked'] = $data['is_locked'] ?? $registerField->is_locked;
                $data['is_financial'] = $data['is_financial'] ?? $registerField->is_financial;
                $data['is_insured'] = $data['is_insured'] ?? $registerField->is_insured;
                $data['insurance_value'] = $data['insurance_value'] ?? $registerField->insurance_value;
                $data['priority'] = $data['priority'] ?? $registerField->priority;
                $data['placeholder'] = $data['placeholder'] ?? $registerField->name;
                $data['sort_order'] = $data['sort_order'] ?? $registerField->sort_order;
            }
        }

        if (empty($data['sort_order'])) {
            $maxSortOrder = $version->fields()->max('sort_order') ?? 0;
            $data['sort_order'] = $maxSortOrder + 1;
        }

        $data['workflow_version_id'] = $version->id;

        $field = WorkflowField::create($data);
        return $this->success($field->load('registerField'), '', [], 201);
    }

    public function updateField(Request $request, string $workflowId, string $versionId, string $fieldId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $field = $version->fields()->where('id', $fieldId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $field->update($request->only([
            'step_id', 'label_override', 'custom_name', 'custom_label', 'placeholder', 'default_value',
            'is_required', 'is_visible', 'is_readonly', 'is_editable', 'is_locked',
            'is_financial', 'is_insured', 'insurance_value', 'priority',
            'sort_order', 'condition_logic', 'fee_code', 'calculation_formula',
            'field_type', 'options', 'validation_rules',
            'conditional_validation_rules', 'cross_field_validation_rules',
            'computed_formula', 'computed_dependencies', 'is_computed',
            'parent_field_id', 'option_source_type', 'option_source_config', 'cascade_config',
        ]));

        return $this->success($field->fresh()->load('registerField'));
    }

    public function destroyField(string $workflowId, string $versionId, string $fieldId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $field = $version->fields()->where('id', $fieldId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $field->delete();
        return $this->success([], 'تم حذف الحقل');
    }

    public function reorderFields(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'fields' => 'required|array',
            'fields.*.workflow_field_id' => 'required|string',
            'fields.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($version, $data) {
            foreach ($data['fields'] as $fieldData) {
                $version->fields()
                    ->where('id', $fieldData['workflow_field_id'])
                    ->update(['sort_order' => $fieldData['sort_order']]);
            }
        });

        return $this->success($version->fields()->orderBy('sort_order')->get()->load('registerField'));
    }

    // --- Rules ---

    public function storeRule(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $ruleType = $request->input('rule_type', 'simple');

        if ($ruleType === 'case_based') {
            $data = $request->validate([
                'name' => 'nullable|string|max:200',
                'description' => 'nullable|string',
                'rule_type' => 'required|in:case_based',
                'trigger_field_id' => 'required|string',
                'cases' => 'required|array|min:1',
                'cases.*.value' => 'required',
                'cases.*.label' => 'nullable|string',
                'cases.*.actions' => 'required|array|min:1',
                'cases.*.priority' => 'nullable|integer',
                'cases.*.compound_condition' => 'nullable|array',
                'default_actions' => 'nullable|array',
                'match_mode' => 'nullable|in:exact,contains,pattern,in',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);

            // Case-based rules don't use condition_logic, but the column is NOT NULL
            $data['condition_logic'] = ['operator' => 'and', 'conditions' => []];
            $data['actions'] = [];
        } else {
            $data = $request->validate([
                'name' => 'nullable|string|max:200',
                'description' => 'nullable|string',
                'condition_logic' => 'required|array',
                'actions' => 'required|array',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);
        }

        $data['workflow_version_id'] = $version->id;
        $rule = WorkflowRule::create($data);
        return $this->success($rule, '', [], 201);
    }

    public function updateRule(Request $request, string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $rule = $version->rules()->where('id', $ruleId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $ruleType = $request->input('rule_type', $rule->rule_type);

        if ($ruleType === 'case_based') {
            $data = $request->validate([
                'name' => 'nullable|string|max:200',
                'description' => 'nullable|string',
                'rule_type' => 'nullable|in:case_based',
                'trigger_field_id' => 'nullable|string',
                'cases' => 'nullable|array|min:1',
                'cases.*.value' => 'required',
                'cases.*.label' => 'nullable|string',
                'cases.*.actions' => 'nullable|array|min:1',
                'cases.*.priority' => 'nullable|integer',
                'cases.*.compound_condition' => 'nullable|array',
                'default_actions' => 'nullable|array',
                'match_mode' => 'nullable|in:exact,contains,pattern,in',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);

            // Ensure condition_logic is set for case-based rules
            if (!isset($data['condition_logic'])) {
                $data['condition_logic'] = $rule->condition_logic ?? ['operator' => 'and', 'conditions' => []];
            }
            if (!isset($data['actions'])) {
                $data['actions'] = $rule->actions ?? [];
            }
        } else {
            $data = $request->validate([
                'name' => 'nullable|string|max:200',
                'description' => 'nullable|string',
                'condition_logic' => 'nullable|array',
                'actions' => 'nullable|array',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);
        }

        $rule->update($data);
        return $this->success($rule->fresh());
    }

    public function destroyRule(string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $rule = $version->rules()->where('id', $ruleId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $rule->delete();
        return $this->success([], 'تم حذف القاعدة');
    }

    public function simulateRule(Request $request, string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $rule = $version->rules()->where('id', $ruleId)->firstOrFail();
        $this->authorize('view', $workflow);

        $data = $request->validate([
            'test_values' => 'required|array',
        ]);

        $branchingEngine = app(\App\Services\ConditionalBranchingEngine::class);
        $result = $branchingEngine->simulate($rule, $data['test_values']);

        return $this->success($result);
    }

    // --- Validation Rules ---

    public function storeValidationRule(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'validation_type' => 'required|in:duplicate_check,exists,not_exists,cross_register_check,dynamic_search,multi_field,register_search,query_builder,sql,field_existence_check',
            'target_register_id' => 'nullable|string|exists:registers,id',
            'trigger_field_id' => 'nullable|string',
            'trigger_conditions' => 'nullable|array',
            'target_fields' => 'nullable|array',
            'query_conditions' => 'nullable|array',
            'sql_query' => 'nullable|string',
            'sql_condition' => 'nullable|string|max:100',
            'route_config' => 'nullable|array',
            'lookup_config' => 'nullable|array',
            'field_effects' => 'nullable|array',
            'response_type' => 'nullable|in:error,warning,confirm',
            'error_message_ar' => 'nullable|string|max:500',
            'error_message_en' => 'nullable|string|max:500',
            'confirm_message_ar' => 'nullable|string|max:500',
            'confirm_message_en' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'rule_config' => 'nullable|array',
            'priority' => 'nullable|integer|min:1|max:10000',
            'category' => 'nullable|string|max:50',
        ]);

        $data['workflow_version_id'] = $version->id;
        $rule = \App\Models\ValidationRule::create($data);
        return $this->success($rule->load('targetRegister'), '', [], 201);
    }

    public function updateValidationRule(Request $request, string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $rule = \App\Models\ValidationRule::where('workflow_version_id', $versionId)->where('id', $ruleId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'validation_type' => 'nullable|in:duplicate_check,exists,not_exists,cross_register_check,dynamic_search,multi_field,register_search,query_builder,sql,field_existence_check',
            'target_register_id' => 'nullable|string|exists:registers,id',
            'trigger_field_id' => 'nullable|string',
            'trigger_conditions' => 'nullable|array',
            'target_fields' => 'nullable|array',
            'query_conditions' => 'nullable|array',
            'sql_query' => 'nullable|string',
            'sql_condition' => 'nullable|string|max:100',
            'route_config' => 'nullable|array',
            'lookup_config' => 'nullable|array',
            'field_effects' => 'nullable|array',
            'response_type' => 'nullable|in:error,warning,confirm',
            'error_message_ar' => 'nullable|string|max:500',
            'error_message_en' => 'nullable|string|max:500',
            'confirm_message_ar' => 'nullable|string|max:500',
            'confirm_message_en' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'rule_config' => 'nullable|array',
            'priority' => 'nullable|integer|min:1|max:10000',
            'category' => 'nullable|string|max:50',
        ]);

        $rule->update($data);
        return $this->success($rule->fresh()->load('targetRegister'));
    }

    public function destroyValidationRule(string $workflowId, string $versionId, string $ruleId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $rule = \App\Models\ValidationRule::where('workflow_version_id', $versionId)->where('id', $ruleId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $rule->delete();
        return $this->success([], 'تم حذف قاعدة التحقق');
    }

    public function reorderValidationRules(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('update', $workflow);

        if (!$version->isDraft()) {
            return $this->error('لا يمكن تعديل نسخة منشورة أو مؤرشفة', 422);
        }

        $data = $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|string',
            'rules.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($version, $data) {
            foreach ($data['rules'] as $ruleData) {
                \App\Models\ValidationRule::where('workflow_version_id', $version->id)
                    ->where('id', $ruleData['id'])
                    ->update(['sort_order' => $ruleData['sort_order']]);
            }
        });

        return $this->success(\App\Models\ValidationRule::where('workflow_version_id', $version->id)->orderBy('sort_order')->get());
    }

    public function simulateValidation(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('view', $workflow);

        $data = $request->validate([
            'test_values' => 'required|array',
        ]);

        $validationEngine = app(\App\Services\ValidationEngine::class);
        $result = $validationEngine->simulate($versionId, $data['test_values']);

        return $this->success($result);
    }

    public function getValidationRules(string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('view', $workflow);

        $rules = \App\Models\ValidationRule::where('workflow_version_id', $versionId)
            ->orderBy('sort_order')
            ->with('targetRegister')
            ->get();

        return $this->success($rules);
    }

    public function simulateEnterprise(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('view', $workflow);

        $data = $request->validate([
            'test_values' => 'required|array',
            'context' => 'nullable|array',
        ]);

        $engine = app(\App\Services\EnterpriseRuleEngine::class);
        $result = $engine->simulate($versionId, $data['test_values'], $data['context'] ?? []);

        return $this->success($result);
    }

    /**
     * Real-time field validation — triggered on field change.
     * Returns routing decisions for field_existence_check rules.
     */
    public function validateField(Request $request, string $workflowId, string $versionId): JsonResponse
    {
        $workflow = Workflow::findOrFail($workflowId);
        $version = $workflow->versions()->where('id', $versionId)->firstOrFail();
        $this->authorize('view', $workflow);

        $data = $request->validate([
            'field_id' => 'required|string',
            'field_value' => 'required',
            'context_values' => 'nullable|array', // Other field values for multi-field lookups
        ]);

        $values = array_merge($data['context_values'] ?? [], [
            $data['field_id'] => $data['field_value'],
        ]);

        $validationEngine = app(\App\Services\ValidationEngine::class);

        // Only run field_existence_check rules for this specific field
        $rules = \App\Models\ValidationRule::where('workflow_version_id', $versionId)
            ->where('validation_type', 'field_existence_check')
            ->where('trigger_field_id', $data['field_id'])
            ->where('is_active', true)
            ->get();

        $results = [];
        $hasRoutingDecision = false;
        $routingDecision = null;

        foreach ($rules as $rule) {
            $result = $validationEngine->runValidation($rule, $values, []);
            $results[] = $result;

            if ($result['status'] === 'found') {
                $hasRoutingDecision = true;
                $routingDecision = $result;
            }
        }

        return $this->success([
            'field_id' => $data['field_id'],
            'field_value' => $data['field_value'],
            'has_routing_decision' => $hasRoutingDecision,
            'routing_decision' => $routingDecision,
            'all_results' => $results,
        ]);
    }
}
