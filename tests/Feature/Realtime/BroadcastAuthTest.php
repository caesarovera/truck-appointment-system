<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\Terminal;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

/*
 * Endpoint /broadcasting/auth adalah gerbang channel privat WebSocket (Echo →
 * POST sebelum join). Regresi yang dijaga: SPA memakai Bearer token Sanctum,
 * BUKAN session cookie — maka route ini WAJIB ber-guard `auth:sanctum`, bukan
 * `web` (default framework saat channels didaftarkan lewat withRouting()).
 * Otorisasi per-channel = cermin matriks RBAC (routes/channels.php).
 */

beforeEach(fn () => seed(RolePermissionSeeder::class));

/**
 * Aktifkan broadcaster pusher (bertanda tangan) supaya callback otorisasi channel
 * benar-benar dijalankan — driver `null` (default phpunit) meng-no-op auth().
 * Channel didaftarkan pada driver DEFAULT saat boot (`null`); setelah default
 * diganti ke pusher, channel harus didaftarkan ulang di driver baru dengan
 * me-`require` channels.php (isinya hanya panggilan Broadcast::channel — idempoten).
 * Kredensial dummy cukup untuk HMAC lokal (tanpa jaringan).
 */
function usePusherBroadcaster(): void
{
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app',
        'broadcasting.connections.pusher.options.cluster' => 'mt1',
    ]);

    require base_path('routes/channels.php');
}

/** @param array<string, mixed> $extra */
function authChannel(string $channel, array $extra = [])
{
    return postJson('/broadcasting/auth', array_merge([
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ], $extra));
}

it('rejects an unauthenticated request with 401 (guard is auth:sanctum, not web)', function (): void {
    // Tanpa driver pusher pun cukup: middleware menolak sebelum controller.
    authChannel('private-slot.1')->assertUnauthorized();
});

it('authorizes a user with slot.read on the private slot channel', function (): void {
    usePusherBroadcaster();

    $user = User::factory()->create();
    $user->assignRole('transporter'); // punya slot.read

    actingAs($user, 'sanctum');

    authChannel('private-slot.5')
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('denies a user without slot.read on the private slot channel', function (): void {
    usePusherBroadcaster();

    $user = User::factory()->create();
    $user->assignRole('driver'); // hanya appointment.read.self

    actingAs($user, 'sanctum');

    authChannel('private-slot.5')->assertForbidden();
});

it('authorizes a gate officer on their own terminal gate-queue channel', function (): void {
    usePusherBroadcaster();

    $terminal = Terminal::factory()->create();
    Gate::factory()->create(['terminal_id' => $terminal->id]);

    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');

    actingAs($officer, 'sanctum');

    authChannel("private-gate.queue.{$terminal->id}")
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('denies a gate officer on a terminal that is not theirs', function (): void {
    usePusherBroadcaster();

    $home = Terminal::factory()->create();
    $other = Terminal::factory()->create();

    $officer = User::factory()->create(['terminal_id' => $home->id]);
    $officer->assignRole('gate-officer');

    actingAs($officer, 'sanctum');

    authChannel("private-gate.queue.{$other->id}")->assertForbidden();
});
