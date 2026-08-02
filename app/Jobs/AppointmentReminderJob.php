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
 * delayed oleh ScheduleAppointmentReminder saat booking DAN reschedule.
 *
 * Job membawa `slotWindowId` yang ia jadwalkan — bukan cuma appointment id —
 * karena dua hal bergantung padanya:
 *  1. `uniqueId()` ikut ber-window. ShouldBeUnique memegang lock selama job masih
 *     PENDING di queue (baru dilepas saat diproses). Kalau kuncinya cuma
 *     appointment id, reminder baru hasil reschedule DIBUANG DIAM-DIAM karena
 *     job lama masih menunggu — dan sopir tak pernah diingatkan sama sekali.
 *  2. Guard basi di handle(). Job yang sudah antre tak bisa ditarik kembali dari
 *     queue, jadi reminder untuk window lama harus membatalkan dirinya sendiri
 *     saat sadar appointment-nya sudah pindah.
 *
 * Saat eksekusi juga cek status terkini: cancel/no-show/selesai → tak ada
 * notifikasi.
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

    public function __construct(
        public readonly int $appointmentId,
        public readonly int $slotWindowId,
    ) {}

    public function uniqueId(): string
    {
        return $this->appointmentId.':'.$this->slotWindowId;
    }

    public function handle(): void
    {
        $appointment = Appointment::query()->with(['driver', 'slotWindow'])->find($this->appointmentId);

        if ($appointment === null) {
            return;
        }

        // Appointment sudah pindah window sejak job ini dijadwalkan (reschedule):
        // reminder ini basi — jamnya jam window lama. Reminder untuk window baru
        // sudah dijadwalkan listener, jadi cukup diam.
        if ($appointment->slot_window_id !== $this->slotWindowId) {
            return;
        }

        // Hanya ingatkan bila masih menunggu kedatangan; cancel/no-show/sudah
        // gate-in → diam.
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
