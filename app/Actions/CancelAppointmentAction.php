<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\Events\AppointmentCancelled;
use App\Exceptions\InvalidAppointmentStateException;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Batalkan appointment sebelum truk tiba (BUSINESS-FLOW §2/§3.3).
 * Kuota window dikembalikan dalam transaksi ber-lock; container dilepas.
 */
final class CancelAppointmentAction
{
    public function __construct(
        private readonly SlotRepositoryInterface $slots,
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    public function execute(Appointment $appointment): Appointment
    {
        $result = DB::transaction(function () use ($appointment): Appointment {
            // Kunci baris appointment → cek status mutakhir & cegah balapan dgn gate-in.
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isCancellable()) {
                throw InvalidAppointmentStateException::cannotCancel();
            }

            $window = $this->slots->lockForUpdate($locked->slot_window_id);

            $this->appointments->markCancelled($locked);

            if ($window !== null) {
                $this->slots->decrementBooked($window);
            }

            return $locked;
        }, attempts: 3);

        AppointmentCancelled::dispatch($result, $result->slot_window_id);

        return $result;
    }
}
