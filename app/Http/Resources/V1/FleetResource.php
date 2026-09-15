<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Truck;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @property array{trucks: Collection<int, Truck>, drivers: Collection<int, User>} $resource */
final class FleetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'trucks' => TruckResource::collection($this->resource['trucks']),
            'drivers' => DriverResource::collection($this->resource['drivers']),
        ];
    }
}
