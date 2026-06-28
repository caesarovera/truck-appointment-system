<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Contracts\SlotRepositoryInterface;
use App\DataTransferObjects\RescheduleAppointmentData;
use App\Events\AppointmentRescheduled;
use App\Exceptions\DuplicateBookingException;
use App\Exceptions\InvalidAppointmentStateException;
use App\Exceptions\OptimisticLockException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\SlotWindow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Pindahkan appointment ke window lain (BUSINESS-FLOW §3.3).
 * Optimistic lock via `version` (transporter & planner bisa edit bersamaan) +
 * kunci kedua window dalam satu transaksi untuk pindah kuota dengan aman.
 */
final class RescheduleAppointmentAction
{
    public function __construct(
        private readonly SlotRepositoryInterface $slots,
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    public function execute(Appointment $appointment, RescheduleAppointmentData $data): Appointment
    {
        [$result, $fromWindowId] = DB::transaction(function () use ($appointment, $data): array {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isReschedulable()) {
                throw InvalidAppointmentStateException::cannotReschedule();
            }

            // Optimistic lock: tolak bila klien memegang versi usang.
            if ($locked->version !== $data->expectedVersion) {
                throw new OptimisticLockException;
            }

            $fromWindowId = $locked->slot_window_id;

            if ($data->slotWindowId === $fromWindowId) {
                // Tidak pindah ke mana-mana → tidak ada yang dikerjakan.
                return [$locked, $fromWindowId];
            }

            // Kunci kedua window dengan urutan id konsisten → cegah deadlock.
            $ids = [$fromWindowId, $data->slotWindowId];
            sort($ids);
            /** @var array<int, SlotWindow|null> $locks */
            $locks = [];
            foreach ($ids as $id) {
                $locks[$id] = $this->slots->lockForUpdate($id);
            }

            $from = $locks[$fromWindowId];
            $to = $locks[$data->slotWindowId];

            if ($to === null) {
                throw (new ModelNotFoundException)->setModel(SlotWindow::class, [$data->slotWindowId]);
            }

            if (! $to->isOpen()) {
                throw SlotUnavailableException::closed();
            }
            if (! $to->hasCapacity()) {
                throw SlotUnavailableException::full();
            }

            try {
                $this->appointments->moveToWindow($locked, $data->slotWindowId);
            } catch (UniqueConstraintViolationException) {
                throw new DuplicateBookingException;
            }

            if ($from !== null) {
                $this->slots->decrementBooked($from);
            }
            $this->slots->incrementBooked($to);

            return [$locked, $fromWindowId];
        }, attempts: 3);

        if ($fromWindowId !== $result->slot_window_id) {
            AppointmentRescheduled::dispatch($result, $fromWindowId, $result->slot_window_id);
        }

        return $result;
    }
}
