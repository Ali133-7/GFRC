<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Register;
use App\Models\RegisterField;
use App\Models\TransactionTemplate;
use App\Models\TransactionTemplateField;
use App\Models\TemplateRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionTemplateController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view', TransactionTemplate::class);
        $query = TransactionTemplate::with('register')->withCount('fields');

        if ($request->filled('register_id')) {
            $query->where('register_id', $request->register_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $templates = $query->orderBy('sort_order')->orderBy('name_ar')->paginate($request->input('per_page', 25));
        return $this->success($templates->items(), '', $this->paginationMeta($templates));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage', TransactionTemplate::class);
        $data = $request->validate([
            'register_id' => 'required|string|exists:registers,id',
            'name_ar' => 'required|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'sections' => 'nullable|array',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'fields' => 'nullable|array',
            'fields.*.register_field_id' => 'required_with:fields|string|exists:register_fields,id',
            'fields.*.label_override' => 'nullable|string',
            'fields.*.placeholder' => 'nullable|string',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.is_visible' => 'boolean',
            'fields.*.is_readonly' => 'boolean',
            'fields.*.sort_order' => 'integer',
            'rules' => 'nullable|array',
            'rules.*.name' => 'nullable|string',
            'rules.*.trigger_field_id' => 'required_with:rules|string|exists:register_fields,id',
            'rules.*.trigger_operator' => 'required_with:rules|string|in:equals,not_equals,contains,gt,lt',
            'rules.*.trigger_value' => 'required_with:rules|string',
            'rules.*.target_field_id' => 'required_with:rules|string|exists:register_fields,id',
            'rules.*.action' => 'required_with:rules|string|in:set_value,set_amount,hide,show',
            'rules.*.action_value' => 'nullable|string',
            'rules.*.sort_order' => 'integer',
        ]);

        return DB::transaction(function () use ($data) {
            $templateData = collect($data)->except(['fields', 'rules'])->toArray();
            $templateData['id'] = (string) Str::uuid();
            $template = TransactionTemplate::create($templateData);

            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $field) {
                    $field['id'] = (string) Str::uuid();
                    $field['template_id'] = $template->id;
                    TransactionTemplateField::create($field);
                }
            }

            if (!empty($data['rules'])) {
                foreach ($data['rules'] as $rule) {
                    $rule['id'] = (string) Str::uuid();
                    $rule['template_id'] = $template->id;
                    TemplateRule::create($rule);
                }
            }

            return $this->success($this->loadTemplate($template), 'تم إنشاء القالب بنجاح');
        });
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('view', TransactionTemplate::class);
        $template = $this->loadTemplate(TransactionTemplate::findOrFail($id));
        return $this->success($template);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', TransactionTemplate::class);
        $template = TransactionTemplate::findOrFail($id);
        $data = $request->validate([
            'name_ar' => 'sometimes|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'sections' => 'nullable|array',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'fields' => 'nullable|array',
            'fields.*.id' => 'nullable|string',
            'fields.*.register_field_id' => 'required_with:fields|string|exists:register_fields,id',
            'fields.*.label_override' => 'nullable|string',
            'fields.*.placeholder' => 'nullable|string',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.is_visible' => 'boolean',
            'fields.*.is_readonly' => 'boolean',
            'fields.*.sort_order' => 'integer',
            'rules' => 'nullable|array',
            'rules.*.id' => 'nullable|string',
            'rules.*.name' => 'nullable|string',
            'rules.*.trigger_field_id' => 'required_with:rules|string|exists:register_fields,id',
            'rules.*.trigger_operator' => 'required_with:rules|string|in:equals,not_equals,contains,gt,lt',
            'rules.*.trigger_value' => 'required_with:rules|string',
            'rules.*.target_field_id' => 'required_with:rules|string|exists:register_fields,id',
            'rules.*.action' => 'required_with:rules|string|in:set_value,set_amount,hide,show',
            'rules.*.action_value' => 'nullable|string',
            'rules.*.sort_order' => 'integer',
        ]);

        return DB::transaction(function () use ($template, $data) {
            $templateData = collect($data)->except(['fields', 'rules'])->toArray();
            if (!empty($templateData)) {
                $template->update($templateData);
            }

            if (array_key_exists('fields', $data)) {
                $existingIds = collect($data['fields'])->pluck('id')->filter()->values()->all();
                TransactionTemplateField::where('template_id', $template->id)
                    ->whereNotIn('id', $existingIds)->delete();

                foreach ($data['fields'] as $field) {
                    if (!empty($field['id'])) {
                        TransactionTemplateField::where('id', $field['id'])->where('template_id', $template->id)->update(
                            collect($field)->except('id')->toArray()
                        );
                    } else {
                        $field['id'] = (string) Str::uuid();
                        $field['template_id'] = $template->id;
                        TransactionTemplateField::create($field);
                    }
                }
            }

            if (array_key_exists('rules', $data)) {
                $existingIds = collect($data['rules'])->pluck('id')->filter()->values()->all();
                TemplateRule::where('template_id', $template->id)
                    ->whereNotIn('id', $existingIds)->delete();

                foreach ($data['rules'] as $rule) {
                    if (!empty($rule['id'])) {
                        TemplateRule::where('id', $rule['id'])->where('template_id', $template->id)->update(
                            collect($rule)->except('id')->toArray()
                        );
                    } else {
                        $rule['id'] = (string) Str::uuid();
                        $rule['template_id'] = $template->id;
                        TemplateRule::create($rule);
                    }
                }
            }

            return $this->success($this->loadTemplate($template), 'تم تحديث القالب بنجاح');
        });
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('manage', TransactionTemplate::class);
        $template = TransactionTemplate::findOrFail($id);
        $template->delete();
        return $this->success([], 'تم حذف القالب بنجاح');
    }

    public function clone(string $id): JsonResponse
    {
        $this->authorize('manage', TransactionTemplate::class);
        $original = TransactionTemplate::with(['fields', 'rules'])->findOrFail($id);

        return DB::transaction(function () use ($original) {
            $newTemplate = $original->replicate();
            $newTemplate->id = (string) Str::uuid();
            $newTemplate->name_ar = $original->name_ar . ' (نسخة)';
            $newTemplate->name_en = $original->name_en ? $original->name_en . ' (Copy)' : null;
            $newTemplate->is_default = false;
            $newTemplate->usage_count = 0;
            $newTemplate->save();

            foreach ($original->fields as $field) {
                $newField = $field->replicate();
                $newField->id = (string) Str::uuid();
                $newField->template_id = $newTemplate->id;
                $newField->save();
            }

            foreach ($original->rules as $rule) {
                $newRule = $rule->replicate();
                $newRule->id = (string) Str::uuid();
                $newRule->template_id = $newTemplate->id;
                $newRule->save();
            }

            return $this->success($this->loadTemplate($newTemplate), 'تم نسخ القالب بنجاح');
        });
    }

    public function toggle(string $id): JsonResponse
    {
        $this->authorize('manage', TransactionTemplate::class);
        $template = TransactionTemplate::findOrFail($id);
        $template->update(['is_active' => !$template->is_active]);
        return $this->success($template, 'تم تغيير حالة القالب');
    }

    public function byRegister(string $registerId): JsonResponse
    {
        $this->authorize('view', TransactionTemplate::class);
        $register = Register::findOrFail($registerId);
        $templates = TransactionTemplate::with(['fields.registerField', 'rules'])
            ->where('register_id', $register->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get();

        return $this->success($templates);
    }

    public function preview(string $id): JsonResponse
    {
        $this->authorize('view', TransactionTemplate::class);
        $template = $this->loadTemplate(TransactionTemplate::findOrFail($id));
        $register = Register::with('fields')->findOrFail($template['register_id']);

        return $this->success([
            'template' => $template,
            'register_fields' => $register->fields,
        ]);
    }

    protected function loadTemplate(TransactionTemplate $template): array
    {
        return $template->load(['fields.registerField', 'rules.triggerField', 'rules.targetField', 'register'])->toArray();
    }
}
