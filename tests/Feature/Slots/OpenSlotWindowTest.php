<?php

declare(strict_types=1);

use App\Enums\SlotWindowStatus;
use App\Events\SlotWindowOpened;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

function plannerUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('planner');

    return $user;
}

/** @return array<string, mixed> */
function openPayload(Gate $gate, array $overrides = []): array
{
    return array_merge([
        'gate' => $gate->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'capacity' => 20,
    ], $overrides);
}

it('lets a planner open a slot window with zero booked count', function (): void {
    Event::fake([SlotWindowOpened::class]);
    $gate = Gate::factory()->create();
    Sanctum::actingAs(plannerUser());

    postJson('/api/v1/slots', openPayload($gate))
        ->assertCreated()
        ->assertJsonPath('data.capacity', 20)
        ->assertJsonPath('data.booked_count', 0)
        ->assertJsonPath('data.status', 'OPEN');

    $window = SlotWindow::query()->where('gate_id', $gate->id)->first();
    expect($window)->not->toBeNull()
        ->and($window->status)->toBe(SlotWindowStatus::OPEN)
        ->and($window->booked_count)->toBe(0);

    Event::assertDispatched(SlotWindowOpened::class);
});

it('rejects a duplicate window (same gate, date and start time) with 409', function (): void {
    $gate = Gate::factory()->create();
    SlotWindow::factory()->create([
        'gate_id' => $gate->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00:00',
    ]);
    Sanctum::actingAs(plannerUser());

    postJson('/api/v1/slots', openPayload($gate))
        ->assertStatus(409)
        ->assertJsonPath('error', 'duplicate_slot_window');
});

it('forbids a transporter from opening a window (403)', function (): void {
    $gate = Gate::factory()->create();
    $transporter = User::factory()->create();
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    postJson('/api/v1/slots', openPayload($gate))->assertForbidden();
});

it('validates required fields and time ordering (422)', function (): void {
    $gate = Gate::factory()->create();
    Sanctum::actingAs(plannerUser());

    postJson('/api/v1/slots', openPayload($gate, ['capacity' => 0]))->assertStatus(422);
    postJson('/api/v1/slots', openPayload($gate, ['end_time' => '07:00:00']))->assertStatus(422);
});

it('makes a newly opened window visible on the availability endpoint (cache invalidated)', function (): void {
    $gate = Gate::factory()->create();
    $date = now()->toDateString();
    Sanctum::actingAs(plannerUser());

    // Prime the (empty) availability cache for this gate/date.
    getJson("/api/v1/slots/availability?gate={$gate->id}&date={$date}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    postJson('/api/v1/slots', openPayload($gate))->assertCreated();

    getJson("/api/v1/slots/availability?gate={$gate->id}&date={$date}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
