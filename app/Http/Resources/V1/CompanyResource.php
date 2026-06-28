<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TransportCompany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransportCompany */
final class CompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'users_count' => $this->whenCounted('users'),
            'trucks_count' => $this->whenCounted('trucks'),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
