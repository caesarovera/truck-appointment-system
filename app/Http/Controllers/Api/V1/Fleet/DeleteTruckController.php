<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Actions\Fleet\DeleteTruckAction;
use App\Http\Requests\V1\Fleet\DeleteTruckRequest;
use App\Models\Truck;
use Illuminate\Http\Response;

final class DeleteTruckController
{
    public function __invoke(DeleteTruckRequest $request, Truck $truck, DeleteTruckAction $action): Response
    {
        // Guard "truk masih dipakai appointment" (409) ada di repository.
        $action->execute($truck);

        return response()->noContent();
    }
}
