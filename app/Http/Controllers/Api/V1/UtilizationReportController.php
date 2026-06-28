<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SlotRepositoryInterface;
use App\Http\Requests\V1\UtilizationReportRequest;
use App\Http\Resources\V1\SlotUtilizationResource;
use App\Models\SlotWindow;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UtilizationReportController
{
    public function __construct(private readonly SlotRepositoryInterface $slots) {}

    public function __invoke(UtilizationReportRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: planner/admin (FormRequest::authorize).
        $windows = $this->slots->utilization($request->gateId(), $request->requestedDate());

        $summary = [
            'capacity' => $windows->sum(fn (SlotWindow $w): int => $w->capacity),
            'booked' => $windows->sum(fn (SlotWindow $w): int => $w->booked_count),
            'completed' => $windows->sum(fn (SlotWindow $w): int => (int) $w->getAttribute('completed_count')),
            'no_show' => $windows->sum(fn (SlotWindow $w): int => (int) $w->getAttribute('no_show_count')),
            'cancelled' => $windows->sum(fn (SlotWindow $w): int => (int) $w->getAttribute('cancelled_count')),
            'active' => $windows->sum(fn (SlotWindow $w): int => (int) $w->getAttribute('active_count')),
        ];

        return SlotUtilizationResource::collection($windows)->additional([
            'meta' => [
                'gate_id' => $request->gateId(),
                'date' => $request->requestedDate(),
                'summary' => $summary,
            ],
        ]);
    }
}
