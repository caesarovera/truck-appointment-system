<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\SlotWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SlotWindow */
final class SlotUtilizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status->value,
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'remaining' => $this->remaining(),
            // Hitungan per status dari withCount (alias *_count) — whenCounted.
            'completed' => $this->whenCounted('completed'),
            'no_show' => $this->whenCounted('no_show'),
            'cancelled' => $this->whenCounted('cancelled'),
            'active' => $this->whenCounted('active'),
        ];
    }
}
