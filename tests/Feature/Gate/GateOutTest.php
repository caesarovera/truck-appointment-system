<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\GateTransactionType;
use App\Events\TruckGatedOut;
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
 * @return array{officer: User, appointment: Appointment}
 */
function gateOutScenario(string $status = 'IN_PROGRESS'): array
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

    // Gate-in already happened for an in-progress appointment.
    if ($status === 'IN_PROGRESS' || $status === 'COMPLETED') {
        GateTransaction::factory()->create(['appointment_id' => $appointment->id]);
    }

    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');

    return compact('officer', 'appointment');
}

it('gates out an in-progress appointment, records an OUT transaction and completes it', function (): void {
    Event::fake([TruckGatedOut::class]);
    ['officer' => $officer, 'appointment' => $appointment] = gateOutScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-out")
        ->assertOk()
        ->assertJsonPath('data.status', 'COMPLETED');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::COMPLETED);

    $tx = GateTransaction::query()->where('appointment_id', $appointment->id)->where('type', 'OUT')->first();
    expect($tx)->not->toBeNull()
        ->and($tx->type)->toBe(GateTransactionType::OUT)
        ->and($tx->processed_by)->toBe($officer->id);

    Event::assertDispatched(TruckGatedOut::class);
});

it('is idempotent: a second gate-out does not create a duplicate transaction', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = gateOutScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-out")->assertOk();
    postJson("/api/v1/appointments/{$appointment->id}/gate-out")
        ->assertOk()
        ->assertJsonPath('data.status', 'COMPLETED');

    expect(GateTransaction::query()->where('appointment_id', $appointment->id)->where('type', 'OUT')->count())->toBe(1);
});

it('refuses to gate-out an appointment that is not in progress (409)', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = gateOutScenario(status: 'CONFIRMED');
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-out")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_state');

    expect(GateTransaction::query()->where('appointment_id', $appointment->id)->where('type', 'OUT')->exists())->toBeFalse();
});

it('forbids a gate officer from another terminal (403)', function (): void {
    ['appointment' => $appointment] = gateOutScenario();
    $outsider = User::factory()->create(['terminal_id' => Terminal::factory()->create()->id]);
    $outsider->assignRole('gate-officer');
    Sanctum::actingAs($outsider);

    postJson("/api/v1/appointments/{$appointment->id}/gate-out")->assertForbidden();
});
