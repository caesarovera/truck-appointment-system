<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Enums\AppointmentStatus;
use App\Events\TruckGatedOut;
use App\Exceptions\InvalidAppointmentStateException;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Gate-out: bongkar/muat selesai, truk keluar (BUSINESS-FLOW §3.6).
 *
 * IN_PROGRESS → COMPLETED. Catat gate_transactions (type=OUT). Idempoten:
 * row-lock + bila sudah COMPLETED, kembalikan apa adanya. Efek eksternal (TOS)
 * lewat event TruckGatedOut.
 */
final class GateOutAction
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    public function execute(Appointment $appointment, int $processedBy): Appointment
    {
        [$result, $changed] = DB::transaction(function () use ($appointment, $processedBy): array {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            // Double-tap / retry: sudah selesai → idempotent, tak ada transaksi baru.
            if ($locked->status === AppointmentStatus::COMPLETED) {
                return [$locked, false];
            }

            if (! $locked->status->canGateOut()) {
                throw InvalidAppointmentStateException::cannotGateOut();
            }

            $this->appointments->recordGateOut($locked, $processedBy);

            return [$locked, true];
        }, attempts: 3);

        if ($changed) {
            TruckGatedOut::dispatch($result);
        }

        return $result;
    }
}
