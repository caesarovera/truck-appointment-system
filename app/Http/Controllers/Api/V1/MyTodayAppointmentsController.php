<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AppointmentRepositoryInterface;
use App\Http\Requests\V1\TodayAppointmentsRequest;
use App\Http\Resources\V1\AppointmentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class MyTodayAppointmentsController
{
    public function __construct(private readonly AppointmentRepositoryInterface $appointments) {}

    public function __invoke(TodayAppointmentsRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: scope appointment.read.self (FormRequest::authorize).
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        $appointments = $this->appointments->todayForDriver($user->id, now()->toDateString());

        return AppointmentResource::collection($appointments);
    }
}
