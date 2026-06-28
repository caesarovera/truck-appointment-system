<?php

declare(strict_types=1);

use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

/** @return array{user: User, truck: Truck, driver: User, window: SlotWindow} */
function bookingRateLimitContext(): array
{
    seed(RolePermissionSeeder::class);

    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');

    return [
        'user' => $user,
        'truck' => Truck::factory()->create(['company_id' => $company->id]),
        'driver' => User::factory()->create(['company_id' => $company->id]),
        'window' => SlotWindow::factory()->create(['capacity' => 100, 'booked_count' => 0, 'status' => 'OPEN']),
    ];
}

it('throttles a single transporter once the per-user booking limit is exceeded (429)', function (): void {
    ['user' => $user, 'truck' => $truck, 'driver' => $driver, 'window' => $window] = bookingRateLimitContext();
    Sanctum::actingAs($user, ['*']);

    $limit = (int) config('tas.rate_limits.booking');

    // Container unik tiap request supaya tidak kena duplicate; kapasitas cukup.
    $book = fn (int $n) => postJson('/api/v1/appointments', [
        'slot_window_id' => $window->id,
        'truck_id' => $truck->id,
        'driver_id' => $driver->id,
        'move_type' => 'DELIVERY',
        'container_no' => sprintf('MAUU%07d', $n),
    ]);

    for ($i = 1; $i <= $limit; $i++) {
        $book($i)->assertCreated();
    }

    $book($limit + 1)->assertStatus(429);
});
