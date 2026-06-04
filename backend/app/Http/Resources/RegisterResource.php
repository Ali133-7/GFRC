<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'fiscal_year' => $this->fiscal_year,
            'current_sequence' => $this->current_sequence,
            'created_by' => $this->whenLoaded('creator', fn() => new UserResource($this->creator)),
            'fields' => $this->whenLoaded('fields', fn() => RegisterFieldResource::collection($this->fields)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
