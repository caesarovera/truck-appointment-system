<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\GateTransactionType;
use App\Events\TruckGatedIn;
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
 * @return array{officer: User, appointment: Appointment, window: SlotWindow, terminal: Terminal}
 */
function gateInScenario(string $status = 'CONFIRMED'): array
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

    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');

    return compact('officer', 'appointment', 'window', 'terminal');
}

it('gates in a confirmed appointment, records an IN transaction and lands IN_PROGRESS', function (): void {
    Event::fake([TruckGatedIn::class]);
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::IN_PROGRESS);

    $tx = GateTransaction::query()->where('appointment_id', $appointment->id)->where('type', 'IN')->first();
    expect($tx)->not->toBeNull()
        ->and($tx->type)->toBe(GateTransactionType::IN)
        ->and($tx->processed_by)->toBe($officer->id);

    Event::assertDispatched(TruckGatedIn::class);
});

it('is idempotent: a second gate-in does not create a duplicate transaction', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertOk();
    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');

    expect(GateTransaction::query()->where('appointment_id', $appointment->id)->where('type', 'IN')->count())->toBe(1);
});

it('refuses to gate-in an appointment that is not yet confirmed (409)', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(status: 'BOOKED');
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_state');

    expect(GateTransaction::query()->where('appointment_id', $appointment->id)->exists())->toBeFalse();
});

it('refuses to gate-in a no-show appointment (409)', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(status: 'NO_SHOW');
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertStatus(409);
});

it('forbids a gate officer from another terminal (403)', function (): void {
    ['appointment' => $appointment] = gateInScenario();
    $outsider = User::factory()->create(['terminal_id' => Terminal::factory()->create()->id]);
    $outsider->assignRole('gate-officer');
    Sanctum::actingAs($outsider);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertForbidden();
});

it('forbids a transporter from gating in (403)', function (): void {
    ['appointment' => $appointment] = gateInScenario();
    $transporter = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertForbidden();
});
