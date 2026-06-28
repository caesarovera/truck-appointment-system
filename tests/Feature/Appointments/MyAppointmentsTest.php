<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/** Transporter + N appointment milik company-nya. */
function transporterWithAppointments(int $count = 2, string $status = 'CONFIRMED'): array
{
    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');

    $appointments = Appointment::factory()->count($count)->create([
        'company_id' => $company->id,
        'slot_window_id' => SlotWindow::factory()->create()->id,
        'status' => $status,
    ]);

    return ['user' => $user, 'company' => $company, 'appointments' => $appointments];
}

it('lists only the transporter own company appointments', function (): void {
    ['user' => $user] = transporterWithAppointments(count: 2);
    // Appointment company lain → tidak boleh bocor.
    Appointment::factory()->create(['slot_window_id' => SlotWindow::factory()->create()->id]);

    Sanctum::actingAs($user);

    getJson('/api/v1/me/appointments')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'booking_code', 'status', 'version', 'slot_window']]]);
});

it('filters by status', function (): void {
    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');

    Appointment::factory()->create(['company_id' => $company->id, 'slot_window_id' => SlotWindow::factory()->create()->id, 'status' => 'CONFIRMED']);
    Appointment::factory()->create(['company_id' => $company->id, 'slot_window_id' => SlotWindow::factory()->create()->id, 'status' => 'CANCELLED']);

    Sanctum::actingAs($user);

    getJson('/api/v1/me/appointments?status=CANCELLED')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'CANCELLED');
});

it('rejects an invalid status filter (422)', function (): void {
    ['user' => $user] = transporterWithAppointments();
    Sanctum::actingAs($user);

    getJson('/api/v1/me/appointments?status=NOPE')->assertStatus(422);
});

it('forbids a planner without a company (403)', function (): void {
    $planner = User::factory()->create(['company_id' => null]);
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson('/api/v1/me/appointments')->assertForbidden();
});

it('forbids a driver lacking appointment.read (403)', function (): void {
    $company = TransportCompany::factory()->create();
    $driver = User::factory()->create(['company_id' => $company->id]);
    $driver->assignRole('driver');
    Sanctum::actingAs($driver);

    getJson('/api/v1/me/appointments')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/me/appointments')->assertUnauthorized();
});
