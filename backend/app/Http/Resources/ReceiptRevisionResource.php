<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_id' => $this->receipt_id,
            'version' => $this->version,
            'revised_by' => $this->revised_by,
            'reviser' => $this->whenLoaded('reviser', fn() => new UserResource($this->reviser)),
            'reason' => $this->reason,
            'old_snapshot' => $this->old_snapshot,
            'new_snapshot' => $this->new_snapshot,
            'created_at' => $this->created_at,
        ];
    }
}
