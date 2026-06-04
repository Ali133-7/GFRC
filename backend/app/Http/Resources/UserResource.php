<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name])),
            'permissions' => $this->getAllPermissions()->pluck('name')->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
