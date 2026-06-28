<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelAppointmentAction;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

final class CancelAppointmentController
{
    public function __invoke(Appointment $appointment, CancelAppointmentAction $action): AppointmentResource
    {
        // Otorisasi: middleware can:cancel,appointment (route).
        $cancelled = $action->execute($appointment);
        $cancelled->load(['truck', 'driver', 'company', 'slotWindow', 'containers']);

        return AppointmentResource::make($cancelled);
    }
}
