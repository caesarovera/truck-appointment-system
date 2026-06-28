<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\seed;

it('seeds the demo dataset touching every appointment status', function (): void {
    seed([RolePermissionSeeder::class, DemoSeeder::class]);

    $statuses = Appointment::query()->pluck('status')->unique()->all();

    expect($statuses)->toContain(
        AppointmentStatus::COMPLETED,
        AppointmentStatus::NO_SHOW,
        AppointmentStatus::CONFIRMED,
        AppointmentStatus::IN_PROGRESS,
        AppointmentStatus::BOOKED,
        AppointmentStatus::CANCELLED,
    );
});

it('wires the RBAC matrix from BUSINESS-FLOW §1', function (): void {
    seed([RolePermissionSeeder::class, DemoSeeder::class]);

    $planner = User::query()->where('email', 'planner@tas.test')->firstOrFail();
    $transporter = User::query()->where('email', 'dispatcher@majulog.test')->firstOrFail();
    $driver = User::query()->where('email', 'budi@majulog.test')->firstOrFail();

    expect($planner->can('slot.manage'))->toBeTrue()
        ->and($planner->can('appointment.write'))->toBeFalse()
        ->and($transporter->can('appointment.write'))->toBeTrue()
        ->and($transporter->can('gate.process'))->toBeFalse()
        ->and($driver->can('appointment.read.self'))->toBeTrue()
        ->and($driver->can('slot.manage'))->toBeFalse();
});

it('keeps a nearly-full window for race-condition demos', function (): void {
    seed([RolePermissionSeeder::class, DemoSeeder::class]);

    $nearFull = SlotWindow::query()
        ->whereColumn('booked_count', '>=', 'capacity')
        ->orWhere(fn ($q) => $q->whereRaw('capacity - booked_count = 1'))
        ->first();

    expect($nearFull)->not->toBeNull();
});
