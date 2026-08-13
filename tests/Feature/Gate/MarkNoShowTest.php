<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Events\AppointmentNoShow;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\GateTransaction;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

/**
 * @return array{officer: User, appointment: Appointment, window: SlotWindow}
 */
function markNoShowScenario(string $status = 'CONFIRMED'): array
{
    $terminal = Terminal::factory()->create();
    $gate = Gate::factory()->create(['terminal_id' => $terminal->id]);
    $window = SlotWindow::factory()->create(['gate_id' => $gate->id, 'capacity' => 5, 'booked_count' => 1]);

    $company = TransportCompany::factory()->create();
    $appointment = Appointment::factory()->create([
        'company_id' => $company->id,
        'slot_window_id' => $window->id,
        'status' => $status,
    ]);

    if ($status === 'IN_PROGRESS' || $status === 'COMPLETED') {
        GateTransaction::factory()->create(['appointment_id' => $appointment->id]);
    }

    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');

    return compact('officer', 'appointment', 'window');
}

it('marks a confirmed appointment no-show and returns its quota (BUSINESS-FLOW §3.5)', function (): void {
    Event::fake([AppointmentNoShow::class]);
    ['officer' => $officer, 'appointment' => $appointment, 'window' => $window] = markNoShowScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', 'NO_SHOW');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NO_SHOW)
        ->and($window->fresh()->booked_count)->toBe(0);

    Event::assertDispatched(AppointmentNoShow::class);
});

it('marks a booked (not yet confirmed) appointment no-show too', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = markNoShowScenario(status: 'BOOKED');
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', 'NO_SHOW');
});

it('refuses to mark no-show a truck already inside (409)', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = markNoShowScenario(status: 'IN_PROGRESS');
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/no-show")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_state');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::IN_PROGRESS);
});

it('refuses to mark no-show an appointment already completed or cancelled (409)', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = markNoShowScenario(status: 'CANCELLED');
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/no-show")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_state');
});

it('forbids a gate officer from another terminal (403)', function (): void {
    ['appointment' => $appointment] = markNoShowScenario();
    $outsider = User::factory()->create(['terminal_id' => Terminal::factory()->create()->id]);
    $outsider->assignRole('gate-officer');
    Sanctum::actingAs($outsider);

    postJson("/api/v1/appointments/{$appointment->id}/no-show")->assertForbidden();
});

it('forbids a transporter from marking no-show', function (): void {
    ['appointment' => $appointment] = markNoShowScenario();
    $transporter = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    postJson("/api/v1/appointments/{$appointment->id}/no-show")->assertForbidden();
});
