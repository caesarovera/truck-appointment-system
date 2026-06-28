<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenLoaded('roles', fn () => $this->roles->first()?->getAttribute('name')),
            'terminal_id' => $this->terminal_id,
            'terminal' => $this->whenLoaded('terminal', fn () => $this->terminal
                ? ['id' => $this->terminal->id, 'name' => $this->terminal->name]
                : null),
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company
                ? ['id' => $this->company->id, 'name' => $this->company->name]
                : null),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
