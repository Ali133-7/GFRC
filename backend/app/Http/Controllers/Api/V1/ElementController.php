<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ReceiptTemplate;
use App\Models\TemplateElement;
use App\Models\TemplateStyle;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElementController extends ApiController
{
    /**
     * Add element to template
     */
    public function store(Request $request, string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'field_id' => 'nullable|uuid|exists:register_fields,id',
            'element_type' => 'required|in:field,text,divider,qr,signature,total,image,spacer',
            'label' => 'nullable|string|max:255',
            'x' => 'required|integer|min:0',
            'y' => 'required|integer|min:0',
            'width' => 'required|integer|min:10',
            'height' => 'required|integer|min:10',
            'is_visible' => 'boolean',
            'metadata' => 'nullable|array',
            'style' => 'nullable|array',
        ]);

        try {
            $template = ReceiptTemplate::findOrFail($templateId);

            // Get next sort order
            $maxSort = $template->elements()->max('sort_order') ?? 0;
            $validated['sort_order'] = $maxSort + 1;
            $validated['template_id'] = $templateId;

            $element = TemplateElement::create($validated);

            // Create default style if provided
            $styleInput = $request->input('style');
            if (is_array($styleInput) && !empty($styleInput)) {
                $styleData = array_merge(
                    $styleInput,
                    ['element_id' => $element->id]
                );
                TemplateStyle::create($styleData);
                $element->load('style');
            }

            return $this->success($element->getElementData(), 'تم إضافة العنصر بنجاح', [], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Update element
     */
    public function update(Request $request, string $templateId, string $elementId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'x' => 'integer|min:0',
            'y' => 'integer|min:0',
            'width' => 'integer|min:10',
            'height' => 'integer|min:10',
            'is_visible' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        try {
            $element = TemplateElement::where('template_id', $templateId)
                ->findOrFail($elementId);

            $element->update($validated);

            return $this->success($element->getElementData(), 'تم تحديث العنصر بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Delete element
     */
    public function destroy(string $templateId, string $elementId): JsonResponse
    {
        $this->authorize('manage-settings');

        try {
            $element = TemplateElement::where('template_id', $templateId)
                ->findOrFail($elementId);

            // Delete associated style
            $element->style?->delete();

            // Delete element
            $element->delete();

            return $this->success(null, 'تم حذف العنصر بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Reorder elements
     */
    public function reorder(Request $request, string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'element_order' => 'required|array',
            'element_order.*' => 'uuid|exists:template_elements,id',
        ]);

        try {
            foreach ($validated['element_order'] as $index => $elementId) {
                TemplateElement::where('template_id', $templateId)
                    ->findOrFail($elementId)
                    ->update(['sort_order' => $index]);
            }

            $template = ReceiptTemplate::with('elements.style')->findOrFail($templateId);

            return $this->success($template->getTemplateData(), 'تم إعادة ترتيب العناصر بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Bulk update element positions and sizes
     */
    public function bulkUpdate(Request $request, string $templateId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.element_id' => 'required|uuid|exists:template_elements,id',
            'updates.*.x' => 'integer|min:0',
            'updates.*.y' => 'integer|min:0',
            'updates.*.width' => 'integer|min:10',
            'updates.*.height' => 'integer|min:10',
        ]);

        try {
            foreach ($validated['updates'] as $update) {
                $elementId = $update['element_id'];
                $data = array_diff_key($update, ['element_id' => null]);

                TemplateElement::where('template_id', $templateId)
                    ->findOrFail($elementId)
                    ->update($data);
            }

            $template = ReceiptTemplate::with('elements.style')->findOrFail($templateId);

            return $this->success($template->getTemplateData(), 'تم تحديث العناصر بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
