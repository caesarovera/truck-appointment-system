<?php

declare(strict_types=1);

use App\Events\AppointmentBooked;
use App\Events\Broadcasting\SlotAvailabilityChanged;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

it('broadcasts live remaining quota on the gate channel when availability changes', function (): void {
    // Fake only the broadcast event so the domain event still fires its listeners.
    Event::fake([SlotAvailabilityChanged::class]);

    $gate = Gate::factory()->create();
    $window = SlotWindow::factory()->create(['gate_id' => $gate->id, 'capacity' => 5, 'booked_count' => 2]);
    $appointment = Appointment::factory()->create(['slot_window_id' => $window->id]);

    event(new AppointmentBooked($appointment));

    Event::assertDispatched(SlotAvailabilityChanged::class, function (SlotAvailabilityChanged $e) use ($gate, $window): bool {
        return $e->gateId === $gate->id
            && collect($e->windows)->contains(fn (array $w): bool => $w['id'] === $window->id && $w['remaining'] === 3);
    });
});

it('targets the private slot.{gateId} channel', function (): void {
    $channels = (new SlotAvailabilityChanged(7, []))->broadcastOn();

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-slot.7');
});
