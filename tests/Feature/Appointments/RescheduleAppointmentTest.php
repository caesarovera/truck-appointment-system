<?php

declare(strict_types=1);

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
 * @return array{user: User, appointment: Appointment, from: SlotWindow, to: SlotWindow}
 */
function rescheduleScenario(int $toBooked = 0, int $toCapacity = 5, string $status = 'CONFIRMED'): array
{
    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');

    $from = SlotWindow::factory()->create(['capacity' => 5, 'booked_count' => 1]);
    $to = SlotWindow::factory()->create(['capacity' => $toCapacity, 'booked_count' => $toBooked]);

    $appointment = Appointment::factory()->create([
        'company_id' => $company->id,
        'slot_window_id' => $from->id,
        'status' => $status,
        'version' => 1,
    ]);
    Container::factory()->create([
        'appointment_id' => $appointment->id,
        'slot_window_id' => $from->id,
    ]);

    return compact('user', 'appointment', 'from', 'to');
}

it('moves the appointment to a new window, shifting quota and bumping version', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'from' => $from, 'to' => $to] = rescheduleScenario();
    Sanctum::actingAs($user);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $to->id,
        'version' => 1,
    ])->assertOk()->assertJsonPath('data.version', 2);

    $fresh = $appointment->fresh();
    expect($fresh->slot_window_id)->toBe($to->id)
        ->and($fresh->version)->toBe(2)
        ->and($from->fresh()->booked_count)->toBe(0)
        ->and($to->fresh()->booked_count)->toBe(1);

    // Container ikut pindah window.
    expect(Container::query()->where('appointment_id', $appointment->id)->value('slot_window_id'))->toBe($to->id);
});

it('rejects rescheduling into a window that has already ended (409)', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'from' => $from] = rescheduleScenario();
    Sanctum::actingAs($user);

    // Window tujuan sudah berakhir kemarin — tanpa guard, appointment pindah
    // ke sana lalu langsung disapu NO_SHOW oleh sweep.
    $ended = SlotWindow::factory()->create([
        'date' => now()->subDay()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $ended->id,
        'version' => 1,
    ])->assertStatus(409)->assertJsonPath('error', 'slot_unavailable');

    // Tidak ada yang berpindah.
    expect($appointment->fresh()->slot_window_id)->toBe($from->id)
        ->and($ended->fresh()->booked_count)->toBe(0);
});

it('rejects a stale version (409 optimistic lock)', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'to' => $to, 'from' => $from] = rescheduleScenario();
    Sanctum::actingAs($user);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $to->id,
        'version' => 99,
    ])->assertStatus(409)->assertJsonPath('error', 'version_conflict');

    // Tidak ada yang berpindah.
    expect($appointment->fresh()->slot_window_id)->toBe($from->id)
        ->and($to->fresh()->booked_count)->toBe(0);
});

it('rejects rescheduling into a full window (409)', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'to' => $to] = rescheduleScenario(toBooked: 5, toCapacity: 5);
    Sanctum::actingAs($user);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $to->id,
        'version' => 1,
    ])->assertStatus(409)->assertJsonPath('error', 'slot_unavailable');
});

it('refuses to reschedule once the appointment is completed (409)', function (): void {
    ['user' => $user, 'appointment' => $appointment, 'to' => $to] = rescheduleScenario(status: 'COMPLETED');
    Sanctum::actingAs($user);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $to->id,
        'version' => 1,
    ])->assertStatus(409)->assertJsonPath('error', 'invalid_state');
});

it('forbids rescheduling another company appointment (403)', function (): void {
    ['appointment' => $appointment, 'to' => $to] = rescheduleScenario();
    $outsider = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $outsider->assignRole('transporter');
    Sanctum::actingAs($outsider);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $to->id,
        'version' => 1,
    ])->assertForbidden();
});
