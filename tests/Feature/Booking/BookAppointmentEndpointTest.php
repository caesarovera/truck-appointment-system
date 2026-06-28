<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

/**
 * @return array{user: User, payload: array<string, mixed>, window: SlotWindow}
 */
function transporterBookingContext(int $capacity = 5, int $booked = 0, string $status = 'OPEN'): array
{
    seed(RolePermissionSeeder::class);

    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');

    $truck = Truck::factory()->create(['company_id' => $company->id]);
    $driver = User::factory()->create(['company_id' => $company->id]);
    $window = SlotWindow::factory()->create([
        'capacity' => $capacity,
        'booked_count' => $booked,
        'status' => $status,
    ]);

    return [
        'user' => $user,
        'window' => $window,
        'payload' => [
            'slot_window_id' => $window->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'move_type' => 'DELIVERY',
            'container_no' => 'MAUU1234567',
            'iso_type' => '22G1',
            'size' => 20,
        ],
    ];
}

it('lets a transporter book an available slot (201)', function (): void {
    ['user' => $user, 'payload' => $payload, 'window' => $window] = transporterBookingContext();
    Sanctum::actingAs($user);

    $response = postJson('/api/v1/appointments', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'CONFIRMED')
        ->assertJsonPath('data.move_type', 'DELIVERY')
        ->assertJsonPath('data.slot_window.remaining', $window->capacity - 1);

    expect(Appointment::query()->count())->toBe(1)
        ->and($window->fresh()->booked_count)->toBe(1);
});

it('returns 409 when the window is full', function (): void {
    ['user' => $user, 'payload' => $payload] = transporterBookingContext(capacity: 2, booked: 2);
    Sanctum::actingAs($user);

    postJson('/api/v1/appointments', $payload)
        ->assertStatus(409)
        ->assertJsonPath('error', 'slot_unavailable');

    expect(Appointment::query()->count())->toBe(0);
});

it('replays the same response for a repeated Idempotency-Key (no double booking)', function (): void {
    ['user' => $user, 'payload' => $payload] = transporterBookingContext();
    Sanctum::actingAs($user);

    $headers = ['Idempotency-Key' => 'book-once-123'];

    $first = postJson('/api/v1/appointments', $payload, $headers)->assertCreated();
    $second = postJson('/api/v1/appointments', $payload, $headers)->assertCreated();

    // Booking yang sama → satu appointment, respons kedua diputar ulang.
    expect(Appointment::query()->count())->toBe(1);
    $second->assertHeader('Idempotent-Replayed', 'true');
    expect($second->json('data.id'))->toBe($first->json('data.id'));
});

it('forbids a driver (no appointment.write) from booking (403)', function (): void {
    ['payload' => $payload] = transporterBookingContext();
    $driverUser = User::factory()->create();
    $driverUser->assignRole('driver');
    Sanctum::actingAs($driverUser);

    postJson('/api/v1/appointments', $payload)->assertForbidden();
});

it('requires authentication (401)', function (): void {
    ['payload' => $payload] = transporterBookingContext();

    postJson('/api/v1/appointments', $payload)->assertUnauthorized();
});

it('rejects a truck from another company via validation (422)', function (): void {
    ['user' => $user, 'payload' => $payload] = transporterBookingContext();
    Sanctum::actingAs($user);

    $foreignTruck = Truck::factory()->create(); // company lain (factory bikin company baru)
    $payload['truck_id'] = $foreignTruck->id;

    postJson('/api/v1/appointments', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('truck_id');
});
