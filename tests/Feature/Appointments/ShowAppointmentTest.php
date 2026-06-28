<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/** Bangun appointment lengkap; bisa ditambat ke terminal & company tertentu. */
function makeAppointment(?Terminal $terminal = null, ?TransportCompany $company = null, ?User $driver = null): Appointment
{
    $terminal ??= Terminal::factory()->create();
    $gate = Gate::factory()->for($terminal)->create();
    $window = SlotWindow::factory()->for($gate)->create();

    $overrides = ['slot_window_id' => $window->id];
    if ($company !== null) {
        $overrides['company_id'] = $company->id;
    }
    if ($driver !== null) {
        $overrides['driver_id'] = $driver->id;
    }

    return Appointment::factory()->create($overrides);
}

function userWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('lets a transporter view an appointment of their own company', function (): void {
    $company = TransportCompany::factory()->create();
    $user = userWithRole('transporter', ['company_id' => $company->id]);
    $appointment = makeAppointment(company: $company);

    Sanctum::actingAs($user);

    getJson("/api/v1/appointments/{$appointment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $appointment->id);
});

it('forbids a transporter from viewing another company appointment (403)', function (): void {
    $user = userWithRole('transporter', ['company_id' => TransportCompany::factory()->create()->id]);
    $appointment = makeAppointment(company: TransportCompany::factory()->create());

    Sanctum::actingAs($user);

    getJson("/api/v1/appointments/{$appointment->id}")->assertForbidden();
});

it('lets a driver view only their assigned appointment', function (): void {
    $driver = userWithRole('driver');
    $own = makeAppointment(driver: $driver);
    $foreign = makeAppointment();

    Sanctum::actingAs($driver);

    getJson("/api/v1/appointments/{$own->id}")->assertOk();
    getJson("/api/v1/appointments/{$foreign->id}")->assertForbidden();
});

it('lets a gate officer view appointments at their assigned terminal only', function (): void {
    $terminal = Terminal::factory()->create();
    $officer = userWithRole('gate-officer', ['terminal_id' => $terminal->id]);

    $here = makeAppointment(terminal: $terminal);
    $elsewhere = makeAppointment(terminal: Terminal::factory()->create());

    Sanctum::actingAs($officer);

    getJson("/api/v1/appointments/{$here->id}")->assertOk();
    getJson("/api/v1/appointments/{$elsewhere->id}")->assertForbidden();
});

it('lets admin and planner view any appointment', function (): void {
    $appointment = makeAppointment();

    Sanctum::actingAs(userWithRole('admin'));
    getJson("/api/v1/appointments/{$appointment->id}")->assertOk();

    Sanctum::actingAs(userWithRole('planner'));
    getJson("/api/v1/appointments/{$appointment->id}")->assertOk();
});

it('requires authentication (401)', function (): void {
    $appointment = makeAppointment();

    getJson("/api/v1/appointments/{$appointment->id}")->assertUnauthorized();
});

it('returns 404 for a missing appointment', function (): void {
    Sanctum::actingAs(userWithRole('planner'));

    getJson('/api/v1/appointments/999999')->assertNotFound();
});
