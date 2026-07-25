<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Actions\Fleet\UpdateTruckAction;
use App\Http\Requests\V1\Fleet\UpsertTruckRequest;
use App\Http\Resources\V1\TruckResource;
use App\Models\Truck;

final class UpdateTruckController
{
    public function __invoke(UpsertTruckRequest $request, Truck $truck, UpdateTruckAction $action): TruckResource
    {
        // Kepemilikan (truck.company_id === user.company_id) sudah dijaga di request.
        return TruckResource::make($action->execute($truck, $request->toData()));
    }
}
