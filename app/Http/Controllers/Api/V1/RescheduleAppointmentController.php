<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\RescheduleAppointmentAction;
use App\Http\Requests\V1\RescheduleAppointmentRequest;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

final class RescheduleAppointmentController
{
    public function __invoke(
        RescheduleAppointmentRequest $request,
        Appointment $appointment,
        RescheduleAppointmentAction $action,
    ): AppointmentResource {
        // Otorisasi: middleware can:update,appointment (route).
        $rescheduled = $action->execute($appointment, $request->toData());
        $rescheduled->load(['truck', 'driver', 'company', 'slotWindow', 'containers']);

        return AppointmentResource::make($rescheduled);
    }
}
