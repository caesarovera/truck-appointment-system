<?php

declare(strict_types=1);

use App\Events\AppointmentBooked;
use App\Jobs\AppointmentReminderJob;
use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\User;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

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

    AppointmentReminderJob::dispatchSync($appointment->id);

    Notification::assertSentTo($driver, AppointmentReminderNotification::class);
});

it('does not notify when the appointment is no longer active', function (): void {
    Notification::fake();

    $driver = User::factory()->create();
    $appointment = Appointment::factory()->cancelled()->create(['driver_id' => $driver->id]);

    AppointmentReminderJob::dispatchSync($appointment->id);

    Notification::assertNothingSent();
});

it('is unique per appointment id', function (): void {
    $appointment = Appointment::factory()->create();
    $job = new AppointmentReminderJob($appointment->id);

    expect($job->uniqueId())->toBe((string) $appointment->id);
});
