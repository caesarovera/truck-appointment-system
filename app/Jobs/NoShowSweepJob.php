<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\MarkNoShowAction;
use App\Contracts\AppointmentRepositoryInterface;
use App\Exceptions\InvalidAppointmentStateException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sapu appointment yang lewat window.end + grace tapi truk tak datang →
 * tandai NO_SHOW & kembalikan kuota (BUSINESS-FLOW §3.5). Dijadwalkan tiap 5
 * menit (routes/console.php). WithoutOverlapping supaya dua sweep tak tumpang
 * tindih; per-appointment idempotensi & lock dipegang MarkNoShowAction.
 */
final class NoShowSweepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('no-show-sweep'))->dontRelease()];
    }

    public function handle(AppointmentRepositoryInterface $appointments, MarkNoShowAction $action): void
    {
        $graceMinutes = (int) config('tas.no_show_grace_minutes', 30);

        foreach ($appointments->dueForNoShow(Carbon::now(), $graceMinutes) as $appointment) {
            try {
                $action->execute($appointment);
            } catch (InvalidAppointmentStateException) {
                // Balapan: appointment keburu gate-in/cancel di antara query & lock.
                // Lewati dengan aman — bukan kondisi error.
            }
        }
    }

    public function failed(Throwable $e): void
    {
        // Hook alerting saat sweep gagal permanen (di-wire saat observability slice).
    }
}
