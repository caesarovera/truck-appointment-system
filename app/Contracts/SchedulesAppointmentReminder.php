<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Appointment;

/**
 * Event yang membuat sopir perlu diingatkan pada jadwal (baru)-nya: booking &
 * reschedule. Listener `ScheduleAppointmentReminder` mendengarkan interface ini,
 * bukan tiap kelas event satu per satu — pola yang sama dengan
 * `AffectsSlotAvailability`.
 *
 * SENGAJA tidak memakai `AffectsSlotAvailability` yang sudah ada walau kedua
 * event itu mengimplementasikannya: cancel, no-show, dan buka/tutup window juga
 * mengimplementasikannya, dan tak satu pun dari mereka boleh menjadwalkan
 * reminder.
 */
interface SchedulesAppointmentReminder
{
    public function appointmentToRemind(): Appointment;
}
