<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;
use function Pest\Laravel\withHeaders;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('returns the authenticated user with roles and permissions', function (): void {
    $user = User::factory()->create(['company_id' => null]);
    $user->assignRole('transporter');
    $token = $user->createToken('test')->plainTextToken;

    $response = withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles', ['transporter']);

    expect($response->json('data.permissions'))
        ->toContain('appointment.write', 'fleet.manage')
        ->not->toContain('gate.process');
});

it('rejects /me without a token (401)', function (): void {
    getJson('/api/v1/me')->assertUnauthorized();
});

it('revokes the current token on logout', function (): void {
    $user = User::factory()->create();
    $user->assignRole('driver');
    $token = $user->createToken('test')->plainTextToken;

    expect(PersonalAccessToken::query()->count())->toBe(1);

    withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/logout')
        ->assertOk();

    // Bukti pencabutan: baris token hilang dari DB (tiap request nyata = proses baru
    // yang akan gagal resolve token ini).
    expect(PersonalAccessToken::query()->count())->toBe(0);
});
