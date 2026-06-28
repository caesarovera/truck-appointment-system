<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/** Window hari ini di sebuah gate. */
function todayWindowAt(Gate $gate): SlotWindow
{
    return SlotWindow::factory()->create(['gate_id' => $gate->id, 'date' => now()->toDateString()]);
}

it('lists today CONFIRMED and IN_PROGRESS appointments at the officer terminal only', function (): void {
    $terminal = Terminal::factory()->create();
    $gate = Gate::factory()->create(['terminal_id' => $terminal->id]);
    $window = todayWindowAt($gate);

    $confirmed = Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'CONFIRMED']);
    $inProgress = Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'IN_PROGRESS']);
    // Noise di terminal yang sama: status di luar antrian.
    Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'COMPLETED']);
    Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'BOOKED']);

    // Noise: terminal lain.
    $otherGate = Gate::factory()->create(['terminal_id' => Terminal::factory()->create()->id]);
    Appointment::factory()->create(['slot_window_id' => todayWindowAt($otherGate)->id, 'status' => 'CONFIRMED']);

    // Noise: terminal sama tapi kemarin.
    $yesterday = SlotWindow::factory()->create(['gate_id' => $gate->id, 'date' => now()->subDay()->toDateString()]);
    Appointment::factory()->create(['slot_window_id' => $yesterday->id, 'status' => 'CONFIRMED']);

    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');
    Sanctum::actingAs($officer);

    $response = getJson('/api/v1/gate/queue')->assertOk()->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($confirmed->id)->toContain($inProgress->id);
});

it('forbids a transporter without gate.process (403)', function (): void {
    $transporter = User::factory()->create();
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    getJson('/api/v1/gate/queue')->assertForbidden();
});

it('forbids a gate officer without an assigned terminal (403)', function (): void {
    $officer = User::factory()->create(['terminal_id' => null]);
    $officer->assignRole('gate-officer');
    Sanctum::actingAs($officer);

    getJson('/api/v1/gate/queue')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/gate/queue')->assertUnauthorized();
});
