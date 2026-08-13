<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AppointmentRepositoryInterface;
use App\Http\Requests\V1\GateHistoryReportRequest;
use App\Http\Resources\V1\AppointmentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GateHistoryReportController
{
    public function __construct(private readonly AppointmentRepositoryInterface $appointments) {}

    public function __invoke(GateHistoryReportRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: admin/planner (FormRequest::authorize).
        return AppointmentResource::collection(
            $this->appointments->gateHistoryForGate($request->gateId(), $request->requestedDate()),
        )->additional([
            'meta' => [
                'gate_id' => $request->gateId(),
                'date' => $request->requestedDate(),
            ],
        ]);
    }
}
