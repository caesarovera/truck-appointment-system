<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Events\TruckGatedIn;
use App\Exceptions\InvalidAppointmentStateException;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Gate-in: truk tiba & masuk terminal (BUSINESS-FLOW §3.5).
 *
 * CONFIRMED → ARRIVED → IN_PROGRESS (MVP satu aksi). Catat gate_transactions
 * (type=IN). Idempoten: row-lock + bila sudah gated-in, kembalikan apa adanya
 * tanpa transaksi ganda. Efek eksternal (TOS) lewat event TruckGatedIn.
 */
final class GateInAction
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    public function execute(Appointment $appointment, int $processedBy): Appointment
    {
        [$result, $changed] = DB::transaction(function () use ($appointment, $processedBy): array {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            // Double-tap / retry: sudah masuk → idempotent, tak ada transaksi baru.
            if ($locked->isGatedIn()) {
                return [$locked, false];
            }

            if (! $locked->status->canGateIn()) {
                throw InvalidAppointmentStateException::cannotGateIn();
            }

            $this->appointments->recordGateIn($locked, $processedBy);

            return [$locked, true];
        }, attempts: 3);

        if ($changed) {
            TruckGatedIn::dispatch($result);
        }

        return $result;
    }
}
