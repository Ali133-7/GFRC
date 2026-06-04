<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Receipt;
use App\Models\Register;
use App\Models\ReceiptTemplate;
use App\Models\TemplateElement;
use App\Models\TemplateStyle;
use App\Services\TemplateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends ApiController
{
    public function __construct(private TemplateService $templateService)
    {
    }

    /**
     * Get all templates (with optional register filter)
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage-settings');

        $query = ReceiptTemplate::with(['register', 'creator', 'elements.style']);

        if ($request->has('register_id')) {
            $query->where('register_id', $request->register_id);
        }

        if ($request->has('is_default')) {
            $query->where('is_default', (bool) $request->is_default);
        }

        $templates = $query->paginate($request->per_page ?? 15);

        return $this->success([
            'data' => $templates->items(),
            'pagination' => [
                'total' => $templates->total(),
                'per_page' => $templates->perPage(),
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
            ],
        ], 'تم جلب القوالب بنجاح');
    }

    /**
     * Get template for a specific register
     */
    public function getRegisterTemplate(string $registerId): JsonResponse
    {
        $this->authorize('view-registers');

        $register = Register::findOrFail($registerId);
        $template = $register->getDefaultTemplate();

        return $this->success($template->getTemplateData(), 'تم جلب القالب بنجاح');
    }

    /**
     * Create a new template
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'register_id' => 'required|uuid|exists:registers,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'layout_type' => 'in:portrait,landscape,custom',
            'page_width' => 'nullable|integer|min:100|max:500',
            'page_height' => 'nullable|integer|min:100|max:500',
            'background_color' => 'nullable|regex:/#([a-fA-F0-9]{6})/i',
            'metadata' => 'nullable|array',
        ]);

        try {
            $template = $this->templateService->createTemplate(
                userId: auth()->id(),
                data: $validated
            );

            return $this->success($template->getTemplateData(), 'تم إنشاء القالب بنجاح', [], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get a specific template
     */
    public function show(string $templateId): JsonResponse
    {
        $this->authorize('view-registers');

        $template = ReceiptTemplate::with(['elements.style', 'creator'])->findOrFail($templateId);

        return $this->success($template->getTemplateData(), 'تم جلب القالب بنجاح');
    }

    /**
     * Update a template
     */
    public function update(Request $request, string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'layout_type' => 'in:portrait,landscape,custom',
            'page_width' => 'nullable|integer|min:100|max:500',
            'page_height' => 'nullable|integer|min:100|max:500',
            'background_color' => 'nullable|regex:/#([a-fA-F0-9]{6})/i',
            'metadata' => 'nullable|array',
        ]);

        try {
            $template = $this->templateService->updateTemplate($templateId, $validated, auth()->id());

            return $this->success($template->getTemplateData(), 'تم تحديث القالب بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Delete a template
     */
    public function destroy(string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        try {
            $this->templateService->deleteTemplate($templateId);

            return $this->success(null, 'تم حذف القالب بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Clone a template
     */
    public function clone(Request $request, string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $template = ReceiptTemplate::findOrFail($templateId);
            $clonedTemplate = $template->cloneTemplate($validated['name'], auth()->id());

            return $this->success($clonedTemplate->getTemplateData(), 'تم نسخ القالب بنجاح', [], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Set a template as default for its register
     */
    public function makeDefault(string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        try {
            $template = ReceiptTemplate::findOrFail($templateId);
            $template->makeDefault();

            return $this->success($template->getTemplateData(), 'تم تعيين القالب كافتراضي');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get preview of a template with sample receipt
     */
    public function preview(string $templateId): JsonResponse
    {
        $this->authorize('view-registers');

        try {
            $template = ReceiptTemplate::with('elements.style', 'register.fields')->findOrFail($templateId);
            $register = $template->register;

            // Get a sample receipt or create a mock one
            $receipt = $register->receipts()->first();

            if (!$receipt) {
                return $this->success([
                    'template' => $template->getTemplateData(),
                    'message' => 'لا توجد وصولات في هذا السجل. يتم عرض القالب الفارغ.',
                ], 'معاينة القالب');
            }

            return $this->success([
                'template' => $template->getTemplateData(),
                'receipt' => [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'total_amount' => $receipt->total_amount,
                ],
            ], 'معاينة القالب');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Clear all elements in a template (start from scratch)
     */
    public function clearElements(string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');
        try {
            $template = ReceiptTemplate::findOrFail($templateId);
            $template->elements()->each(function ($element) {
                $element->style?->delete();
                $element->delete();
            });
            return $this->success($template->getTemplateData(), 'تم حذف جميع العناصر وبدء التصميم من الصفر');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get all templates for a specific register
     */
    public function getRegisterTemplates(string $registerId): JsonResponse
    {
        $this->authorize('view-registers');

        try {
            $register = Register::findOrFail($registerId);
            $templates = ReceiptTemplate::where('register_id', $registerId)
                ->with(['creator', 'elements.style'])
                ->get()
                ->map(fn($t) => $t->getTemplateData());

            return $this->success($templates, 'تم جلب قوالب السجل بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
