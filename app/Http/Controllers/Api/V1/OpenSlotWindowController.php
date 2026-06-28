<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\OpenSlotWindowAction;
use App\Http\Requests\V1\OpenSlotWindowRequest;
use App\Http\Resources\V1\SlotWindowResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class OpenSlotWindowController
{
    public function __invoke(OpenSlotWindowRequest $request, OpenSlotWindowAction $action): JsonResponse
    {
        // Otorisasi: slot.manage (FormRequest::authorize).
        $window = $action->execute($request->toData());

        return SlotWindowResource::make($window)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
