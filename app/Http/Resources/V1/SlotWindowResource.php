<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\SlotWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SlotWindow */
final class SlotWindowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gate_id' => $this->gate_id,
            'date' => $this->date->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'remaining' => $this->remaining(),
            'status' => $this->status->value,
            // Hanya muncul bila relasi gate di-eager-load (mis. jadwal driver) —
            // driver perlu tahu gate tujuan. Tak menambah query di endpoint lain.
            'gate' => $this->whenLoaded('gate', fn () => GateResource::make($this->gate)),
        ];
    }
}
