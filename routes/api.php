<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\BookAppointmentController;
use App\Http\Controllers\Api\V1\CancelAppointmentController;
use App\Http\Controllers\Api\V1\CloseSlotWindowController;
use App\Http\Controllers\Api\V1\GateInController;
use App\Http\Controllers\Api\V1\GateOutController;
use App\Http\Controllers\Api\V1\MyTodayAppointmentsController;
use App\Http\Controllers\Api\V1\OpenSlotWindowController;
use App\Http\Controllers\Api\V1\RescheduleAppointmentController;
use App\Http\Controllers\Api\V1\ShowAppointmentController;
use App\Http\Controllers\Api\V1\SlotAvailabilityController;
use App\Http\Controllers\Api\V1\UtilizationReportController;
use Illuminate\Support\Facades\Route;

// Versi baru = folder/grup baru, jangan mutasi v1 (CLAUDE.md).
Route::prefix('v1')->group(function (): void {
    // Publik — throttle anti brute-force (kunci email+ip).
    Route::post('login', LoginController::class)->middleware('throttle:login');

    // Perlu token Sanctum + batas umum abuse (kunci user id / ip).
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('logout', LogoutController::class);
        Route::get('me', MeController::class);

        Route::get('slots/availability', SlotAvailabilityController::class);
        // Planner kelola jendela slot (slot.manage) — otorisasi di FormRequest.
        Route::post('slots', OpenSlotWindowController::class)->middleware('idempotency');
        Route::post('slots/{slotWindow}/close', CloseSlotWindowController::class)->middleware('idempotency');
        // Booking lebih ketat dari `api` (anti bot borong slot).
        Route::post('appointments', BookAppointmentController::class)
            ->middleware(['throttle:booking', 'idempotency']);
        Route::get('appointments/{appointment}', ShowAppointmentController::class)
            ->middleware('can:view,appointment');
        Route::post('appointments/{appointment}/reschedule', RescheduleAppointmentController::class)
            ->middleware(['can:update,appointment', 'idempotency']);
        Route::post('appointments/{appointment}/cancel', CancelAppointmentController::class)
            ->middleware(['can:cancel,appointment', 'idempotency']);
        Route::post('appointments/{appointment}/gate-in', GateInController::class)
            ->middleware(['can:process,appointment', 'idempotency']);
        Route::post('appointments/{appointment}/gate-out', GateOutController::class)
            ->middleware(['can:process,appointment', 'idempotency']);

        // Endpoint pendukung
        Route::get('me/appointments/today', MyTodayAppointmentsController::class);
        Route::get('reports/utilization', UtilizationReportController::class);
    });
});
