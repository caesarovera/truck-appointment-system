<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CreateGateAction;
use App\Http\Requests\V1\Admin\UpsertGateRequest;
use App\Http\Resources\V1\GateResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateGateController
{
    public function __invoke(UpsertGateRequest $request, CreateGateAction $action): JsonResponse
    {
        $gate = $action->execute($request->toData());

        return GateResource::make($gate)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
