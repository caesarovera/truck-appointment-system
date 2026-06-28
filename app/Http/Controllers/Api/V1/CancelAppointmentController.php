<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelAppointmentAction;
use App\Http\Requests\V1\CancelAppointmentRequest;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

final class CancelAppointmentController
{
    public function __invoke(CancelAppointmentRequest $request, Appointment $appointment, CancelAppointmentAction $action): AppointmentResource
    {
        // Otorisasi: middleware can:cancel,appointment (route).
        $cancelled = $action->execute($appointment, $request->expectedVersion());
        $cancelled->load(['truck', 'driver', 'company', 'slotWindow', 'containers']);

        return AppointmentResource::make($cancelled);
    }
}
