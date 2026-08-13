<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentQrTokenService;
use Illuminate\Support\Facades\Gate;

final class VerifyAppointmentQrController
{
    public function __invoke(string $token, AppointmentQrTokenService $tokens): AppointmentResource
    {
        $appointmentId = $tokens->verify($token);
        $appointment = Appointment::findOrFail($appointmentId);

        // Token bukan route-model-binding biasa — id-nya baru diketahui setelah
        // didekode, jadi tak bisa lewat middleware `can:view,appointment` di
        // route. Ini satu-satunya tempat di proyek ini yang mengecek Policy
        // manual; ability & aturan isolasinya sama persis dengan
        // ShowAppointmentController.
        Gate::authorize('view', $appointment);

        $appointment->load(['truck', 'driver', 'company', 'slotWindow', 'containers']);

        return AppointmentResource::make($appointment);
    }
}
