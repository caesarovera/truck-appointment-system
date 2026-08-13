<?php

declare(strict_types=1);

use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;
use function Pest\Laravel\travelTo;

function availabilityActor(): User
{
    seed(RolePermissionSeeder::class);
    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');
    Sanctum::actingAs($user);

    return $user;
}

it('lists open windows with remaining quota for a gate and date', function (): void {
    seed(RolePermissionSeeder::class);
    $company = TransportCompany::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('transporter');
    Sanctum::actingAs($user);

    $gate = Gate::factory()->create();
    SlotWindow::factory()->for($gate)->create([
        'date' => now()->toDateString(),
        'start_time' => '08:00:00',
        'capacity' => 5,
        'booked_count' => 2,
        'status' => 'OPEN',
    ]);
    SlotWindow::factory()->for($gate)->closed()->create([
        'date' => now()->toDateString(),
        'start_time' => '09:00:00',
    ]);

    $response = getJson('/api/v1/slots/availability?gate='.$gate->id);

    // Hanya window OPEN yang tampil, dengan sisa kuota benar.
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.remaining', 3)
        ->assertJsonPath('data.0.status', 'OPEN');
});

it('requires authentication', function (): void {
    $gate = Gate::factory()->create();

    getJson('/api/v1/slots/availability?gate='.$gate->id)->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Flag `ended` (window OPEN tapi window.end sudah lewat)
|--------------------------------------------------------------------------
| BookAppointmentAction menolak booking ke window begini (409 slot_unavailable/
| expired) — tanpa flag ini, FE menampilkan badge "Tersedia" yang menyesatkan
| (lihat SlotAvailabilityPage badge + tombol Booking).
*/

it('flags a window that already ended even though it is still OPEN', function (): void {
    availabilityActor();
    travelTo(today()->setTime(12, 0));

    $gate = Gate::factory()->create();
    SlotWindow::factory()->for($gate)->create([
        'date' => today()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00', // sudah lewat — sekarang 12:00
        'status' => 'OPEN',
    ]);

    getJson('/api/v1/slots/availability?gate='.$gate->id.'&date='.today()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.ended', true);
});

it('does not flag a window that has not ended yet', function (): void {
    availabilityActor();
    travelTo(today()->setTime(12, 0));

    $gate = Gate::factory()->create();
    SlotWindow::factory()->for($gate)->create([
        'date' => today()->toDateString(),
        'start_time' => '13:00:00',
        'end_time' => '14:00:00', // belum mulai
        'status' => 'OPEN',
    ]);

    getJson('/api/v1/slots/availability?gate='.$gate->id.'&date='.today()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.ended', false);
});
