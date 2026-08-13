<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\GateTransaction;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('lists appointments that already gated in, including ones already gated out (COMPLETED)', function (): void {
    $gate = Gate::factory()->create();
    $window = SlotWindow::factory()->create(['gate_id' => $gate->id, 'date' => now()->toDateString()]);

    // Sudah gate-in & gate-out — HARUS muncul (ini intinya, beda dari antrian gate).
    $completed = Appointment::factory()->completed()->create(['slot_window_id' => $window->id]);
    GateTransaction::factory()->create(['appointment_id' => $completed->id, 'type' => 'IN']);
    GateTransaction::factory()->create(['appointment_id' => $completed->id, 'type' => 'OUT']);

    // Sudah gate-in, belum gate-out — HARUS muncul.
    $inProgress = Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'IN_PROGRESS']);
    GateTransaction::factory()->create(['appointment_id' => $inProgress->id, 'type' => 'IN']);

    // Belum pernah gate-in sama sekali — TIDAK boleh muncul.
    Appointment::factory()->confirmed()->create(['slot_window_id' => $window->id]);

    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    $response = getJson("/api/v1/reports/gate-history?gate={$gate->id}&date=".now()->toDateString())
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.gate_id', $gate->id);

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($completed->id, $inProgress->id);

    $completedEntry = collect($response->json('data'))->firstWhere('id', $completed->id);
    expect($completedEntry['gate_in_at'])->not->toBeNull()
        ->and($completedEntry['gate_out_at'])->not->toBeNull()
        ->and($completedEntry['dwell_minutes'])->not->toBeNull();
});

it('excludes appointments from a different gate or date', function (): void {
    $gate = Gate::factory()->create();
    $otherGate = Gate::factory()->create();
    $window = SlotWindow::factory()->create(['gate_id' => $gate->id, 'date' => now()->toDateString()]);
    $otherGateWindow = SlotWindow::factory()->create(['gate_id' => $otherGate->id, 'date' => now()->toDateString()]);
    $otherDateWindow = SlotWindow::factory()->create(['gate_id' => $gate->id, 'date' => now()->addDay()->toDateString()]);

    foreach ([$otherGateWindow, $otherDateWindow] as $w) {
        $appointment = Appointment::factory()->create(['slot_window_id' => $w->id, 'status' => 'IN_PROGRESS']);
        GateTransaction::factory()->create(['appointment_id' => $appointment->id, 'type' => 'IN']);
    }
    // Kandidat yang seharusnya muncul, supaya test ini juga membuktikan query tak kosong karena alasan lain.
    $matching = Appointment::factory()->create(['slot_window_id' => $window->id, 'status' => 'IN_PROGRESS']);
    GateTransaction::factory()->create(['appointment_id' => $matching->id, 'type' => 'IN']);

    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson("/api/v1/reports/gate-history?gate={$gate->id}&date=".now()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

it('forbids a transporter from the cross-company gate history report (403)', function (): void {
    $gate = Gate::factory()->create();
    $transporter = User::factory()->create();
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    getJson("/api/v1/reports/gate-history?gate={$gate->id}")->assertForbidden();
});

it('requires the gate parameter (422)', function (): void {
    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson('/api/v1/reports/gate-history')->assertStatus(422);
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/reports/gate-history?gate=1')->assertUnauthorized();
});
