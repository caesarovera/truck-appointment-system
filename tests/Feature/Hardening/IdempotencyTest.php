<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/** @return array{user: User, payload: array<string, mixed>} */
function idempotencyContext(): array
{
    $planner = User::factory()->create();
    $planner->assignRole('planner');

    $gate = Gate::factory()->create();

    return [
        'user' => $planner,
        'payload' => [
            'gate' => $gate->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'capacity' => 10,
        ],
    ];
}

it('replays the stored response for an arbitrarily long Idempotency-Key (hashed key, no duplicate)', function (): void {
    ['user' => $user, 'payload' => $payload] = idempotencyContext();
    Sanctum::actingAs($user);

    // Key panjang & berkarakter aneh → harus tetap aman karena di-hash jadi kunci cache.
    $headers = ['Idempotency-Key' => str_repeat('a-VERY/long+key=', 40)];

    $first = postJson('/api/v1/slots', $payload, $headers)->assertCreated();
    $second = postJson('/api/v1/slots', $payload, $headers)->assertCreated();

    $second->assertHeader('Idempotent-Replayed', 'true');
    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(SlotWindow::query()->count())->toBe(1);
});

it('does not replay a response across different endpoints with the same key', function (): void {
    ['user' => $user, 'payload' => $payload] = idempotencyContext();
    Sanctum::actingAs($user);

    // Kunci sama, endpoint beda: replay booking di gate-in = bug. Scope per
    // method+path menjamin tiap operasi punya ruang idempotency sendiri.
    $headers = ['Idempotency-Key' => 'shared-key-across-endpoints'];

    $created = postJson('/api/v1/slots', $payload, $headers)->assertCreated();
    $windowId = $created->json('data.id');

    $close = postJson("/api/v1/slots/{$windowId}/close", [], $headers);

    $close->assertOk();
    $close->assertHeaderMissing('Idempotent-Replayed');
    expect($close->json('data.status'))->toBe('CLOSED');
});

it('returns 409 when a twin request is still in flight (lock held)', function (): void {
    ['user' => $user, 'payload' => $payload] = idempotencyContext();
    Sanctum::actingAs($user);

    $key = 'in-flight-key';

    // Tiru kunci yang dipakai middleware (scope user id + sha256 method|path|header)
    // dan tahan lock-nya → simulasikan request kembar yang masih diproses.
    $cacheKey = 'idem:'.$user->id.':'.hash('sha256', 'POST|api/v1/slots|'.$key);
    $lock = Cache::lock("{$cacheKey}:lock", 60);
    expect($lock->get())->toBeTrue();

    postJson('/api/v1/slots', $payload, ['Idempotency-Key' => $key])
        ->assertStatus(409);

    // Tidak ada window yang dibuat selagi lock ditahan.
    expect(SlotWindow::query()->count())->toBe(0);
});
