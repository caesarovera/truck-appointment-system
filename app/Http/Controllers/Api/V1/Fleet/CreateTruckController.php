<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Actions\Fleet\CreateTruckAction;
use App\Http\Requests\V1\Fleet\UpsertTruckRequest;
use App\Http\Resources\V1\TruckResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateTruckController
{
    public function __invoke(UpsertTruckRequest $request, CreateTruckAction $action): JsonResponse
    {
        // company_id dari user login (bukan input) — armada selalu milik sendiri.
        $companyId = (int) $request->user()?->company_id;

        $truck = $action->execute($companyId, $request->toData());

        return TruckResource::make($truck)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
