<?php

declare(strict_types=1);

use App\Contracts\GateEventGateway;
use App\Enums\GateTransactionType;
use App\Jobs\ProcessGateEventJob;
use App\Models\Appointment;
use App\Models\GateTransaction;

it('pushes to the TOS gateway when the matching gate transaction exists', function (): void {
    $appointment = Appointment::factory()->create();
    GateTransaction::factory()->create(['appointment_id' => $appointment->id]); // type IN

    $spy = Mockery::spy(GateEventGateway::class);
    app()->instance(GateEventGateway::class, $spy);

    ProcessGateEventJob::dispatchSync($appointment->id, GateTransactionType::IN);

    $spy->shouldHaveReceived('push')->once();
});

it('does not push when no matching transaction was recorded (idempotent guard)', function (): void {
    $appointment = Appointment::factory()->create();

    $spy = Mockery::spy(GateEventGateway::class);
    app()->instance(GateEventGateway::class, $spy);

    ProcessGateEventJob::dispatchSync($appointment->id, GateTransactionType::IN);

    $spy->shouldNotHaveReceived('push');
});

it('does nothing when the appointment no longer exists', function (): void {
    $spy = Mockery::spy(GateEventGateway::class);
    app()->instance(GateEventGateway::class, $spy);

    ProcessGateEventJob::dispatchSync(999999, GateTransactionType::OUT);

    $spy->shouldNotHaveReceived('push');
});
