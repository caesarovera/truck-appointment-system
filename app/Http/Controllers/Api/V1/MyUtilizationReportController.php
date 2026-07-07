<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SlotRepositoryInterface;
use App\Http\Requests\V1\MyUtilizationReportRequest;
use App\Http\Resources\V1\SlotUtilizationResource;
use App\Models\SlotWindow;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Utilisasi company-scoped untuk transporter (pola /me/*): hitungan per status
 * hanya milik company si pemanggil — angka company lain tidak bocor.
 * capacity/booked_count tetap konteks gate global (sudah terbuka via availability).
 */
final class MyUtilizationReportController
{
    public function __construct(private readonly SlotRepositoryInterface $slots) {}

    public function __invoke(MyUtilizationReportRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: report.read (FormRequest::authorize).
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        // Laporan ber-scope company → user tanpa company (planner/admin/gate) ditolak;
        // agregat lintas-company mereka ada di GET /reports/utilization.
        $companyId = $user->company_id;
        abort_if($companyId === null, Response::HTTP_FORBIDDEN);

        $windows = $this->slots->utilization($request->gateId(), $request->requestedDate(), $companyId);

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
                'company_id' => $companyId,
                'summary' => $summary,
            ],
        ]);
    }
}
