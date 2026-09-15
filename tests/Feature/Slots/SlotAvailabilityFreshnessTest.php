<?php

declare(strict_types=1);

use App\Actions\BookAppointmentAction;
use App\Actions\CancelAppointmentAction;
use App\Actions\MarkNoShowAction;
use App\Actions\RescheduleAppointmentAction;
use App\Contracts\SlotRepositoryInterface;
use App\DataTransferObjects\BookAppointmentData;
use App\DataTransferObjects\RescheduleAppointmentData;
use App\Enums\AppointmentStatus;
use App\Enums\MoveType;
use App\Events\Broadcasting\SlotAvailabilityChanged;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
 * Setiap Action yang mengubah kuota wajib membuang cache ketersediaan DAN menyiarkan sisa kuota.
 * Test memanggil Action sungguhan (bukan memicu event manual), jadi merah bila Action lupa
 * memicu event, event lepas dari `AffectsSlotAvailability`, atau listener-nya rusak.
 */

beforeEach(fn () => Event::fake([SlotAvailabilityChanged::class]));

function freshnessRemaining(SlotWindow $window): ?int
{
    return app(SlotRepositoryInterface::class)
        ->cachedAvailability($window->gate_id, $window->date->toDateString())
        ->firstWhere('id', $window->id)
        ?->remaining();
}

function assertFreshnessBroadcast(SlotWindow $window, int $remaining): void
{
    Event::assertDispatched(
        SlotAvailabilityChanged::class,
        fn (SlotAvailabilityChanged $e): bool => $e->gateId === $window->gate_id
            && collect($e->windows)->contains(
                fn (array $w): bool => $w['id'] === $window->id && $w['remaining'] === $remaining,
            ),
    );
}

function freshnessAppointmentIn(SlotWindow $window): Appointment
{
    return Appointment::factory()->create([
        'slot_window_id' => $window->id,
        'status' => AppointmentStatus::CONFIRMED,
    ]);
}

it('refreshes cached availability and broadcasts after a booking', function (): void {
    $window = SlotWindow::factory()->create(['capacity' => 5, 'booked_count' => 2]);
    $company = TransportCompany::factory()->create();
    $actor = User::factory()->create(['company_id' => $company->id]);
    $truck = Truck::factory()->create(['company_id' => $company->id]);
    $driver = User::factory()->driver()->create(['company_id' => $company->id]);
    expect(freshnessRemaining($window))->toBe(3);

    app(BookAppointmentAction::class)->execute($actor, new BookAppointmentData(
        slotWindowId: $window->id,
        truckId: $truck->id,
        driverId: $driver->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'MAUU7654321',
    ));

    expect(freshnessRemaining($window))->toBe(2);
    assertFreshnessBroadcast($window, 2);
});

it('refreshes cached availability and broadcasts after a cancel', function (): void {
    $window = SlotWindow::factory()->create(['capacity' => 5, 'booked_count' => 3]);
    $appointment = freshnessAppointmentIn($window);
    expect(freshnessRemaining($window))->toBe(2);

    app(CancelAppointmentAction::class)->execute($appointment);

    expect(freshnessRemaining($window))->toBe(3);
    assertFreshnessBroadcast($window, 3);
});

it('refreshes cached availability and broadcasts after a no-show', function (): void {
    $window = SlotWindow::factory()->create(['capacity' => 5, 'booked_count' => 3]);
    $appointment = freshnessAppointmentIn($window);
    expect(freshnessRemaining($window))->toBe(2);

    app(MarkNoShowAction::class)->execute($appointment);

    expect(freshnessRemaining($window))->toBe(3);
    assertFreshnessBroadcast($window, 3);
});

it('refreshes both windows and broadcasts to both gates after a reschedule', function (): void {
    $from = SlotWindow::factory()->create(['gate_id' => Gate::factory(), 'capacity' => 5, 'booked_count' => 3]);
    $to = SlotWindow::factory()->create(['gate_id' => Gate::factory(), 'capacity' => 5, 'booked_count' => 1]);
    $appointment = freshnessAppointmentIn($from);
    expect(freshnessRemaining($from))->toBe(2)
        ->and(freshnessRemaining($to))->toBe(4);

    app(RescheduleAppointmentAction::class)->execute(
        $appointment,
        new RescheduleAppointmentData(slotWindowId: $to->id, expectedVersion: $appointment->version),
    );

    expect(freshnessRemaining($from))->toBe(3)
        ->and(freshnessRemaining($to))->toBe(3);
    assertFreshnessBroadcast($from, 3);
    assertFreshnessBroadcast($to, 3);
});
