<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            // Role admin: kunci di UI (immutable), tapi tetap tampil apa adanya
            // supaya admin bisa lihat cakupan penuhnya.
            'immutable' => $this->name === 'admin',
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('name')->values(),
            ),
        ];
    }
}
