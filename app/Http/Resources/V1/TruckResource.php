<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Truck */
final class TruckResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_no' => $this->plate_no,
            'status' => $this->status->value,
        ];
    }
}
