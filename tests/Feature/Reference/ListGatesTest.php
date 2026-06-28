<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('lists all gates for a user with slot.read', function (): void {
    $terminal = Terminal::factory()->create();
    Gate::factory()->count(2)->create(['terminal_id' => $terminal->id]);

    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson('/api/v1/gates')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'terminal_id', 'code', 'name']]]);
});

it('filters gates by terminal', function (): void {
    $t1 = Terminal::factory()->create();
    $t2 = Terminal::factory()->create();
    Gate::factory()->count(2)->create(['terminal_id' => $t1->id]);
    Gate::factory()->create(['terminal_id' => $t2->id]);

    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson("/api/v1/gates?terminal={$t1->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('forbids a driver without slot.read (403)', function (): void {
    Gate::factory()->create();
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    Sanctum::actingAs($driver);

    getJson('/api/v1/gates')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/gates')->assertUnauthorized();
});
