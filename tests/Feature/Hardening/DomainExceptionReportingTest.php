<?php

declare(strict_types=1);

use App\Exceptions\OptimisticLockException;
use App\Exceptions\SlotUnavailableException;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/*
 * Exception domain yang merender respons 4xx adalah hasil bisnis yang wajar (kuota penuh, versi basi,
 * token QR kedaluwarsa) — bukan error server. Kalau dilaporkan, tiap penolakan menulis ERROR + stack
 * trace ke log dan menenggelamkan error sungguhan & alarm CRITICAL (B7).
 */

/** @return array<int, string> */
function renderableDomainExceptions(): array
{
    return collect(glob(app_path('Exceptions/*.php')) ?: [])
        ->map(fn (string $path): string => 'App\\Exceptions\\'.basename($path, '.php'))
        ->filter(fn (string $class): bool => method_exists($class, 'render'))
        ->values()
        ->all();
}

it('finds the renderable domain exceptions it guards', function (): void {
    // Tanpa ini, glob kosong membuat test berikutnya lolos tanpa memeriksa apa pun.
    expect(renderableDomainExceptions())
        ->toContain(SlotUnavailableException::class, OptimisticLockException::class);
});

it('never reports a renderable domain exception', function (): void {
    $handler = app(ExceptionHandler::class);

    foreach (renderableDomainExceptions() as $class) {
        $exception = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        expect($handler->shouldReport($exception))->toBeFalse("{$class} masih dilaporkan ke log");
    }
});

it('still answers a full slot with 409 without reporting it', function (): void {
    Exceptions::fake();
    $this->seed(RolePermissionSeeder::class);

    $company = TransportCompany::factory()->create();
    $actor = User::factory()->create(['company_id' => $company->id])->assignRole('transporter');
    $window = SlotWindow::factory()->create(['capacity' => 1, 'booked_count' => 1]);
    Sanctum::actingAs($actor, ['*']);

    postJson('/api/v1/appointments', [
        'slot_window_id' => $window->id,
        'truck_id' => Truck::factory()->create(['company_id' => $company->id])->id,
        'driver_id' => User::factory()->driver()->create(['company_id' => $company->id])->id,
        'move_type' => 'DELIVERY',
        'container_no' => 'MAUU1111111',
    ], ['Idempotency-Key' => 'domain-exception-report'])
        ->assertStatus(409)
        ->assertJsonPath('error', 'slot_unavailable');

    Exceptions::assertNotReported(SlotUnavailableException::class);
});
