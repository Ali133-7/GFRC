<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'register_id' => $this->register_id,
            'register' => $this->whenLoaded('register', fn() => new RegisterResource($this->register)),
            'created_by' => $this->whenLoaded('creator', fn() => new UserResource($this->creator)),
            'approved_by' => $this->whenLoaded('approver', fn() => new UserResource($this->approver)),
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'version' => $this->version,
            'notes' => $this->notes,
            'qr_payload' => $this->qr_payload,
            'printed_at' => $this->printed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancelled_by' => $this->whenLoaded('canceller', fn() => new UserResource($this->canceller)),
            'cancel_reason' => $this->cancel_reason,
            'items' => $this->whenLoaded('items', fn() => ReceiptItemResource::collection($this->items)),
            'revisions' => $this->whenLoaded('revisions', fn() => ReceiptRevisionResource::collection($this->revisions)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
