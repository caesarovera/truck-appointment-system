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
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    // CATATAN Pest: $this->seed(...) di closure function(): void — global seed()
    // hanya bekerja di arrow-fn.
    $this->seed(RolePermissionSeeder::class);

    // Guard gate-in adalah aturan berbasis jam, jadi jamnya dibekukan: test batas
    // toleransi harus deterministik, dan tengah hari menjauhkannya dari batas
    // tengah malam (di mana "1 jam lalu" jatuh ke tanggal lain).
    travelTo(today()->setTime(10, 0));
});

/**
 * @param  array<string, mixed>  $windowAttrs
 * @return array{officer: User, appointment: Appointment, window: SlotWindow, terminal: Terminal}
 */
function gateInScenario(string $status = 'CONFIRMED', array $windowAttrs = []): array
{
    $terminal = Terminal::factory()->create();
    $gate = Gate::factory()->create(['terminal_id' => $terminal->id]);
    $window = SlotWindow::factory()->ongoing()->create(array_merge(
        ['gate_id' => $gate->id, 'capacity' => 5, 'booked_count' => 1],
        $windowAttrs,
    ));

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

/*
|--------------------------------------------------------------------------
| Toleransi jendela waktu (BUSINESS-FLOW §2 & §3.5, PRD §4)
|--------------------------------------------------------------------------
| Truk hanya boleh masuk di sekitar jendelanya. Tanpa guard ini status CONFIRMED
| saja sudah cukup — appointment minggu depan bisa gate-in hari ini, dan kuota
| jam sibuk dipakai truk yang datang kapan saja (laporan utilisasi jadi bohong).
| Jam dibekukan di 10:00 oleh beforeEach.
*/

it('refuses gate-in before the early tolerance opens (409 gate_in_too_early)', function (): void {
    config(['tas.gate_in.early_minutes' => 30]);
    // Window 12:00–13:00 → paling awal boleh masuk 11:30, sekarang baru 10:00.
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        windowAttrs: ['start_time' => '12:00:00', 'end_time' => '13:00:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertStatus(409)
        ->assertJsonPath('error', 'gate_in_too_early');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CONFIRMED)
        ->and(GateTransaction::query()->where('appointment_id', $appointment->id)->exists())->toBeFalse();
});

it('allows gate-in exactly at the early tolerance boundary', function (): void {
    config(['tas.gate_in.early_minutes' => 30]);
    // Window 10:30 → batas paling awal tepat 10:00 = sekarang. Batas inklusif.
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        windowAttrs: ['start_time' => '10:30:00', 'end_time' => '11:30:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');
});

it('refuses gate-in after the late tolerance expires (409 gate_in_too_late)', function (): void {
    config(['tas.gate_in.late_minutes' => 30]);
    // Window berakhir 08:30 → tenggat 09:00, sekarang sudah 10:00.
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        windowAttrs: ['start_time' => '08:00:00', 'end_time' => '08:30:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertStatus(409)
        ->assertJsonPath('error', 'gate_in_too_late');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CONFIRMED)
        ->and(GateTransaction::query()->where('appointment_id', $appointment->id)->exists())->toBeFalse();
});

it('allows gate-in exactly at the late tolerance boundary', function (): void {
    config(['tas.gate_in.late_minutes' => 30]);
    // Window berakhir 09:30 → tenggat tepat 10:00 = sekarang. Batas inklusif.
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        windowAttrs: ['start_time' => '09:00:00', 'end_time' => '09:30:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertOk();
});

it('takes the tolerance from config instead of hardcoding it', function (): void {
    // Window yang sama seperti test "too early", tapi terminal ini memberi
    // toleransi 3 jam → truk yang sama kini diterima. PRD §4: toleransi dari config.
    config(['tas.gate_in.early_minutes' => 180]);
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        windowAttrs: ['start_time' => '12:00:00', 'end_time' => '13:00:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertOk();
});

it('stays idempotent for a truck already inside even after the tolerance expires', function (): void {
    config(['tas.gate_in.late_minutes' => 30]);
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        windowAttrs: ['start_time' => '09:00:00', 'end_time' => '11:00:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertOk();

    // Truk sudah di dalam; retry/double-tap yang telat TIDAK boleh berubah jadi
    // error — guard idempoten harus menang atas guard waktu.
    travelTo(today()->setTime(23, 0));

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');

    expect(GateTransaction::query()->where('appointment_id', $appointment->id)->where('type', 'IN')->count())->toBe(1);
});

it('reports invalid_state (not a timing error) when the status is wrong too', function (): void {
    config(['tas.gate_in.early_minutes' => 30]);
    // Status salah DAN waktu salah: state machine dilanggar lebih dulu, jadi
    // pesannya invalid_state. Urutan guard ini dikunci di sini supaya tak terbalik.
    ['officer' => $officer, 'appointment' => $appointment] = gateInScenario(
        status: 'BOOKED',
        windowAttrs: ['start_time' => '12:00:00', 'end_time' => '13:00:00'],
    );
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")
        ->assertStatus(409)
        ->assertJsonPath('error', 'invalid_state');
});
