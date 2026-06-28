<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('throttles brute-force login attempts after the configured limit (429)', function (): void {
    $limit = (int) config('tas.rate_limits.login');

    User::factory()->create(['email' => 'planner@tas.test'])->assignRole('planner');

    $payload = ['email' => 'planner@tas.test', 'password' => 'wrong-password'];

    // Sampai batas: tiap percobaan salah → 422 (validation), bukan 429.
    for ($i = 0; $i < $limit; $i++) {
        postJson('/api/v1/login', $payload)->assertStatus(422);
    }

    // Percobaan berikutnya melewati batas → 429 Too Many Requests.
    postJson('/api/v1/login', $payload)->assertStatus(429);
});

it('keys the throttle by email so other accounts are unaffected', function (): void {
    $limit = (int) config('tas.rate_limits.login');

    User::factory()->create(['email' => 'a@tas.test'])->assignRole('planner');
    User::factory()->create(['email' => 'b@tas.test'])->assignRole('planner');

    // Habiskan kuota untuk akun A.
    for ($i = 0; $i <= $limit; $i++) {
        postJson('/api/v1/login', ['email' => 'a@tas.test', 'password' => 'nope']);
    }
    postJson('/api/v1/login', ['email' => 'a@tas.test', 'password' => 'nope'])->assertStatus(429);

    // Akun B masih bisa login (kunci throttle berbeda).
    postJson('/api/v1/login', ['email' => 'b@tas.test', 'password' => 'password'])->assertCreated();
});
