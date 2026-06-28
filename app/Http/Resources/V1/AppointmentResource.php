<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
final class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'status' => $this->status->value,
            'move_type' => $this->move_type->value,
            'version' => $this->version,
            'company_id' => $this->company_id,
            // Relasi hanya muncul bila di-eager-load (cegah N+1; pakai callback
            // agar tidak diakses saat belum dimuat).
            'slot_window' => $this->whenLoaded('slotWindow', fn () => SlotWindowResource::make($this->slotWindow)),
            'truck' => $this->whenLoaded('truck', fn () => TruckResource::make($this->truck)),
            'driver' => $this->whenLoaded('driver', fn () => DriverResource::make($this->driver)),
            'containers' => ContainerResource::collection($this->whenLoaded('containers')),
            // Jejak gate (muncul setelah gate-in/out di-eager-load).
            'gate_in_at' => $this->whenLoaded('gateIn', fn () => $this->gateIn?->processed_at),
            'gate_out_at' => $this->whenLoaded('gateOut', fn () => $this->gateOut?->processed_at),
            'created_at' => $this->created_at,
        ];
    }
}
