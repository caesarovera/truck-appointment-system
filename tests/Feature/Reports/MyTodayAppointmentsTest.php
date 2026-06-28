<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('returns only the driver own appointments scheduled for today', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole('driver');

    $todayWindow = SlotWindow::factory()->create(['date' => now()->toDateString()]);
    $mine = Appointment::factory()->create(['driver_id' => $driver->id, 'slot_window_id' => $todayWindow->id]);

    // Noise: someone else's appointment today, and my own appointment tomorrow.
    Appointment::factory()->create(['slot_window_id' => $todayWindow->id]);
    $tomorrowWindow = SlotWindow::factory()->create(['date' => now()->addDay()->toDateString()]);
    Appointment::factory()->create(['driver_id' => $driver->id, 'slot_window_id' => $tomorrowWindow->id]);

    Sanctum::actingAs($driver);

    getJson('/api/v1/me/appointments/today')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.booking_code', $mine->booking_code);
});

it('forbids a user without the self-read scope (transporter) — 403', function (): void {
    $transporter = User::factory()->create();
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    getJson('/api/v1/me/appointments/today')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/me/appointments/today')->assertUnauthorized();
});
