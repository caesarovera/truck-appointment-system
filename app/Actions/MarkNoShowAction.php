<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\Events\AppointmentNoShow;
use App\Exceptions\InvalidAppointmentStateException;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Tandai appointment NO_SHOW (truk tak datang sampai window.end + grace).
 * Kuota window dikembalikan dalam transaksi ber-lock; container dilepas.
 * Dipanggil per appointment oleh NoShowSweepJob (BUSINESS-FLOW §2/§3.5).
 */
final class MarkNoShowAction
{
    public function __construct(
        private readonly SlotRepositoryInterface $slots,
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    public function execute(Appointment $appointment): Appointment
    {
        $result = DB::transaction(function () use ($appointment): Appointment {
            // Kunci baris → cek status mutakhir & cegah balapan dgn gate-in/cancel.
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->canMarkNoShow()) {
                throw InvalidAppointmentStateException::cannotMarkNoShow();
            }

            $window = $this->slots->lockForUpdate($locked->slot_window_id);

            $this->appointments->markNoShow($locked);

            if ($window !== null) {
                $this->slots->decrementBooked($window);
            }

            return $locked;
        }, attempts: 3);

        AppointmentNoShow::dispatch($result, $result->slot_window_id);

        return $result;
    }
}
