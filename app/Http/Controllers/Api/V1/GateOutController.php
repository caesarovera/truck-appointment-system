<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\GateOutAction;
use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GateOutController
{
    public function __invoke(Request $request, Appointment $appointment, GateOutAction $action): AppointmentResource
    {
        // Otorisasi: middleware can:process,appointment (route).
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        $gatedOut = $action->execute($appointment, $user->id);
        $gatedOut->load(['truck', 'driver', 'company', 'slotWindow', 'containers', 'gateIn', 'gateOut']);

        return AppointmentResource::make($gatedOut);
    }
}
