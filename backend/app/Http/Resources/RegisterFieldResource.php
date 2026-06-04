<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'register_id' => $this->register_id,
            'name' => $this->name,
            'label_ar' => $this->label_ar,
            'label_en' => $this->label_en,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'is_visible' => $this->is_visible,
            'is_financial' => $this->is_financial,
            'sort_order' => $this->sort_order,
            'validation_rules' => $this->validation_rules,
            'default_value' => $this->default_value,
            'options' => $this->options,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
