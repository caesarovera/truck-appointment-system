<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Jobs\AppointmentReminderJob;
use Illuminate\Support\Carbon;

/**
 * Jadwalkan AppointmentReminderJob H-(reminder_lead) sebelum window mulai.
 * Bila lead-time sudah lewat (booking mepet), reminder dikirim segera. Job
 * ber-ShouldBeUnique per appointment → aman dari double-tap booking.
 */
final class ScheduleAppointmentReminder
{
    public function handle(AppointmentBooked $event): void
    {
        $appointment = $event->appointment;
        $appointment->loadMissing('slotWindow');
        $window = $appointment->slotWindow;

        if ($window === null) {
            return;
        }

        $leadMinutes = (int) config('tas.reminder_lead_minutes', 120);
        $remindAt = $window->date->copy()
            ->setTimeFromTimeString($window->start_time)
            ->subMinutes($leadMinutes);

        AppointmentReminderJob::dispatch($appointment->id)
            ->delay($remindAt->isFuture() ? $remindAt : Carbon::now());
    }
}
