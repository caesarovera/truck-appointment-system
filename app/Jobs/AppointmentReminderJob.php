<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Kirim pengingat H-2 jam ke sopir (CLAUDE.md hardening Queue). Dijadwalkan
 * delayed oleh ScheduleAppointmentReminder saat AppointmentBooked.
 *
 * ShouldBeUnique (uniqueId = appointment id) → satu reminder pending per
 * appointment, kebal double-tap booking. Saat eksekusi cek status terkini:
 * kalau sudah cancel/no-show/selesai, tak ada notifikasi (tahan reschedule).
 */
final class AppointmentReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly int $appointmentId) {}

    public function uniqueId(): string
    {
        return (string) $this->appointmentId;
    }

    public function handle(): void
    {
        $appointment = Appointment::query()->with(['driver', 'slotWindow'])->find($this->appointmentId);

        if ($appointment === null) {
            return;
        }

        // Hanya ingatkan bila masih menunggu kedatangan; cancel/reschedule-pergi/
        // no-show/sudah gate-in → diam.
        if (! in_array($appointment->status, [AppointmentStatus::BOOKED, AppointmentStatus::CONFIRMED], true)) {
            return;
        }

        Notification::send($appointment->driver, new AppointmentReminderNotification($appointment));
    }

    public function failed(Throwable $e): void
    {
        // Hook alerting saat reminder gagal permanen (di-wire saat observability slice).
    }
}
