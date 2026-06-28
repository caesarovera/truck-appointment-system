<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('issues a token with role-based abilities on valid credentials', function (): void {
    $user = User::factory()->create(['email' => 'planner@tas.test']);
    $user->assignRole('planner');

    $response = postJson('/api/v1/login', [
        'email' => 'planner@tas.test',
        'password' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'roles', 'permissions']])
        ->assertJsonPath('user.roles', ['planner']);

    // Token abilities mencerminkan permission role (scope per role).
    $token = PersonalAccessToken::query()->firstOrFail();
    expect($token->abilities)->toContain('slot.manage')
        ->and($token->abilities)->not->toContain('gate.process');
});

it('rejects invalid password (422)', function (): void {
    $user = User::factory()->create(['email' => 'planner@tas.test']);
    $user->assignRole('planner');

    postJson('/api/v1/login', [
        'email' => 'planner@tas.test',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('rejects unknown email with a generic error (422)', function (): void {
    postJson('/api/v1/login', [
        'email' => 'nobody@tas.test',
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('validates required fields', function (): void {
    postJson('/api/v1/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
