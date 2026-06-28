<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CreateTerminalAction;
use App\Http\Requests\V1\Admin\UpsertTerminalRequest;
use App\Http\Resources\V1\TerminalResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateTerminalController
{
    public function __invoke(UpsertTerminalRequest $request, CreateTerminalAction $action): JsonResponse
    {
        $terminal = $action->execute($request->toData());

        return TerminalResource::make($terminal)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
