<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /** @var string[] */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'api_token',
        'secret',
        'credit_card',
        'cvv',
        'otp',
        'pin',
        'private_key',
        'access_token',
        'refresh_token',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer' => $this->whenLoaded('causer', fn() => new UserResource($this->causer)),
            'event' => $this->event,
            'properties' => $this->maskSensitive($this->properties),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Recursively mask sensitive values in audit properties.
     */
    protected function maskSensitive(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $masked = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->sensitiveKeys, true)) {
                $masked[$key] = '••••••••';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitive($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
