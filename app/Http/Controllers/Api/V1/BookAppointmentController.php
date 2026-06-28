<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\BookAppointmentAction;
use App\Http\Requests\V1\BookAppointmentRequest;
use App\Http\Resources\V1\AppointmentResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BookAppointmentController
{
    public function __invoke(BookAppointmentRequest $request, BookAppointmentAction $action): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        $appointment = $action->execute($user, $request->toData());
        $appointment->load(['truck', 'driver', 'company', 'slotWindow', 'containers']);

        return AppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
