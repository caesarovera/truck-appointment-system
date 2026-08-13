<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\TransportCompany;
use App\Models\User;
use App\Services\AppointmentQrTokenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/** Appointment lengkap dengan window ->ongoing() (valid utk QR), ditambat ke terminal tertentu. */
function makeAppointmentWithQrWindow(?Terminal $terminal = null): Appointment
{
    $terminal ??= Terminal::factory()->create();
    $gate = Gate::factory()->for($terminal)->create();
    $window = SlotWindow::factory()->for($gate)->ongoing()->create();

    return Appointment::factory()->create(['slot_window_id' => $window->id]);
}

function userWithRoleForQr(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('lets a gate officer at the matching terminal verify a valid QR token', function (): void {
    $terminal = Terminal::factory()->create();
    $officer = userWithRoleForQr('gate-officer', ['terminal_id' => $terminal->id]);
    $appointment = makeAppointmentWithQrWindow($terminal);
    $appointment->load('slotWindow');

    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    Sanctum::actingAs($officer);

    getJson('/api/v1/appointments/qr/'.$token)
        ->assertOk()
        ->assertJsonPath('data.id', $appointment->id);
});

it('forbids a gate officer at a different terminal even with a valid token (403)', function (): void {
    $appointment = makeAppointmentWithQrWindow();
    $appointment->load('slotWindow');
    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    $officer = userWithRoleForQr('gate-officer', ['terminal_id' => Terminal::factory()->create()->id]);
    Sanctum::actingAs($officer);

    getJson('/api/v1/appointments/qr/'.$token)->assertForbidden();
});

it('rejects a tampered QR token (403)', function (): void {
    $appointment = makeAppointmentWithQrWindow();
    $appointment->load('slotWindow');
    $token = app(AppointmentQrTokenService::class)->generate($appointment);
    [$payload] = explode('.', $token, 2);
    $tampered = $payload.'.'.str_repeat('a', 64);

    Sanctum::actingAs(userWithRoleForQr('planner'));

    getJson('/api/v1/appointments/qr/'.$tampered)
        ->assertForbidden()
        ->assertJsonPath('error', 'invalid_qr_token');
});

it('rejects an expired QR token (403)', function (): void {
    $appointment = makeAppointmentWithQrWindow();
    $appointment->load('slotWindow');
    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    Carbon::setTestNow(now()->addHours(2));
    Sanctum::actingAs(userWithRoleForQr('planner'));

    getJson('/api/v1/appointments/qr/'.$token)->assertForbidden();

    Carbon::setTestNow();
});

it('rejects a garbage QR token string (403)', function (): void {
    Sanctum::actingAs(userWithRoleForQr('planner'));

    getJson('/api/v1/appointments/qr/complete-nonsense')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    $appointment = makeAppointmentWithQrWindow();
    $appointment->load('slotWindow');
    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    getJson('/api/v1/appointments/qr/'.$token)->assertUnauthorized();
});

it('lets a transporter view their own company appointment via QR', function (): void {
    $company = TransportCompany::factory()->create();
    $transporter = userWithRoleForQr('transporter', ['company_id' => $company->id]);

    $terminal = Terminal::factory()->create();
    $gate = Gate::factory()->for($terminal)->create();
    $window = SlotWindow::factory()->for($gate)->ongoing()->create();
    $appointment = Appointment::factory()->create(['slot_window_id' => $window->id, 'company_id' => $company->id]);
    $appointment->load('slotWindow');

    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    Sanctum::actingAs($transporter);

    getJson('/api/v1/appointments/qr/'.$token)->assertOk();
});
