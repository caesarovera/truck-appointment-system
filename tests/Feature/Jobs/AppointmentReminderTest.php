<?php

declare(strict_types=1);

use App\Events\AppointmentBooked;
use App\Events\AppointmentRescheduled;
use App\Jobs\AppointmentReminderJob;
use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\User;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\travelTo;

it('schedules a reminder job when an appointment is booked', function (): void {
    Bus::fake([AppointmentReminderJob::class]);

    // Window starts in the future so the reminder is scheduled with a delay.
    $window = SlotWindow::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);
    $appointment = Appointment::factory()->confirmed()->create(['slot_window_id' => $window->id]);

    event(new AppointmentBooked($appointment));

    Bus::assertDispatched(AppointmentReminderJob::class, fn (AppointmentReminderJob $job): bool => $job->appointmentId === $appointment->id);
});

it('notifies the driver when the reminder fires and the appointment still stands', function (): void {
    Notification::fake();

    $driver = User::factory()->create();
    $appointment = Appointment::factory()->confirmed()->create(['driver_id' => $driver->id]);

    AppointmentReminderJob::dispatchSync($appointment->id, $appointment->slot_window_id);

    Notification::assertSentTo($driver, AppointmentReminderNotification::class);
});

it('does not notify when the appointment is no longer active', function (): void {
    Notification::fake();

    $driver = User::factory()->create();
    $appointment = Appointment::factory()->cancelled()->create(['driver_id' => $driver->id]);

    AppointmentReminderJob::dispatchSync($appointment->id, $appointment->slot_window_id);

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Reschedule → reminder ikut pindah (BUSINESS-FLOW §3.3)
|--------------------------------------------------------------------------
| Tanpa ini, satu-satunya listener reminder mendengarkan AppointmentBooked saja:
| appointment yang dipindah tetap CONFIRMED, jadi reminder-nya meledak di jam
| window LAMA dan window baru tak pernah dapat reminder sama sekali. Sopir yang
| jadwalnya digeser tidak diingatkan.
*/

it('schedules a fresh reminder when an appointment is rescheduled', function (): void {
    Bus::fake([AppointmentReminderJob::class]);

    $from = SlotWindow::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);
    $to = SlotWindow::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '15:00:00',
        'end_time' => '16:00:00',
    ]);
    // Appointment sudah menunjuk window baru saat event dipancarkan (lihat
    // RescheduleAppointmentAction: dispatch terjadi setelah commit).
    $appointment = Appointment::factory()->confirmed()->create(['slot_window_id' => $to->id]);

    event(new AppointmentRescheduled($appointment, $from->id, $to->id));

    Bus::assertDispatched(
        AppointmentReminderJob::class,
        fn (AppointmentReminderJob $job): bool => $job->appointmentId === $appointment->id
            && $job->slotWindowId === $to->id,
    );
});

it('aims the rescheduled reminder at the new window lead time, not the old one', function (): void {
    travelTo(today()->setTime(8, 0));
    config(['tas.reminder_lead_minutes' => 120]);
    Bus::fake([AppointmentReminderJob::class]);

    $from = SlotWindow::factory()->create([
        'date' => today()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);
    $to = SlotWindow::factory()->create([
        'date' => today()->toDateString(),
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
    ]);
    $appointment = Appointment::factory()->confirmed()->create(['slot_window_id' => $to->id]);

    event(new AppointmentRescheduled($appointment, $from->id, $to->id));

    // H-2 jam dari window BARU (18:00) = 16:00 — bukan 08:00 dari window lama.
    Bus::assertDispatched(
        AppointmentReminderJob::class,
        fn (AppointmentReminderJob $job): bool => $job->delay instanceof Carbon
            && $job->delay->equalTo(today()->setTime(16, 0)),
    );
});

it('neutralises the stale reminder left over from before the reschedule', function (): void {
    Notification::fake();

    $old = SlotWindow::factory()->create();
    $new = SlotWindow::factory()->create();
    $driver = User::factory()->create();
    $appointment = Appointment::factory()->confirmed()->create([
        'driver_id' => $driver->id,
        'slot_window_id' => $new->id,
    ]);

    // Job yang dijadwalkan SEBELUM reschedule masih membawa window lama. Job
    // yang sudah antre tak bisa ditarik kembali dari queue — jadi ia harus
    // membatalkan dirinya sendiri saat sadar appointment-nya sudah pindah.
    AppointmentReminderJob::dispatchSync($appointment->id, $old->id);

    Notification::assertNothingSent();
});

it('still notifies when the appointment is on the very window the reminder was scheduled for', function (): void {
    // Penjaga arah sebaliknya: guard "sudah pindah" tak boleh jadi terlalu ketat
    // dan membungkam reminder yang justru sah.
    Notification::fake();

    $window = SlotWindow::factory()->create();
    $driver = User::factory()->create();
    $appointment = Appointment::factory()->confirmed()->create([
        'driver_id' => $driver->id,
        'slot_window_id' => $window->id,
    ]);

    AppointmentReminderJob::dispatchSync($appointment->id, $window->id);

    Notification::assertSentTo($driver, AppointmentReminderNotification::class);
});

it('is unique per appointment AND slot window', function (): void {
    // JEBAKAN yang membuat "tinggal dispatch ulang" TIDAK cukup: ShouldBeUnique
    // memegang lock selama job masih PENDING di queue (dilepas saat job diproses,
    // lihat PendingDispatch::shouldDispatch). Kalau uniqueId cuma appointment id,
    // reminder baru hasil reschedule DIBUANG DIAM-DIAM oleh Laravel karena job
    // lama masih menunggu — fix-nya tak akan berfungsi di queue sungguhan.
    // Di test QUEUE_CONNECTION=sync lock langsung lepas, jadi jebakan ini hanya
    // kelihatan lewat assertion di bawah, bukan lewat test perilaku.
    $appointment = Appointment::factory()->create();
    $job = new AppointmentReminderJob($appointment->id, 77);

    expect($job->uniqueId())->toBe($appointment->id.':77');
});
