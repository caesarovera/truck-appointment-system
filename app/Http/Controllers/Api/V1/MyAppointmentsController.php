<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AppointmentRepositoryInterface;
use App\Http\Requests\V1\MyAppointmentsRequest;
use App\Http\Resources\V1\AppointmentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class MyAppointmentsController
{
    public function __construct(private readonly AppointmentRepositoryInterface $appointments) {}

    public function __invoke(MyAppointmentsRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: appointment.read (FormRequest::authorize).
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        // Daftar ber-scope company → user tanpa company (planner/admin/gate) ditolak.
        $companyId = $user->company_id;
        abort_if($companyId === null, Response::HTTP_FORBIDDEN);

        return AppointmentResource::collection(
            $this->appointments->forCompany($companyId, $request->statusFilter()),
        );
    }
}
