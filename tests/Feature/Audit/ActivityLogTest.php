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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

/*
|--------------------------------------------------------------------------
| Audit trail (Activity Log)
|--------------------------------------------------------------------------
| DoD di CLAUDE.md mensyaratkan "perubahan status tercatat di Activity Log",
| dan BUSINESS-FLOW §3.7 menjadikannya SUMBER KEBENARAN audit. Sampai file ini
| ada, NOL test menjaganya: kalau trait LogsActivity atau logOnly([...]) di
| Appointment hilang, seluruh gerbang tetap hijau dan audit trail diam-diam
| kosong — persis kelas kegagalan yang paling mahal ditemukan belakangan.
*/

beforeEach(function (): void {
    // CATATAN Pest: $this->seed(...) di closure function(): void — global seed()
    // hanya bekerja di arrow-fn.
    $this->seed(RolePermissionSeeder::class);
    travelTo(today()->setTime(10, 0));
});

/**
 * @return array{officer: User, transporter: User, appointment: Appointment, window: SlotWindow, gate: Gate, company: TransportCompany}
 */
function auditScenario(): array
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

    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');

    $transporter = User::factory()->create(['company_id' => $company->id]);
    $transporter->assignRole('transporter');

    return compact('officer', 'transporter', 'appointment', 'window', 'gate', 'company');
}

/** @return Collection<int, Activity> */
function auditTrailFor(Appointment $appointment)
{
    return Activity::query()
        ->where('log_name', 'appointment')
        ->where('subject_type', $appointment->getMorphClass())
        ->where('subject_id', $appointment->getKey())
        ->orderBy('id')
        ->get();
}

/**
 * Hanya entri PERUBAHAN. Pembuatan appointment juga tercatat (event `created`),
 * jadi menghitung seluruh trail akan mencampur "lahir" dengan "berubah".
 *
 * @return Collection<int, Activity>
 */
function auditUpdatesFor(Appointment $appointment)
{
    return auditTrailFor($appointment)->where('event', 'updated')->values();
}

it('records the booking itself, attributed to the transporter who made it', function (): void {
    ['transporter' => $transporter, 'window' => $window, 'company' => $company] = auditScenario();
    $truck = Truck::factory()->create(['company_id' => $company->id]);
    $driver = User::factory()->driver()->create(['company_id' => $company->id]);
    Sanctum::actingAs($transporter);

    $response = postJson('/api/v1/appointments', [
        'slot_window_id' => $window->id,
        'truck_id' => $truck->id,
        'driver_id' => $driver->id,
        'move_type' => 'DELIVERY',
        'container_no' => 'TASU1234567',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $booked = Appointment::query()->findOrFail($response->json('data.id'));
    $trail = auditTrailFor($booked);

    // Titik awal rantai audit: tanpa entri `created` ber-causer, tak ada cara
    // menjawab "siapa yang membooking slot ini?".
    expect($trail)->toHaveCount(1)
        ->and($trail[0]->event)->toBe('created')
        ->and($trail[0]->properties['attributes']['status'])->toBe('CONFIRMED')
        ->and((int) $trail[0]->causer_id)->toBe($transporter->id);
});

it('records both status transitions of a gate-in, attributed to the officer', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = auditScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertOk();

    $trail = auditUpdatesFor($appointment);

    // AppointmentRepository::recordGateIn sengaja menyimpan DUA save
    // (CONFIRMED→ARRIVED→IN_PROGRESS) supaya transisi perantara tak hilang dari
    // audit. Komentarnya sudah lama mengklaim itu; di sinilah klaimnya dikunci.
    expect($trail)->toHaveCount(2)
        ->and($trail[0]->properties['old']['status'])->toBe('CONFIRMED')
        ->and($trail[0]->properties['attributes']['status'])->toBe('ARRIVED')
        ->and($trail[1]->properties['old']['status'])->toBe('ARRIVED')
        ->and($trail[1]->properties['attributes']['status'])->toBe('IN_PROGRESS')
        ->and((int) $trail[0]->causer_id)->toBe($officer->id)
        ->and((int) $trail[1]->causer_id)->toBe($officer->id);
});

it('records the completion at gate-out', function (): void {
    ['officer' => $officer, 'appointment' => $appointment] = auditScenario();
    Sanctum::actingAs($officer);

    postJson("/api/v1/appointments/{$appointment->id}/gate-in")->assertOk();
    postJson("/api/v1/appointments/{$appointment->id}/gate-out")->assertOk();

    $last = auditTrailFor($appointment)->last();

    expect($last->properties['attributes']['status'])->toBe('COMPLETED')
        ->and($last->properties['old']['status'])->toBe('IN_PROGRESS')
        ->and((int) $last->causer_id)->toBe($officer->id);
});

it('records a cancellation against the transporter who made it', function (): void {
    ['transporter' => $transporter, 'appointment' => $appointment] = auditScenario();
    Sanctum::actingAs($transporter);

    postJson("/api/v1/appointments/{$appointment->id}/cancel", ['version' => $appointment->version])
        ->assertOk();

    $last = auditTrailFor($appointment)->last();

    expect($last->properties['attributes']['status'])->toBe('CANCELLED')
        ->and((int) $last->causer_id)->toBe($transporter->id);
});

it('records a reschedule as a window move plus version bump', function (): void {
    ['transporter' => $transporter, 'appointment' => $appointment, 'gate' => $gate] = auditScenario();
    // Jam berbeda dari window skenario: unik (gate_id, date, start_time). Masih
    // nanti hari ini (sekarang 10:00) supaya tak kena guard window-berakhir.
    $target = SlotWindow::factory()->create([
        'gate_id' => $gate->id,
        'date' => today()->toDateString(),
        'start_time' => '12:00:00',
        'end_time' => '13:00:00',
        'capacity' => 5,
        'booked_count' => 0,
    ]);
    Sanctum::actingAs($transporter);

    postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
        'slot_window_id' => $target->id,
        'version' => $appointment->version,
    ])->assertOk();

    $last = auditTrailFor($appointment)->last();

    // Reschedule tidak mengubah status — yang harus terekam justru perpindahan
    // window & kenaikan version (dasar optimistic lock).
    expect($last->properties['attributes']['slot_window_id'])->toBe($target->id)
        ->and($last->properties['old']['slot_window_id'])->toBe($appointment->slot_window_id)
        ->and($last->properties['attributes']['version'])->toBe($appointment->version + 1)
        ->and((int) $last->causer_id)->toBe($transporter->id);
});

it('records a swept no-show with no causer, marking it a system action', function (): void {
    ['appointment' => $appointment, 'gate' => $gate] = auditScenario();
    // Window yang sudah lewat grace → kandidat sapuan.
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

    $last = auditTrailFor($appointment)->last();

    // Tidak ada user yang login saat job jalan. causer NULL itulah yang
    // membedakan tindakan sistem dari tindakan manusia di audit trail —
    // kalau suatu saat ada yang "merapikan" ini jadi user sistem, test gugur.
    expect($last->properties['attributes']['status'])->toBe('NO_SHOW')
        ->and($last->causer_id)->toBeNull();
});

it('keeps the audited scope narrow: untouched status means no new entry', function (): void {
    ['appointment' => $appointment, 'company' => $company] = auditScenario();
    $before = auditTrailFor($appointment)->count();

    // Jangkar: tanpa ini test negatif di bawah tetap hijau walau logging MATI
    // TOTAL (0 == 0) — ia hanya menjaga sesuatu bila terbukti ada yang dijaga.
    expect($before)->toBeGreaterThan(0);

    // truck_id ADA di $fillable tapi TIDAK di logOnly([...]). Mengubahnya sendirian
    // tak boleh menghasilkan entri (logOnlyDirty + dontSubmitEmptyLogs). Ini
    // mengunci batas ruang lingkup audit supaya pelebarannya jadi keputusan sadar,
    // bukan efek samping.
    $appointment->truck_id = Truck::factory()->create(['company_id' => $company->id])->id;
    $appointment->save();

    expect(auditTrailFor($appointment)->count())->toBe($before);
});
