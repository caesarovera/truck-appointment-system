<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AuditRepositoryInterface;
use App\Http\Requests\V1\AppointmentAuditRequest;
use App\Http\Resources\V1\ActivityResource;
use App\Models\Appointment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AppointmentAuditController
{
    public function __construct(
        private readonly AuditRepositoryInterface $audit,
    ) {}

    public function __invoke(AppointmentAuditRequest $request, Appointment $appointment): AnonymousResourceCollection
    {
        // Isolasi per-record dari middleware `can:view,appointment` (transporter
        // company sendiri, planner/admin lintas company); permission audit.read
        // dari AppointmentAuditRequest::authorize.
        return ActivityResource::collection($this->audit->forAppointment($appointment));
    }
}
