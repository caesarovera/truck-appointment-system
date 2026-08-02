<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Events\TruckGatedIn;
use App\Exceptions\GateInWindowException;
use App\Exceptions\InvalidAppointmentStateException;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
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

            $this->assertWithinGateInWindow($locked);

            $this->appointments->recordGateIn($locked, $processedBy);

            return [$locked, true];
        }, attempts: 3);

        if ($changed) {
            TruckGatedIn::dispatch($result);
        }

        return $result;
    }

    /**
     * Truk hanya boleh masuk di sekitar jendelanya (BUSINESS-FLOW §2 & §3.5),
     * toleransinya dari config — bukan hardcode (PRD §4).
     *
     * Dipanggil SETELAH guard idempoten & state: truk yang sudah di dalam tak
     * boleh berubah jadi error hanya karena retry-nya telat, dan status yang
     * salah adalah pelanggaran yang lebih mendasar daripada jam kedatangan.
     *
     * Kenapa harus di Action, bukan cukup mengandalkan NoShowSweepJob: sweep itu
     * eventual (tiap 5 menit) dan diam total kalau worker queue mati — sementara
     * gate-in tetap melayani. Penyaringan di tempat lain bukan penegakan.
     */
    private function assertWithinGateInWindow(Appointment $appointment): void
    {
        $appointment->loadMissing('slotWindow');
        $window = $appointment->slotWindow;

        if ($window === null) {
            return;
        }

        $now = Carbon::now();
        $earliest = $window->startsAt()->subMinutes((int) config('tas.gate_in.early_minutes', 30));
        $latest = $window->endsAt()->addMinutes((int) config('tas.gate_in.late_minutes', 30));

        // Batas inklusif di kedua ujung: truk yang tiba tepat di menit toleransi
        // masih diterima.
        if ($now->lessThan($earliest)) {
            throw GateInWindowException::tooEarly();
        }

        if ($now->greaterThan($latest)) {
            throw GateInWindowException::tooLate();
        }
    }
}
