<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\SchedulesAppointmentReminder;
use App\Jobs\AppointmentReminderJob;
use Illuminate\Support\Carbon;

/**
 * Jadwalkan AppointmentReminderJob H-(reminder_lead) sebelum window mulai.
 * Bila lead-time sudah lewat (booking mepet), reminder dikirim segera.
 *
 * Mendengarkan interface `SchedulesAppointmentReminder`, jadi satu listener
 * melayani booking DAN reschedule — appointment yang dipindah dapat reminder
 * baru pada jam window barunya. Job lama yang sudah antre tak bisa ditarik dari
 * queue; ia membatalkan dirinya sendiri lewat guard `slotWindowId` di dalam job.
 */
final class ScheduleAppointmentReminder
{
    public function handle(SchedulesAppointmentReminder $event): void
    {
        $appointment = $event->appointmentToRemind();
        $appointment->loadMissing('slotWindow');
        $window = $appointment->slotWindow;

        if ($window === null) {
            return;
        }

        $leadMinutes = (int) config('tas.reminder_lead_minutes', 120);
        $remindAt = $window->startsAt()->subMinutes($leadMinutes);

        AppointmentReminderJob::dispatch($appointment->id, $window->id)
            ->delay($remindAt->isFuture() ? $remindAt : Carbon::now());
    }
}
