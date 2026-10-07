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

// Audit 2026-10-07: kolasi MySQL (utf8mb4_unicode_ci) menyamakan `Planner@` dan `planner@`, tapi kunci
// throttle membedakannya → tiap variasi kapital dapat kuota sendiri (5 × 2^n percobaan/menit).
it('shares one throttle bucket across case variants of the same email', function (): void {
    $limit = (int) config('tas.rate_limits.login');

    User::factory()->create(['email' => 'planner@tas.test'])->assignRole('planner');

    $variants = ['planner@tas.test', 'Planner@tas.test', 'PLANNER@tas.test', 'pLanner@TAS.test', 'plAnner@tas.test'];

    for ($i = 0; $i < $limit; $i++) {
        postJson('/api/v1/login', ['email' => $variants[$i % count($variants)], 'password' => 'nope'])->assertStatus(422);
    }

    postJson('/api/v1/login', ['email' => 'PlAnNeR@tas.test', 'password' => 'nope'])->assertStatus(429);
});

// Password spraying: satu IP mencoba banyak email → kunci per-email tak pernah penuh.
it('throttles one IP across many different emails', function (): void {
    $limit = (int) config('tas.rate_limits.login_ip');

    for ($i = 0; $i < $limit; $i++) {
        postJson('/api/v1/login', ['email' => "user{$i}@tas.test", 'password' => 'nope'])->assertStatus(422);
    }

    postJson('/api/v1/login', ['email' => 'fresh@tas.test', 'password' => 'nope'])->assertStatus(429);
});

it('answers 422, not 500, when email is not a string', function (): void {
    postJson('/api/v1/login', ['email' => ['a@tas.test'], 'password' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('email');
});

// Review keamanan 2026-10-08: kolasi utf8mb4_unicode_ci juga menyamakan aksen, fullwidth, dan ß dengan
// padanan ASCII-nya — lowercase saja tak menutup bypass kuota per-email. Penutupnya: email non-ASCII
// berhenti di validasi (422) dan tak pernah mencapai findByEmail/Hash::check.
it('never lets a collation-equivalent non-ascii email reach the credential check', function (string $email): void {
    User::factory()->create(['email' => 'planner@tas.test'])->assignRole('planner');

    postJson('/api/v1/login', ['email' => $email, 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('email');
})->with([
    'aksen' => 'plánner@tas.test',
    'fullwidth' => 'ｐlanner@tas.test',
    'tanda kombinasi' => "pla\u{0301}nner@tas.test",
]);

// Bucket per-IP hanya untuk upaya GAGAL: 30 petugas di belakang satu NAT yang login saat pergantian
// shift (semua berhasil) tak boleh saling mengunci.
it('does not count successful logins against the per-IP bucket', function (): void {
    $limit = (int) config('tas.rate_limits.login_ip');

    for ($i = 0; $i <= $limit; $i++) {
        User::factory()->create(['email' => "officer{$i}@tas.test"])->assignRole('planner');
        postJson('/api/v1/login', ['email' => "officer{$i}@tas.test", 'password' => 'password'])->assertCreated();
    }
});
