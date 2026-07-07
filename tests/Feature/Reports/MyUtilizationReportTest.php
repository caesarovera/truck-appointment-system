<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/*
 * Laporan utilisasi COMPANY-SCOPED untuk transporter (pola /me/*):
 * hitungan per-status hanya milik company si pemanggil — angka company lain
 * tidak boleh bocor. capacity/booked_count tetap konteks gate global (informasi
 * yang sama sudah terbuka lewat GET /slots/availability).
 */

it('shows a transporter only their own company counts per window', function (): void {
    $gate = Gate::factory()->create();
    $window = SlotWindow::factory()->create([
        'gate_id' => $gate->id,
        'date' => now()->toDateString(),
        'capacity' => 10,
        'booked_count' => 3,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    $mine = TransportCompany::factory()->create();
    $other = TransportCompany::factory()->create();

    // Milik company sendiri: 1 completed + 1 no-show.
    Appointment::factory()->completed()->create(['slot_window_id' => $window->id, 'company_id' => $mine->id]);
    Appointment::factory()->noShow()->create(['slot_window_id' => $window->id, 'company_id' => $mine->id]);
    // Milik company lain — TIDAK boleh terhitung.
    Appointment::factory()->completed()->create(['slot_window_id' => $window->id, 'company_id' => $other->id]);
    Appointment::factory()->confirmed()->create(['slot_window_id' => $window->id, 'company_id' => $other->id]);

    $transporter = User::factory()->create(['company_id' => $mine->id]);
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    getJson("/api/v1/me/reports/utilization?gate={$gate->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.capacity', 10)      // konteks gate (global)
        ->assertJsonPath('data.0.completed', 1)       // bukan 2 — company lain tersaring
        ->assertJsonPath('data.0.no_show', 1)
        ->assertJsonPath('data.0.cancelled', 0)
        ->assertJsonPath('data.0.active', 0)          // CONFIRMED company lain tak bocor
        ->assertJsonPath('meta.company_id', $mine->id)
        ->assertJsonPath('meta.summary.completed', 1)
        ->assertJsonPath('meta.summary.no_show', 1);
});

it('forbids a user without a company even with report.read (403)', function (): void {
    $gate = Gate::factory()->create();
    $planner = User::factory()->create(['company_id' => null]);
    $planner->assignRole('planner'); // punya report.read, tapi tanpa company scope

    Sanctum::actingAs($planner);

    getJson("/api/v1/me/reports/utilization?gate={$gate->id}")->assertForbidden();
});

it('forbids a role without report.read (403)', function (): void {
    $gate = Gate::factory()->create();
    $driver = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $driver->assignRole('driver'); // hanya appointment.read.self

    Sanctum::actingAs($driver);

    getJson("/api/v1/me/reports/utilization?gate={$gate->id}")->assertForbidden();
});

it('requires the gate parameter (422)', function (): void {
    $transporter = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    getJson('/api/v1/me/reports/utilization')->assertStatus(422);
});
