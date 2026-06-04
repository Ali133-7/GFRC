<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_id' => $this->receipt_id,
            'field_id' => $this->field_id,
            'field_name_snapshot' => $this->field_name_snapshot,
            'label_ar_snapshot' => $this->label_ar_snapshot,
            'amount' => $this->amount,
            'text_value' => $this->text_value,
            'created_at' => $this->created_at,
        ];
    }
}
