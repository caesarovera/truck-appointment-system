<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Container;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/**
 * @return array{user: User, company: TransportCompany, appointment: Appointment, window: SlotWindow}
 */
function cancelScenario(string $status = 'CONFIRMED'): array
{
    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');

    $window = SlotWindow::factory()->create(['capacity' => 5, 'booked_count' => 1]);
    $appointment = Appointment::factory()->create([
        'company_id' => $company->id,
        'slot_window_id' => $window->id,
        'status' => $status,
    ]);
    Container::factory()->create([
        'appointment_id' => $appointment->id,
        'slot_window_id' => $window->id,
    ]);

    return compact('user', 'company', 'appointment', 'window');
}

it('cancels a confirmed appointment and returns the quota', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'window' => $window] = cancelScenario();
    Sanctum::actingAs($user);

    postJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CANCELLED)
        ->and($window->fresh()->booked_count)->toBe(0);

    // Container dilepas dari window → bisa dibooking ulang.
    expect(Container::query()->where('appointment_id', $appointment->id)->value('slot_window_id'))->toBeNull();
});

it('refuses to cancel after the truck has arrived (409)', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'window' => $window] = cancelScenario(status: 'ARRIVED');
    Sanctum::actingAs($user);

    postJson("/api/v1/appointments/{$appointment->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_state');

    expect($window->fresh()->booked_count)->toBe(1); // kuota tidak dikembalikan
});

it('forbids cancelling another company appointment (403)', function (): void {
    ['appointment' => $appointment] = cancelScenario();
    $outsider = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $outsider->assignRole('transporter');
    Sanctum::actingAs($outsider);

    postJson("/api/v1/appointments/{$appointment->id}/cancel")->assertForbidden();
});

it('lets a planner cancel cross-company (override)', function (): void {
    ['appointment' => $appointment] = cancelScenario();
    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    postJson("/api/v1/appointments/{$appointment->id}/cancel")->assertOk();
});
