<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\SlotWindowStatus;
use App\Events\SlotWindowClosed;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RolePermissionSeeder::class));

function closePlanner(): User
{
    $user = User::factory()->create();
    $user->assignRole('planner');

    return $user;
}

it('lets a planner close an open window without deleting it', function (): void {
    Event::fake([SlotWindowClosed::class]);
    $window = SlotWindow::factory()->create(['status' => SlotWindowStatus::OPEN]);
    Sanctum::actingAs(closePlanner());

    postJson("/api/v1/slots/{$window->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'CLOSED');

    expect($window->fresh()->status)->toBe(SlotWindowStatus::CLOSED);
    Event::assertDispatched(SlotWindowClosed::class);
});

it('keeps existing appointments valid after closing', function (): void {
    $window = SlotWindow::factory()->create(['status' => SlotWindowStatus::OPEN]);
    $appointment = Appointment::factory()->confirmed()->create(['slot_window_id' => $window->id]);
    Sanctum::actingAs(closePlanner());

    postJson("/api/v1/slots/{$window->id}/close")->assertOk();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::CONFIRMED);
});

it('removes a closed window from the availability endpoint', function (): void {
    $gate = Gate::factory()->create();
    $date = now()->toDateString();
    $window = SlotWindow::factory()->create([
        'gate_id' => $gate->id,
        'date' => $date,
        'status' => SlotWindowStatus::OPEN,
    ]);
    Sanctum::actingAs(closePlanner());

    getJson("/api/v1/slots/availability?gate={$gate->id}&date={$date}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    postJson("/api/v1/slots/{$window->id}/close")->assertOk();

    getJson("/api/v1/slots/availability?gate={$gate->id}&date={$date}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('is idempotent: closing an already closed window stays CLOSED', function (): void {
    $window = SlotWindow::factory()->create(['status' => SlotWindowStatus::CLOSED]);
    Sanctum::actingAs(closePlanner());

    postJson("/api/v1/slots/{$window->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'CLOSED');
});

it('forbids a transporter from closing a window (403)', function (): void {
    $window = SlotWindow::factory()->create();
    $transporter = User::factory()->create();
    $transporter->assignRole('transporter');
    Sanctum::actingAs($transporter);

    postJson("/api/v1/slots/{$window->id}/close")->assertForbidden();
});
