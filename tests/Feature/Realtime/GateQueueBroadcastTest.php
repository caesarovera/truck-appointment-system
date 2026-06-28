<?php

declare(strict_types=1);

use App\Events\Broadcasting\GateQueueUpdated;
use App\Events\TruckGatedIn;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

it('broadcasts the gate queue on the terminal channel when a truck gates in', function (): void {
    Event::fake([GateQueueUpdated::class]);

    $terminal = Terminal::factory()->create();
    $gate = Gate::factory()->create(['terminal_id' => $terminal->id]);
    $window = SlotWindow::factory()->create(['gate_id' => $gate->id]);
    $appointment = Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'IN_PROGRESS']);

    event(new TruckGatedIn($appointment));

    Event::assertDispatched(GateQueueUpdated::class, fn (GateQueueUpdated $e): bool => $e->terminalId === $terminal->id
        && $e->appointmentId === $appointment->id
        && $e->gateEvent === 'IN');
});

it('targets the private gate.queue.{terminalId} channel', function (): void {
    $channels = (new GateQueueUpdated(3, 1, 'TAS-ABC', 'IN_PROGRESS', 'IN'))->broadcastOn();

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-gate.queue.3');
});
