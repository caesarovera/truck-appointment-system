<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\DataTransferObjects\BookAppointmentData;
use App\Enums\TruckStatus;
use App\Events\AppointmentBooked;
use App\Exceptions\DuplicateBookingException;
use App\Exceptions\FleetOwnershipException;
use App\Exceptions\InactiveTruckException;
use App\Exceptions\InvalidDriverException;
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
 *  1. Validasi kepemilikan truk & sopir (isolasi antar-company), lalu kelayakan:
 *     truk masih ACTIVE & sopir benar-benar ber-role `driver`.
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

            // Window yang sudah berakhir tak boleh di-book: tanpa guard ini booking
            // lolos lalu langsung disapu NO_SHOW ≤5 menit kemudian. Window yang
            // sedang berjalan tetap boleh (truk masih bisa datang sebelum tutup).
            if ($window->hasEnded()) {
                throw SlotUnavailableException::expired();
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
        $truck = Truck::query()
            ->whereKey($data->truckId)
            ->where('company_id', $companyId)
            ->first();

        $driverOwned = User::query()
            ->whereKey($data->driverId)
            ->where('company_id', $companyId)
            ->exists();

        if ($truck === null || ! $driverOwned) {
            throw new FleetOwnershipException;
        }

        // Truk yang dipensiunkan tak boleh dijadwalkan lagi. Dicek SETELAH
        // kepemilikan: kalau dibalik, pesan error yang berbeda membocorkan
        // keberadaan & kondisi truk milik company lain.
        if ($truck->status !== TruckStatus::ACTIVE) {
            throw new InactiveTruckException;
        }

        // `driver_id` harus benar-benar sopir, bukan sekadar user sekantor.
        // Rule::exists di FormRequest hanya menyaring company — dropdown /me/fleet
        // menyaring role, tapi itu UI, bukan penegakan. Kalau lolos: reminder nyasar
        // ke non-sopir dan appointment tak pernah muncul di /me/appointments/today
        // (butuh `appointment.read.self`) → sopir sungguhan tak pernah tahu jadwalnya.
        // Dicek SETELAH kepemilikan, alasan sama dengan truk INACTIVE di atas.
        $isDriver = User::query()
            ->whereKey($data->driverId)
            ->role('driver', 'api')
            ->exists();

        if (! $isDriver) {
            throw new InvalidDriverException;
        }
    }

    private function generateBookingCode(): string
    {
        return 'TAS-'.Str::upper(Str::random(8));
    }
}
