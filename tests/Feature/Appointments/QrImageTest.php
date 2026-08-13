<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\User;
use App\Services\AppointmentQrTokenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

it('streams a PNG for a valid QR token without ever touching storage', function (): void {
    Storage::fake('local');

    $terminal = Terminal::factory()->create();
    $officer = User::factory()->create(['terminal_id' => $terminal->id]);
    $officer->assignRole('gate-officer');

    $gate = Gate::factory()->for($terminal)->create();
    $window = SlotWindow::factory()->for($gate)->ongoing()->create();
    $appointment = Appointment::factory()->create(['slot_window_id' => $window->id]);
    $appointment->load('slotWindow');

    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    Sanctum::actingAs($officer);

    getJson('/api/v1/appointments/qr/'.$token.'/image')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    Storage::disk('local')->assertDirectoryEmpty('/');
});

it('rejects an invalid QR token for the image endpoint (403)', function (): void {
    Sanctum::actingAs(tap(User::factory()->create(), fn (User $u) => $u->assignRole('planner')));

    getJson('/api/v1/appointments/qr/complete-nonsense/image')->assertForbidden();
});

it('forbids a gate officer at a different terminal from fetching the QR image (403)', function (): void {
    $appointment = Appointment::factory()->create([
        'slot_window_id' => SlotWindow::factory()->for(Gate::factory()->for(Terminal::factory()->create())->create())->ongoing()->create()->id,
    ]);
    $appointment->load('slotWindow');
    $token = app(AppointmentQrTokenService::class)->generate($appointment);

    $officer = User::factory()->create(['terminal_id' => Terminal::factory()->create()->id]);
    $officer->assignRole('gate-officer');
    Sanctum::actingAs($officer);

    getJson('/api/v1/appointments/qr/'.$token.'/image')->assertForbidden();
});

it('requires authentication (401)', function (): void {
    getJson('/api/v1/appointments/qr/whatever/image')->assertUnauthorized();
});
