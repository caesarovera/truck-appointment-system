<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\GateTransaction;

// `new Appointment()` telanjang (bukan factory()->make()): suite Unit tak
// punya RefreshDatabase, dan factory nested (company_id/truck_id/dst) tetap
// create() ke DB walau parent-nya make() — dwellMinutes() tak butuh field itu.

it('is null when gate relations are not eager-loaded', function (): void {
    $appointment = new Appointment;

    expect($appointment->dwellMinutes())->toBeNull();
});

it('is null when the truck has not gated out yet', function (): void {
    $appointment = new Appointment;
    $appointment->setRelation('gateIn', tap(new GateTransaction, fn ($tx) => $tx->processed_at = now()));
    $appointment->setRelation('gateOut', null);

    expect($appointment->dwellMinutes())->toBeNull();
});

it('computes minutes elapsed between gate-in and gate-out', function (): void {
    $appointment = new Appointment;
    $appointment->setRelation('gateIn', tap(new GateTransaction, fn ($tx) => $tx->processed_at = now()));
    $appointment->setRelation('gateOut', tap(new GateTransaction, fn ($tx) => $tx->processed_at = now()->addMinutes(45)));

    expect($appointment->dwellMinutes())->toBe(45);
});
