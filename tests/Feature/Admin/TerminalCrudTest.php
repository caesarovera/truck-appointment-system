<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
});

it('lists terminals with gates count', function (): void {
    $terminal = Terminal::factory()->create();
    Gate::factory()->count(3)->create(['terminal_id' => $terminal->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/terminals')
        ->assertOk()
        ->assertJsonPath('data.0.gates_count', 3);
});

it('returns 403 for non-admin on terminal list', function (): void {
    $planner = User::factory()->create()->assignRole('planner');

    $this->actingAs($planner)
        ->getJson('/api/v1/admin/terminals')
        ->assertForbidden();
});

it('creates a terminal', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/terminals', ['code' => 'TPS1', 'name' => 'Test Terminal'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'TPS1');

    $this->assertDatabaseHas('terminals', ['code' => 'TPS1']);
});

it('rejects duplicate terminal code', function (): void {
    Terminal::factory()->create(['code' => 'DUPE']);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/terminals', ['code' => 'DUPE', 'name' => 'X'])
        ->assertUnprocessable();
});

it('updates a terminal', function (): void {
    $terminal = Terminal::factory()->create(['code' => 'OLD', 'name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/terminals/{$terminal->id}", ['code' => 'NEW', 'name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.code', 'NEW');

    $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'code' => 'NEW']);
});

it('allows updating terminal with same code', function (): void {
    $terminal = Terminal::factory()->create(['code' => 'SAME']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/terminals/{$terminal->id}", ['code' => 'SAME', 'name' => 'Updated Name'])
        ->assertOk();
});

it('deletes a terminal without gates', function (): void {
    $terminal = Terminal::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/terminals/{$terminal->id}")
        ->assertNoContent();

    $this->assertModelMissing($terminal);
});

it('refuses to delete terminal with gates', function (): void {
    $terminal = Terminal::factory()->create();
    Gate::factory()->create(['terminal_id' => $terminal->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/terminals/{$terminal->id}")
        ->assertConflict()
        ->assertJsonPath('error', 'entity_in_use');
});
