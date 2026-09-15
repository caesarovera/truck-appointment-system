<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ResolveQrAppointmentAction;
use App\Http\Resources\V1\AppointmentResource;
use Illuminate\Support\Facades\Gate;

final class VerifyAppointmentQrController
{
    public function __invoke(string $token, ResolveQrAppointmentAction $resolve): AppointmentResource
    {
        $appointment = $resolve->execute($token);

        // Token bukan route-model-binding biasa — id-nya baru diketahui setelah didekode, jadi tak
        // bisa lewat middleware `can:view,appointment`. Ability & isolasi sama dengan
        // ShowAppointmentController.
        Gate::authorize('view', $appointment);

        return AppointmentResource::make($appointment);
    }
}
