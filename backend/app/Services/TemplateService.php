<?php

namespace App\Services;

use App\Models\ReceiptTemplate;
use App\Models\TemplateElement;
use Exception;

class TemplateService
{
    /**
     * Create a new template
     */
    public function createTemplate(string $userId, array $data): ReceiptTemplate
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $template = ReceiptTemplate::create($data);

        // Create default elements based on register fields
        if (isset($data['register_id'])) {
            $this->createDefaultElements($template);
        }

        return $template->fresh(['elements.style', 'creator']);
    }

    /**
     * Update template
     */
    public function updateTemplate(string $templateId, array $data, string $userId): ReceiptTemplate
    {
        $template = ReceiptTemplate::findOrFail($templateId);

        $data['updated_by'] = $userId;

        $template->update($data);

        return $template->fresh(['elements.style', 'creator']);
    }

    /**
     * Delete template safely
     */
    public function deleteTemplate(string $templateId): void
    {
        $template = ReceiptTemplate::findOrFail($templateId);

        // Don't allow deleting if it's the default template and others exist
        if ($template->is_default) {
            $otherTemplates = ReceiptTemplate::where('register_id', $template->register_id)
                ->where('id', '!=', $templateId)
                ->count();

            if ($otherTemplates === 0) {
                throw new Exception('لا يمكن حذف القالب الافتراضي الوحيد. قم بإنشاء قالب جديد أولاً أو اجعل قالباً آخر افتراضياً.');
            }
        }

        // Delete elements and styles
        $template->elements()->each(function ($element) {
            $element->style?->delete();
            $element->delete();
        });

        // Delete template
        $template->forceDelete();
    }

    /**
     * Create default elements from register fields
     */
    public function createDefaultElements(ReceiptTemplate $template): void
    {
        $register = $template->register;

        $sortOrder = 0;

        // Add header element
        TemplateElement::create([
            'template_id' => $template->id,
            'element_type' => 'text',
            'label' => 'رأس الوصل',
            'sort_order' => $sortOrder++,
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 20,
            'is_visible' => true,
        ]);

        // Add fields from register
        foreach ($register->fields()->orderBy('sort_order')->get() as $field) {
            TemplateElement::create([
                'template_id' => $template->id,
                'field_id' => $field->id,
                'element_type' => 'field',
                'label' => $field->label_ar,
                'sort_order' => $sortOrder++,
                'x' => 0,
                'y' => $sortOrder * 30,
                'width' => 100,
                'height' => 25,
                'is_visible' => true,
            ]);
        }

        // Add total element if register has financial fields
        $hasFinancialFields = $register->fields()
            ->where('is_financial', true)
            ->exists();

        if ($hasFinancialFields) {
            TemplateElement::create([
                'template_id' => $template->id,
                'element_type' => 'total',
                'label' => 'المجموع',
                'sort_order' => $sortOrder++,
                'x' => 0,
                'y' => $sortOrder * 30,
                'width' => 100,
                'height' => 25,
                'is_visible' => true,
            ]);
        }

        // Add QR code element
        TemplateElement::create([
            'template_id' => $template->id,
            'element_type' => 'qr',
            'label' => 'رمز الاستجابة السريعة',
            'sort_order' => $sortOrder++,
            'x' => 80,
            'y' => 10,
            'width' => 20,
            'height' => 20,
            'is_visible' => true,
        ]);

        // Add signature element
        TemplateElement::create([
            'template_id' => $template->id,
            'element_type' => 'signature',
            'label' => 'التوقيع',
            'sort_order' => $sortOrder++,
            'x' => 0,
            'y' => 250,
            'width' => 40,
            'height' => 20,
            'is_visible' => true,
        ]);

        // Add footer element
        TemplateElement::create([
            'template_id' => $template->id,
            'element_type' => 'text',
            'label' => 'تذييل الوصل',
            'sort_order' => $sortOrder,
            'x' => 0,
            'y' => 280,
            'width' => 100,
            'height' => 15,
            'is_visible' => true,
        ]);
    }

    /**
     * Validate template before rendering
     */
    public function validateTemplate(ReceiptTemplate $template): array
    {
        $issues = [];

        if ($template->elements()->count() === 0) {
            $issues[] = 'القالب لا يحتوي على أي عناصر';
        }

        // Check for overlapping elements
        $elements = $template->elements->toArray();
        for ($i = 0; $i < count($elements); $i++) {
            for ($j = $i + 1; $j < count($elements); $j++) {
                if ($this->elementsOverlap($elements[$i], $elements[$j])) {
                    $issues[] = "العنصرين '{$elements[$i]['label']}' و '{$elements[$j]['label']}' متداخلة";
                }
            }
        }

        return $issues;
    }

    /**
     * Check if two elements overlap
     */
    private function elementsOverlap(array $elem1, array $elem2): bool
    {
        return !(
            $elem1['x'] + $elem1['width'] <= $elem2['x'] ||
            $elem2['x'] + $elem2['width'] <= $elem1['x'] ||
            $elem1['y'] + $elem1['height'] <= $elem2['y'] ||
            $elem2['y'] + $elem2['height'] <= $elem1['y']
        );
    }

    /**
     * Export template as JSON
     */
    public function exportTemplate(ReceiptTemplate $template): string
    {
        return json_encode($template->getTemplateData(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Import template from JSON
     */
    public function importTemplate(string $json, string $registerId, string $userId): ReceiptTemplate
    {
        $data = json_decode($json, true);

        if (!$data) {
            throw new Exception('ملف JSON غير صحيح');
        }

        // Create template
        $template = $this->createTemplate($userId, [
            'register_id' => $registerId,
            'name' => $data['name'] ?? 'القالب المستورد',
            'description' => $data['description'] ?? null,
            'layout_type' => $data['layout_type'] ?? 'portrait',
            'page_width' => $data['page_width'] ?? 210,
            'page_height' => $data['page_height'] ?? 297,
            'background_color' => $data['background_color'] ?? '#FFFFFF',
        ]);

        return $template;
    }
}
