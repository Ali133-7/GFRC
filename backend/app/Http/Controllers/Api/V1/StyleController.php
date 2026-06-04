<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TemplateElement;
use App\Models\TemplateStyle;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StyleController extends ApiController
{
    /**
     * Get default styles
     */
    public function defaults(): JsonResponse
    {
        $defaultStyles = [
            'font_family' => 'Arial',
            'font_size' => 12,
            'font_weight' => 'normal',
            'font_color' => '#000000',
            'background_color' => null,
            'border_color' => null,
            'border_width' => 0,
            'text_align' => 'right',
            'padding_top' => 0,
            'padding_right' => 0,
            'padding_bottom' => 0,
            'padding_left' => 0,
            'opacity' => 1.00,
            'display' => 'block',
            'line_height' => 1,
            'letter_spacing' => null,
        ];

        return $this->success($defaultStyles, 'الأنماط الافتراضية');
    }

    /**
     * Get element style
     */
    public function show(string $elementId): JsonResponse
    {
        try {
            $element = TemplateElement::with('style')->findOrFail($elementId);

            if (!$element->style) {
                return $this->error('لا توجد أنماط للعنصر', 404);
            }

            return $this->success($element->style->getStyleData(), 'تم جلب الأنماط بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Update element style
     */
    public function update(Request $request, string $elementId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'font_family' => 'string|max:100',
            'font_size' => 'integer|min:6|max:72',
            'font_weight' => 'in:normal,bold,100,300,400,600,700,900',
            'font_color' => 'regex:/#([a-fA-F0-9]{6})/i',
            'background_color' => 'nullable|regex:/#([a-fA-F0-9]{6})/i',
            'border_color' => 'nullable|regex:/#([a-fA-F0-9]{6})/i',
            'border_width' => 'integer|min:0|max:10',
            'text_align' => 'in:left,center,right',
            'padding_top' => 'integer|min:0|max:100',
            'padding_right' => 'integer|min:0|max:100',
            'padding_bottom' => 'integer|min:0|max:100',
            'padding_left' => 'integer|min:0|max:100',
            'opacity' => 'numeric|min:0|max:1',
            'display' => 'in:block,inline,none',
            'line_height' => 'numeric|min:0.5|max:3',
            'letter_spacing' => 'nullable|string|max:10',
        ]);

        try {
            $element = TemplateElement::findOrFail($elementId);

            // Get or create style
            $style = $element->style ?? new TemplateStyle(['element_id' => $elementId]);
            $style->updateStyles($validated);

            if (!$element->style) {
                $element->save();
            }

            return $this->success($style->getStyleData(), 'تم تحديث الأنماط بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Apply preset style template
     */
    public function applyPreset(Request $request, string $elementId): JsonResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'preset' => 'required|in:header,title,body,footer,label,value,currency',
        ]);

        $presets = [
            'header' => [
                'font_family' => 'Arial',
                'font_size' => 20,
                'font_weight' => '700',
                'font_color' => '#000000',
                'text_align' => 'center',
                'padding_top' => 5,
                'padding_bottom' => 10,
            ],
            'title' => [
                'font_family' => 'Arial',
                'font_size' => 16,
                'font_weight' => '600',
                'font_color' => '#333333',
                'text_align' => 'center',
                'padding_bottom' => 5,
            ],
            'body' => [
                'font_family' => 'Arial',
                'font_size' => 12,
                'font_weight' => 'normal',
                'font_color' => '#000000',
                'text_align' => 'right',
                'padding_top' => 2,
                'padding_bottom' => 2,
            ],
            'footer' => [
                'font_family' => 'Arial',
                'font_size' => 10,
                'font_weight' => 'normal',
                'font_color' => '#666666',
                'text_align' => 'center',
                'padding_top' => 10,
            ],
            'label' => [
                'font_family' => 'Arial',
                'font_size' => 11,
                'font_weight' => '600',
                'font_color' => '#333333',
                'text_align' => 'right',
            ],
            'value' => [
                'font_family' => 'Arial',
                'font_size' => 12,
                'font_weight' => 'normal',
                'font_color' => '#000000',
                'text_align' => 'right',
            ],
            'currency' => [
                'font_family' => 'Arial',
                'font_size' => 13,
                'font_weight' => '600',
                'font_color' => '#008000',
                'text_align' => 'right',
            ],
        ];

        try {
            $element = TemplateElement::findOrFail($elementId);

            $preset = $presets[$validated['preset']];

            $style = $element->style ?? new TemplateStyle(['element_id' => $elementId]);
            $style->updateStyles($preset);

            if (!$element->style) {
                $element->save();
            }

            return $this->success($style->getStyleData(), 'تم تطبيق النمط المسبق بنجاح');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
