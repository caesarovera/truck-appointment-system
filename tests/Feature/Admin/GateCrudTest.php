<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->terminal = Terminal::factory()->create();
});

it('lists admin gates with terminal eager-loaded', function (): void {
    Gate::factory()->create(['terminal_id' => $this->terminal->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/gates')
        ->assertOk()
        ->assertJsonPath('data.0.terminal.id', $this->terminal->id);
});

it('filters admin gates by terminal', function (): void {
    $other = Terminal::factory()->create();
    Gate::factory()->create(['terminal_id' => $this->terminal->id, 'code' => 'G1']);
    Gate::factory()->create(['terminal_id' => $other->id, 'code' => 'G2']);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/gates?terminal={$this->terminal->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'G1');
});

it('creates a gate', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/gates', [
            'terminal_id' => $this->terminal->id,
            'code' => 'GA',
            'name' => 'Gate A',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'GA');

    $this->assertDatabaseHas('gates', ['code' => 'GA', 'terminal_id' => $this->terminal->id]);
});

it('rejects duplicate gate code within same terminal', function (): void {
    Gate::factory()->create(['terminal_id' => $this->terminal->id, 'code' => 'DUP']);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/gates', [
            'terminal_id' => $this->terminal->id,
            'code' => 'DUP',
            'name' => 'X',
        ])
        ->assertUnprocessable();
});

it('updates a gate', function (): void {
    $gate = Gate::factory()->create(['terminal_id' => $this->terminal->id, 'code' => 'OLD']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/gates/{$gate->id}", [
            'terminal_id' => $this->terminal->id,
            'code' => 'NEW',
            'name' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.code', 'NEW');
});

it('deletes a gate without slot windows', function (): void {
    $gate = Gate::factory()->create(['terminal_id' => $this->terminal->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/gates/{$gate->id}")
        ->assertNoContent();

    $this->assertModelMissing($gate);
});

it('refuses to delete gate with slot windows', function (): void {
    $gate = Gate::factory()->create(['terminal_id' => $this->terminal->id]);
    SlotWindow::factory()->create(['gate_id' => $gate->id]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/gates/{$gate->id}")
        ->assertConflict()
        ->assertJsonPath('error', 'entity_in_use');
});
