<?php

declare(strict_types=1);

use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
});

it('lists companies with counts', function (): void {
    $company = TransportCompany::factory()->create();
    User::factory()->count(2)->create(['company_id' => $company->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/companies')
        ->assertOk()
        ->assertJsonPath('data.0.users_count', 2);
});

it('returns 403 for non-admin on company list', function (): void {
    $transporter = User::factory()->create()->assignRole('transporter');

    $this->actingAs($transporter)
        ->getJson('/api/v1/admin/companies')
        ->assertForbidden();
});

it('creates a company', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/companies', ['code' => 'LOGCO', 'name' => 'Logistik Co'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'LOGCO');

    $this->assertDatabaseHas('transport_companies', ['code' => 'LOGCO']);
});

it('rejects duplicate company code', function (): void {
    TransportCompany::factory()->create(['code' => 'DUPE']);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/companies', ['code' => 'DUPE', 'name' => 'X'])
        ->assertUnprocessable();
});

it('updates a company', function (): void {
    $company = TransportCompany::factory()->create(['code' => 'OLD', 'name' => 'Old']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/companies/{$company->id}", ['code' => 'NEW', 'name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.code', 'NEW');
});

it('allows updating company with same code', function (): void {
    $company = TransportCompany::factory()->create(['code' => 'SAME']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/companies/{$company->id}", ['code' => 'SAME', 'name' => 'Updated'])
        ->assertOk();
});

it('deletes a company without users or appointments', function (): void {
    $company = TransportCompany::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/companies/{$company->id}")
        ->assertNoContent();

    $this->assertModelMissing($company);
});

it('refuses to delete company with users', function (): void {
    $company = TransportCompany::factory()->create();
    User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/companies/{$company->id}")
        ->assertConflict()
        ->assertJsonPath('error', 'entity_in_use');
});
