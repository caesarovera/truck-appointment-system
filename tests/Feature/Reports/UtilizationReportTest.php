<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('reports capacity, usage and no-shows per window for a gate', function (): void {
    $gate = Gate::factory()->create();
    $window = SlotWindow::factory()->create([
        'gate_id' => $gate->id,
        'date' => now()->toDateString(),
        'capacity' => 10,
        'booked_count' => 3,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    Appointment::factory()->completed()->create(['slot_window_id' => $window->id]);
    Appointment::factory()->noShow()->create(['slot_window_id' => $window->id]);
    Appointment::factory()->confirmed()->create(['slot_window_id' => $window->id]);
    Appointment::factory()->cancelled()->create(['slot_window_id' => $window->id]);

    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson("/api/v1/reports/utilization?gate={$gate->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.capacity', 10)
        ->assertJsonPath('data.0.booked_count', 3)
        ->assertJsonPath('data.0.completed', 1)
        ->assertJsonPath('data.0.no_show', 1)
        ->assertJsonPath('data.0.cancelled', 1)
        ->assertJsonPath('data.0.active', 1)
        ->assertJsonPath('meta.summary.completed', 1)
        ->assertJsonPath('meta.summary.no_show', 1)
        ->assertJsonPath('meta.summary.capacity', 10);
});

it('forbids a transporter from the aggregate utilization report (403)', function (): void {
    $gate = Gate::factory()->create();
    $transporter = User::factory()->create();
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    getJson("/api/v1/reports/utilization?gate={$gate->id}")->assertForbidden();
});

it('requires the gate parameter (422)', function (): void {
    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson('/api/v1/reports/utilization')->assertStatus(422);
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/reports/utilization?gate=1')->assertUnauthorized();
});
