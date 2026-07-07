<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;

/*
 * Token Sanctum HARUS punya umur terbatas (config sanctum.expiration).
 * Tanpa TTL, token yang bocor (XSS localStorage, perangkat hilang, log
 * tercecer) valid selamanya — logout hanya mencabut token itu satu per satu.
 * SPA sudah menangani 401 → redirect login, jadi kedaluwarsa terdegradasi mulus.
 */

it('rejects an expired token (401)', function (): void {
    $user = User::factory()->create();
    $plain = $user->createToken('test-device')->plainTextToken;

    $this->travel((int) config('sanctum.expiration') + 1)->minutes();

    getJson('/api/v1/me', ['Authorization' => "Bearer {$plain}"])
        ->assertUnauthorized();
});

it('accepts a token within its lifetime', function (): void {
    $user = User::factory()->create();
    $plain = $user->createToken('test-device')->plainTextToken;

    $this->travel((int) config('sanctum.expiration') - 5)->minutes();

    getJson('/api/v1/me', ['Authorization' => "Bearer {$plain}"])
        ->assertOk();
});

it('prunes expired tokens past the 24h grace window', function (): void {
    $user = User::factory()->create();
    $user->createToken('stale-device');

    // Lewat TTL + grace 24 jam → baris token mati boleh dibersihkan dari DB.
    $this->travel((int) config('sanctum.expiration'))->minutes();
    $this->travel(25)->hours();

    artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('schedules daily pruning of expired tokens', function (): void {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (object $event): string => (string) ($event->command ?? ''));

    expect($commands->contains(fn (string $cmd): bool => str_contains($cmd, 'sanctum:prune-expired')))
        ->toBeTrue();
});
