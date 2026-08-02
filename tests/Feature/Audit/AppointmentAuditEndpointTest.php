<?php

declare(strict_types=1);

use App\Jobs\NoShowSweepJob;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

/*
|--------------------------------------------------------------------------
| GET /api/v1/appointments/{id}/audit
|--------------------------------------------------------------------------
| Menutup janji matriks §1 baris "Lihat audit log" (admin ✅ · planner sebagian ·
| transporter company sendiri) yang selama ini cuma desain: permission audit.read
| SUDAH diberikan ke planner & transporter di seeder, tapi tak ada satu pun rute
| untuk membacanya. Log direkam, tak pernah bisa dibaca siapa pun.
|
| Otorisasi DUA LAPIS, dan lapis keduanya yang penting: gate-officer & driver
| lolos AppointmentPolicy::view untuk appointment mereka sendiri, tapi TIDAK
| punya audit.read. Tanpa cek permission di FormRequest, keduanya akan bisa
| membaca audit trail — termasuk nama orang yang mengubahnya.
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    travelTo(today()->setTime(10, 0));
});

/**
 * @return array{appointment: Appointment, company: TransportCompany, terminal: Terminal, gate: Gate, transporter: User}
 */
function auditEndpointScenario(): array
{
    $terminal = Terminal::factory()->create();
    $gate = Gate::factory()->create(['terminal_id' => $terminal->id]);
    $window = SlotWindow::factory()->ongoing()->create([
        'gate_id' => $gate->id, 'capacity' => 5, 'booked_count' => 1,
    ]);

    $company = TransportCompany::factory()->create();
    $appointment = Appointment::factory()->confirmed()->create([
        'company_id' => $company->id,
        'truck_id' => Truck::factory()->create(['company_id' => $company->id])->id,
        'driver_id' => User::factory()->driver()->create(['company_id' => $company->id])->id,
        'slot_window_id' => $window->id,
    ]);

    $transporter = User::factory()->create(['company_id' => $company->id]);
    $transporter->assignRole('transporter');

    return compact('appointment', 'company', 'terminal', 'gate', 'transporter');
}

it('returns the audit trail of an own-company appointment to a transporter', function (): void {
    ['appointment' => $appointment, 'transporter' => $transporter] = auditEndpointScenario();
    Sanctum::actingAs($transporter);

    // Satu perubahan nyata dulu supaya trail-nya bukan cuma entri pembuatan.
    postJson("/api/v1/appointments/{$appointment->id}/cancel", ['version' => $appointment->version])
        ->assertOk();

    $response = getJson("/api/v1/appointments/{$appointment->id}/audit")->assertOk();

    // Urut kronologis: dibuat dulu, baru dibatalkan.
    $response->assertJsonPath('data.0.event', 'created')
        ->assertJsonPath('data.1.event', 'updated')
        ->assertJsonPath('data.1.changes.new.status', 'CANCELLED')
        ->assertJsonPath('data.1.changes.old.status', 'CONFIRMED')
        ->assertJsonPath('data.1.causer.id', $transporter->id)
        ->assertJsonPath('data.1.causer.name', $transporter->name);

    expect($response->json('data.1'))->toHaveKeys(['id', 'event', 'changes', 'causer', 'created_at']);
});

it('exposes a system action as a null causer', function (): void {
    ['appointment' => $appointment, 'gate' => $gate, 'transporter' => $transporter] = auditEndpointScenario();
    $ended = SlotWindow::factory()->create([
        'gate_id' => $gate->id,
        'date' => today()->toDateString(),
        'start_time' => '06:00:00',
        'end_time' => '07:00:00',
        'capacity' => 5,
        'booked_count' => 1,
    ]);
    $appointment->slot_window_id = $ended->id;
    $appointment->save();

    NoShowSweepJob::dispatchSync();
    Sanctum::actingAs($transporter);

    $data = getJson("/api/v1/appointments/{$appointment->id}/audit")->assertOk()->json('data');
    $noShow = collect($data)->firstWhere('changes.new.status', 'NO_SHOW');

    // NULL = sistem, bukan orang. Itu yang membedakan "sistem menyapu no-show"
    // dari "seseorang membatalkan" saat trail ini dipakai menyelesaikan sengketa.
    expect($noShow)->not->toBeNull()
        ->and($noShow['causer'])->toBeNull();
});

it('forbids a transporter from reading another company audit trail (403)', function (): void {
    ['appointment' => $appointment] = auditEndpointScenario();
    $outsider = User::factory()->create(['company_id' => TransportCompany::factory()->create()->id]);
    $outsider->assignRole('transporter');
    Sanctum::actingAs($outsider);

    getJson("/api/v1/appointments/{$appointment->id}/audit")->assertForbidden();
});

it('lets a planner read the trail across companies', function (): void {
    ['appointment' => $appointment] = auditEndpointScenario();
    $planner = User::factory()->create();
    $planner->assignRole('planner');
    Sanctum::actingAs($planner);

    getJson("/api/v1/appointments/{$appointment->id}/audit")->assertOk();
});

it('forbids the assigned driver even though the policy would let them view it (403)', function (): void {
    ['appointment' => $appointment] = auditEndpointScenario();
    // Sopir appointment INI: AppointmentPolicy::view meluluskannya. Yang menolak
    // di sini murni lapis kedua — driver tak punya audit.read.
    $driver = $appointment->driver;
    Sanctum::actingAs($driver);

    getJson("/api/v1/appointments/{$appointment->id}/audit")->assertForbidden();
});

it('forbids the gate officer of the same terminal (403)', function (): void {
    ['appointment' => $appointment, 'terminal' => $terminal] = auditEndpointScenario();
    // Sama: Policy meluluskan (terminalnya cocok), permission yang menolak.
    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');
    Sanctum::actingAs($officer);

    getJson("/api/v1/appointments/{$appointment->id}/audit")->assertForbidden();
});

it('requires authentication (401)', function (): void {
    ['appointment' => $appointment] = auditEndpointScenario();

    getJson("/api/v1/appointments/{$appointment->id}/audit")->assertUnauthorized();
});
