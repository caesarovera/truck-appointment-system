<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

final class ShowAppointmentController
{
    public function __invoke(Appointment $appointment): AppointmentResource
    {
        // Otorisasi per-record ditegakkan middleware `can:view,appointment` di route.
        $appointment->load(['truck', 'driver', 'company', 'slotWindow', 'containers']);

        return AppointmentResource::make($appointment);
    }
}
