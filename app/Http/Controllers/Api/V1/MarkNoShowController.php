<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\MarkNoShowAction;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;

final class MarkNoShowController
{
    public function __invoke(Appointment $appointment, MarkNoShowAction $action): AppointmentResource
    {
        // Otorisasi: middleware can:process,appointment (route) — gate-officer
        // di terminal appointment, sama scope-nya dgn gate-in/out (BUSINESS-FLOW §3.5).
        $marked = $action->execute($appointment);
        $marked->load(['truck', 'driver', 'company', 'slotWindow', 'containers', 'gateIn', 'gateOut']);

        return AppointmentResource::make($marked);
    }
}
