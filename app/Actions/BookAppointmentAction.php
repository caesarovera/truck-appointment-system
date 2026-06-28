<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\DataTransferObjects\BookAppointmentData;
use App\Events\AppointmentBooked;
use App\Exceptions\DuplicateBookingException;
use App\Exceptions\FleetOwnershipException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Booking 1 slot (jantung anti-race proyek ini).
 *
 * Alur (docs/BUSINESS-FLOW.md §3.2):
 *  1. Validasi kepemilikan truk & sopir (isolasi antar-company).
 *  2. DB::transaction(attempts: 3)  → auto-retry bila deadlock.
 *  3. SlotWindow::lockForUpdate     → serialisasi perebut slot terakhir.
 *  4. Tolak bila ditutup / penuh    → 409.
 *  5. Buat appointment + container, naikkan booked_count (transaksi sama).
 *  6. Commit → baru dispatch AppointmentBooked (efek samping di luar transaksi).
 */
final class BookAppointmentAction
{
    public function __construct(
        private readonly SlotRepositoryInterface $slots,
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    public function execute(User $actor, BookAppointmentData $data): Appointment
    {
        $companyId = $actor->company_id;

        if ($companyId === null) {
            throw new FleetOwnershipException;
        }

        $this->assertFleetBelongsToCompany($companyId, $data);

        $appointment = DB::transaction(function () use ($data, $companyId): Appointment {
            $window = $this->slots->lockForUpdate($data->slotWindowId);

            if ($window === null) {
                throw (new ModelNotFoundException)->setModel(SlotWindow::class, [$data->slotWindowId]);
            }

            if (! $window->isOpen()) {
                throw SlotUnavailableException::closed();
            }

            if (! $window->hasCapacity()) {
                throw SlotUnavailableException::full();
            }

            try {
                $appointment = $this->appointments->createConfirmed($data, $companyId, $this->generateBookingCode());
            } catch (UniqueConstraintViolationException) {
                // Kontainer sudah dibooking di window ini (jaring terakhir DB).
                throw new DuplicateBookingException;
            }

            $this->slots->incrementBooked($window);

            return $appointment;
        }, attempts: 3);

        AppointmentBooked::dispatch($appointment);

        return $appointment;
    }

    private function assertFleetBelongsToCompany(int $companyId, BookAppointmentData $data): void
    {
        $truckOwned = Truck::query()
            ->whereKey($data->truckId)
            ->where('company_id', $companyId)
            ->exists();

        $driverOwned = User::query()
            ->whereKey($data->driverId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $truckOwned || ! $driverOwned) {
            throw new FleetOwnershipException;
        }
    }

    private function generateBookingCode(): string
    {
        return 'TAS-'.Str::upper(Str::random(8));
    }
}
