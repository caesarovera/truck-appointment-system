<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\CreateCompanyController;
use App\Http\Controllers\Api\V1\Admin\CreateGateController;
use App\Http\Controllers\Api\V1\Admin\CreateTerminalController;
use App\Http\Controllers\Api\V1\Admin\CreateUserController;
use App\Http\Controllers\Api\V1\Admin\DeleteCompanyController;
use App\Http\Controllers\Api\V1\Admin\DeleteGateController;
use App\Http\Controllers\Api\V1\Admin\DeleteTerminalController;
use App\Http\Controllers\Api\V1\Admin\DeleteUserController;
use App\Http\Controllers\Api\V1\Admin\ListAdminGatesController;
use App\Http\Controllers\Api\V1\Admin\ListCompaniesController;
use App\Http\Controllers\Api\V1\Admin\ListRolesController;
use App\Http\Controllers\Api\V1\Admin\ListTerminalsController;
use App\Http\Controllers\Api\V1\Admin\ListUsersController;
use App\Http\Controllers\Api\V1\Admin\ShowCompanyController;
use App\Http\Controllers\Api\V1\Admin\ShowGateController;
use App\Http\Controllers\Api\V1\Admin\ShowTerminalController;
use App\Http\Controllers\Api\V1\Admin\ShowUserController;
use App\Http\Controllers\Api\V1\Admin\UpdateCompanyController;
use App\Http\Controllers\Api\V1\Admin\UpdateGateController;
use App\Http\Controllers\Api\V1\Admin\UpdateRolePermissionsController;
use App\Http\Controllers\Api\V1\Admin\UpdateTerminalController;
use App\Http\Controllers\Api\V1\Admin\UpdateUserController;
use App\Http\Controllers\Api\V1\AppointmentAuditController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\BookAppointmentController;
use App\Http\Controllers\Api\V1\CancelAppointmentController;
use App\Http\Controllers\Api\V1\CloseSlotWindowController;
use App\Http\Controllers\Api\V1\Fleet\CreateTruckController;
use App\Http\Controllers\Api\V1\Fleet\DeleteTruckController;
use App\Http\Controllers\Api\V1\Fleet\ListTrucksController;
use App\Http\Controllers\Api\V1\Fleet\UpdateTruckController;
use App\Http\Controllers\Api\V1\GateHistoryReportController;
use App\Http\Controllers\Api\V1\GateInController;
use App\Http\Controllers\Api\V1\GateOutController;
use App\Http\Controllers\Api\V1\GateQueueController;
use App\Http\Controllers\Api\V1\ListGatesController;
use App\Http\Controllers\Api\V1\MarkNoShowController;
use App\Http\Controllers\Api\V1\MyAppointmentsController;
use App\Http\Controllers\Api\V1\MyFleetController;
use App\Http\Controllers\Api\V1\MyTodayAppointmentsController;
use App\Http\Controllers\Api\V1\MyUtilizationReportController;
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

        // Referensi/master data (read) untuk dropdown & form booking.
        Route::get('gates', ListGatesController::class);
        Route::get('me/fleet', MyFleetController::class);

        // Armada truk milik transporter (fleet.manage, scoped company sendiri).
        Route::get('me/trucks', ListTrucksController::class);
        Route::post('me/trucks', CreateTruckController::class);
        Route::patch('me/trucks/{truck}', UpdateTruckController::class);
        Route::delete('me/trucks/{truck}', DeleteTruckController::class);

        Route::get('slots/availability', SlotAvailabilityController::class);
        // Planner kelola jendela slot (slot.manage) — otorisasi di FormRequest.
        Route::post('slots', OpenSlotWindowController::class)->middleware('idempotency');
        Route::post('slots/{slotWindow}/close', CloseSlotWindowController::class)->middleware('idempotency');
        // Booking lebih ketat dari `api` (anti bot borong slot).
        Route::post('appointments', BookAppointmentController::class)
            ->middleware(['throttle:booking', 'idempotency']);
        Route::get('appointments/{appointment}', ShowAppointmentController::class)
            ->middleware('can:view,appointment');
        // Audit trail 1 appointment (audit.read + Policy view) — matriks §1.
        Route::get('appointments/{appointment}/audit', AppointmentAuditController::class)
            ->middleware('can:view,appointment');
        Route::post('appointments/{appointment}/reschedule', RescheduleAppointmentController::class)
            ->middleware(['can:update,appointment', 'idempotency']);
        Route::post('appointments/{appointment}/cancel', CancelAppointmentController::class)
            ->middleware(['can:cancel,appointment', 'idempotency']);
        // Antrian gate-officer (CONFIRMED/IN_PROGRESS di terminalnya hari ini).
        Route::get('gate/queue', GateQueueController::class);
        Route::post('appointments/{appointment}/gate-in', GateInController::class)
            ->middleware(['can:process,appointment', 'idempotency']);
        Route::post('appointments/{appointment}/gate-out', GateOutController::class)
            ->middleware(['can:process,appointment', 'idempotency']);
        // Tandai no-show manual (BUSINESS-FLOW §3.5) — sama scope otorisasi dgn
        // gate-in/out; NoShowSweepJob tetap jalan sbg jaring otomatis (grace period).
        Route::post('appointments/{appointment}/no-show', MarkNoShowController::class)
            ->middleware(['can:process,appointment', 'idempotency']);

        // Endpoint pendukung
        Route::get('me/appointments', MyAppointmentsController::class);
        Route::get('me/appointments/today', MyTodayAppointmentsController::class);
        // Agregat lintas-company (planner/admin) vs scoped milik sendiri (transporter).
        Route::get('reports/utilization', UtilizationReportController::class);
        Route::get('me/reports/utilization', MyUtilizationReportController::class);
        // Riwayat gate-in/out per gate+tanggal (planner/admin) — appointment yang
        // sudah gate-in, termasuk yang sudah COMPLETED. Lihat GateHistoryReportRequest.
        Route::get('reports/gate-history', GateHistoryReportController::class);

        // Admin CRUD — otorisasi per-permission di setiap FormRequest.
        Route::prefix('admin')->group(function (): void {
            // Terminals
            Route::get('terminals', ListTerminalsController::class);
            Route::post('terminals', CreateTerminalController::class);
            Route::get('terminals/{terminal}', ShowTerminalController::class);
            Route::put('terminals/{terminal}', UpdateTerminalController::class);
            Route::delete('terminals/{terminal}', DeleteTerminalController::class);

            // Gates (admin view: with terminal, not just for dropdown)
            Route::get('gates', ListAdminGatesController::class);
            Route::post('gates', CreateGateController::class);
            Route::get('gates/{gate}', ShowGateController::class);
            Route::put('gates/{gate}', UpdateGateController::class);
            Route::delete('gates/{gate}', DeleteGateController::class);

            // Transport companies
            Route::get('companies', ListCompaniesController::class);
            Route::post('companies', CreateCompanyController::class);
            Route::get('companies/{transportCompany}', ShowCompanyController::class);
            Route::put('companies/{transportCompany}', UpdateCompanyController::class);
            Route::delete('companies/{transportCompany}', DeleteCompanyController::class);

            // Users
            Route::get('users', ListUsersController::class);
            Route::post('users', CreateUserController::class);
            Route::get('users/{user}', ShowUserController::class);
            Route::put('users/{user}', UpdateUserController::class);
            Route::delete('users/{user}', DeleteUserController::class);

            // Role & Izin — hanya edit permission role yang sudah ada (5 tetap),
            // TIDAK bisa bikin/hapus role baru (lihat HANDOVER 2026-08-13: scoping
            // otorisasi di AppointmentPolicy hardcode nama role, jadi role baru
            // butuh refactor Policy dulu supaya beneran punya perilaku yang benar).
            Route::get('roles', ListRolesController::class);
            Route::put('roles/{role}/permissions', UpdateRolePermissionsController::class);
        });
    });
});
