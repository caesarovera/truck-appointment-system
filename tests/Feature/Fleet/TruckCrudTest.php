<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/*
 * CRUD armada (truk) milik transporter — SELALU ter-scope ke company_id user
 * yang login (bukan param). Beda dari Admin CRUD (lihat lintas company).
 * Otorisasi: `fleet.manage` + kepemilikan company (truk company lain → 403).
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->company = TransportCompany::factory()->create();
    $this->transporter = User::factory()->create(['company_id' => $this->company->id])->assignRole('transporter');
});

it('lists only the transporter own company trucks', function (): void {
    Truck::factory()->count(2)->create(['company_id' => $this->company->id]);
    Truck::factory()->create(); // company lain — tak boleh bocor

    $this->actingAs($this->transporter)
        ->getJson('/api/v1/me/trucks')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists INACTIVE trucks too (they must stay manageable/reactivatable)', function (): void {
    Truck::factory()->create(['company_id' => $this->company->id]);
    Truck::factory()->inactive()->create(['company_id' => $this->company->id]);

    // Kebalikan `GET /me/fleet` (dropdown booking) yang menyaring ke ACTIVE saja.
    $this->actingAs($this->transporter)
        ->getJson('/api/v1/me/trucks')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('forbids a user without fleet.manage', function (): void {
    $driver = User::factory()->create(['company_id' => $this->company->id])->assignRole('driver');

    $this->actingAs($driver)->getJson('/api/v1/me/trucks')->assertForbidden();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me/trucks')->assertUnauthorized();
});

it('creates a truck scoped to the own company', function (): void {
    $this->actingAs($this->transporter)
        ->postJson('/api/v1/me/trucks', ['plate_no' => 'B 1234 XY', 'status' => 'ACTIVE'])
        ->assertCreated()
        ->assertJsonPath('data.plate_no', 'B 1234 XY')
        ->assertJsonPath('data.status', 'ACTIVE');

    $this->assertDatabaseHas('trucks', ['plate_no' => 'B 1234 XY', 'company_id' => $this->company->id]);
});

it('rejects a duplicate plate_no within the same company (422)', function (): void {
    Truck::factory()->create(['company_id' => $this->company->id, 'plate_no' => 'B 1 CD']);

    $this->actingAs($this->transporter)
        ->postJson('/api/v1/me/trucks', ['plate_no' => 'B 1 CD', 'status' => 'ACTIVE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('plate_no');
});

it('allows the same plate_no in a different company', function (): void {
    $other = TransportCompany::factory()->create();
    Truck::factory()->create(['company_id' => $other->id, 'plate_no' => 'B 2 EF']);

    $this->actingAs($this->transporter)
        ->postJson('/api/v1/me/trucks', ['plate_no' => 'B 2 EF', 'status' => 'ACTIVE'])
        ->assertCreated();
});

it('validates the status enum (422)', function (): void {
    $this->actingAs($this->transporter)
        ->postJson('/api/v1/me/trucks', ['plate_no' => 'B 9 ZZ', 'status' => 'BOGUS'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('status');
});

it('updates the own truck', function (): void {
    $truck = Truck::factory()->create([
        'company_id' => $this->company->id, 'plate_no' => 'B 3 GH', 'status' => 'ACTIVE',
    ]);

    $this->actingAs($this->transporter)
        ->patchJson("/api/v1/me/trucks/{$truck->id}", ['plate_no' => 'B 3 GH', 'status' => 'INACTIVE'])
        ->assertOk()
        ->assertJsonPath('data.status', 'INACTIVE');

    $this->assertDatabaseHas('trucks', ['id' => $truck->id, 'status' => 'INACTIVE']);
});

it('forbids updating another company truck (403)', function (): void {
    $truck = Truck::factory()->create(); // company lain

    $this->actingAs($this->transporter)
        ->patchJson("/api/v1/me/trucks/{$truck->id}", ['plate_no' => 'X 1 Y', 'status' => 'ACTIVE'])
        ->assertForbidden();
});

it('deletes the own truck without appointments (204)', function (): void {
    $truck = Truck::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->transporter)
        ->deleteJson("/api/v1/me/trucks/{$truck->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('trucks', ['id' => $truck->id]);
});

it('refuses to delete a truck that has appointments (409 entity_in_use)', function (): void {
    $truck = Truck::factory()->create(['company_id' => $this->company->id]);
    Appointment::factory()->create(['truck_id' => $truck->id, 'company_id' => $this->company->id]);

    $this->actingAs($this->transporter)
        ->deleteJson("/api/v1/me/trucks/{$truck->id}")
        ->assertStatus(409)
        ->assertJsonPath('error', 'entity_in_use');

    $this->assertDatabaseHas('trucks', ['id' => $truck->id]);
});

it('forbids deleting another company truck (403)', function (): void {
    $truck = Truck::factory()->create(); // company lain

    $this->actingAs($this->transporter)
        ->deleteJson("/api/v1/me/trucks/{$truck->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('trucks', ['id' => $truck->id]);
});
