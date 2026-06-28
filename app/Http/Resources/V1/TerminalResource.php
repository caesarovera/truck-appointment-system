<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Terminal */
final class TerminalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'gates_count' => $this->whenCounted('gates'),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
