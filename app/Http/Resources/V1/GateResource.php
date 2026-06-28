<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Gate */
final class GateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terminal_id' => $this->terminal_id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
