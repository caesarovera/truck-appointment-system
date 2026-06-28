<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Events\AppointmentNoShow;
use App\Jobs\NoShowSweepJob;
use App\Models\Appointment;
use App\Models\Container;
use App\Models\SlotWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * Buat appointment + container di sebuah window dengan waktu tertentu.
 *
 * @return array{appointment: Appointment, window: SlotWindow}
 */
function noShowScenario(string $status, Carbon $date, string $endTime, int $bookedCount = 1): array
{
    $window = SlotWindow::factory()->create([
        'date' => $date->toDateString(),
        'start_time' => '06:00:00',
        'end_time' => $endTime,
        'capacity' => 5,
        'booked_count' => $bookedCount,
    ]);

    $appointment = Appointment::factory()->create([
        'slot_window_id' => $window->id,
        'status' => $status,
    ]);
    Container::factory()->create([
        'appointment_id' => $appointment->id,
        'slot_window_id' => $window->id,
    ]);

    return compact('appointment', 'window');
}

it('marks a confirmed appointment past the grace period as NO_SHOW and returns its quota', function (): void {
    Event::fake([AppointmentNoShow::class]);
    config(['tas.no_show_grace_minutes' => 30]);

    // Window ended yesterday → well past grace.
    ['appointment' => $appointment, 'window' => $window] = noShowScenario(
        status: 'CONFIRMED',
        date: now()->subDay(),
        endTime: '07:00:00',
    );

    NoShowSweepJob::dispatchSync();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NO_SHOW)
        ->and($window->fresh()->booked_count)->toBe(0)
        ->and(Container::query()->where('appointment_id', $appointment->id)->value('slot_window_id'))->toBeNull();

    Event::assertDispatched(AppointmentNoShow::class);
});

it('also sweeps still-booked (unconfirmed) appointments', function (): void {
    config(['tas.no_show_grace_minutes' => 30]);
    ['appointment' => $appointment] = noShowScenario('BOOKED', now()->subDay(), '07:00:00');

    NoShowSweepJob::dispatchSync();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NO_SHOW);
});

it('leaves appointments still inside the grace period untouched', function (): void {
    Event::fake([AppointmentNoShow::class]);
    config(['tas.no_show_grace_minutes' => 30]);

    // Window ended 10 minutes ago, grace is 30 → not yet a no-show.
    ['appointment' => $appointment, 'window' => $window] = noShowScenario(
        status: 'CONFIRMED',
        date: now(),
        endTime: now()->subMinutes(10)->format('H:i:s'),
    );

    NoShowSweepJob::dispatchSync();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CONFIRMED)
        ->and($window->fresh()->booked_count)->toBe(1);

    Event::assertNotDispatched(AppointmentNoShow::class);
});

it('sweeps every due appointment across chunk boundaries (chunked scan)', function (): void {
    config(['tas.no_show_grace_minutes' => 30, 'tas.no_show_chunk_size' => 2]);

    // 5 kandidat lewat grace → harus tersapu walau chunk hanya muat 2 (3 iterasi).
    $due = collect(range(1, 5))->map(
        fn (): Appointment => noShowScenario('CONFIRMED', now()->subDay(), '07:00:00')['appointment'],
    );
    // 1 di dalam grace → harus tetap aman meski ikut ter-scan antar chunk.
    $safe = noShowScenario('CONFIRMED', now(), now()->subMinutes(10)->format('H:i:s'))['appointment'];

    NoShowSweepJob::dispatchSync();

    $due->each(fn (Appointment $a) => expect($a->fresh()->status)->toBe(AppointmentStatus::NO_SHOW));
    expect($safe->fresh()->status)->toBe(AppointmentStatus::CONFIRMED);
});

it('never touches arrived, in-progress, completed or cancelled appointments', function (string $status): void {
    config(['tas.no_show_grace_minutes' => 30]);
    ['appointment' => $appointment] = noShowScenario($status, now()->subDay(), '07:00:00');

    NoShowSweepJob::dispatchSync();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::from($status));
})->with(['ARRIVED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW']);
