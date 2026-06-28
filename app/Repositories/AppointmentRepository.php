<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\AppointmentRepositoryInterface;
use App\DataTransferObjects\BookAppointmentData;
use App\Enums\AppointmentStatus;
use App\Enums\GateTransactionType;
use App\Models\Appointment;
use App\Models\Container;
use App\Models\GateTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AppointmentRepository implements AppointmentRepositoryInterface
{
    public function createConfirmed(BookAppointmentData $data, int $companyId, string $bookingCode): Appointment
    {
        // Properti di-set eksplisit (bukan array) supaya type-safe & tidak kena
        // mass-assignment liar. MVP: dokumen dianggap valid → langsung CONFIRMED.
        $appointment = new Appointment;
        $appointment->company_id = $companyId;
        $appointment->truck_id = $data->truckId;
        $appointment->driver_id = $data->driverId;
        $appointment->slot_window_id = $data->slotWindowId;
        $appointment->move_type = $data->moveType;
        $appointment->status = AppointmentStatus::CONFIRMED;
        $appointment->version = 1;
        $appointment->booking_code = $bookingCode;
        $appointment->save();

        // slot_window_id di container = penegak unik (slot_window_id, container_no):
        // pertahanan terakhir DB anti double-booking kontainer di window yang sama.
        $container = new Container;
        $container->appointment_id = $appointment->id;
        $container->slot_window_id = $data->slotWindowId;
        $container->container_no = $data->containerNo;
        $container->iso_type = $data->isoType;
        $container->size = $data->size;
        $container->save();

        return $appointment;
    }

    public function markCancelled(Appointment $appointment): void
    {
        $appointment->status = AppointmentStatus::CANCELLED;
        $appointment->save();

        // Lepas container dari window → bebaskan unik (slot_window_id, container_no).
        Container::query()
            ->where('appointment_id', $appointment->id)
            ->update(['slot_window_id' => null]);
    }

    public function moveToWindow(Appointment $appointment, int $toWindowId): void
    {
        $appointment->slot_window_id = $toWindowId;
        $appointment->version = $appointment->version + 1;
        $appointment->save();

        Container::query()
            ->where('appointment_id', $appointment->id)
            ->update(['slot_window_id' => $toWindowId]);
    }

    public function recordGateIn(Appointment $appointment, int $processedBy): void
    {
        $this->recordGateEvent($appointment, GateTransactionType::IN, $processedBy);

        // Lewati ARRIVED → IN_PROGRESS (MVP: keduanya berurutan dalam satu aksi).
        // Dua save = dua entri Activity Log → audit transisi tetap utuh.
        $appointment->status = AppointmentStatus::ARRIVED;
        $appointment->save();
        $appointment->status = AppointmentStatus::IN_PROGRESS;
        $appointment->save();
    }

    public function recordGateOut(Appointment $appointment, int $processedBy): void
    {
        $this->recordGateEvent($appointment, GateTransactionType::OUT, $processedBy);

        $appointment->status = AppointmentStatus::COMPLETED;
        $appointment->save();
    }

    public function markNoShow(Appointment $appointment): void
    {
        $appointment->status = AppointmentStatus::NO_SHOW;
        $appointment->save();

        // Lepas container dari window → bebaskan unik (slot_window_id, container_no).
        Container::query()
            ->where('appointment_id', $appointment->id)
            ->update(['slot_window_id' => null]);
    }

    public function dueForNoShow(CarbonInterface $now, int $graceMinutes): Collection
    {
        // Saring kasar di DB (status pra-kedatangan + tanggal window <= hari ini),
        // lalu refine presisi di PHP — kombinasi date+time portabel lintas driver
        // (sqlite dev & mysql) tanpa ekspresi tanggal database-spesifik.
        //
        // Dipindai chunkById: hanya N baris di-hydrate per iterasi → memori
        // terbatas walau appointment pra-kedatangan menumpuk. Hanya yang benar2
        // lewat grace yang ditahan di hasil.
        $chunkSize = (int) config('tas.no_show_chunk_size', 500);

        /** @var Collection<int, Appointment> $due */
        $due = new Collection;

        Appointment::query()
            ->whereIn('status', [AppointmentStatus::BOOKED, AppointmentStatus::CONFIRMED])
            ->whereHas('slotWindow', fn ($q) => $q->whereDate('date', '<=', $now->toDateString()))
            ->with('slotWindow')
            ->chunkById($chunkSize, function (EloquentCollection $chunk) use ($now, $graceMinutes, $due): void {
                foreach ($chunk as $appointment) {
                    $window = $appointment->slotWindow;

                    if ($window === null) {
                        continue;
                    }

                    $deadline = $window->date->copy()
                        ->setTimeFromTimeString($window->end_time)
                        ->addMinutes($graceMinutes);

                    if ($deadline->lessThan($now)) {
                        $due->push($appointment);
                    }
                }
            });

        return $due->values();
    }

    public function todayForDriver(int $driverId, string $date): Collection
    {
        return Appointment::query()
            ->where('driver_id', $driverId)
            ->whereHas('slotWindow', fn ($q) => $q->whereDate('date', $date))
            ->with(['truck', 'driver', 'company', 'slotWindow.gate', 'containers'])
            ->get();
    }

    public function forCompany(int $companyId, ?string $status): Collection
    {
        return Appointment::query()
            ->where('company_id', $companyId)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with(['truck', 'driver', 'company', 'slotWindow.gate', 'containers'])
            ->orderByDesc('id') // booking terbaru di atas
            ->get();
    }

    private function recordGateEvent(Appointment $appointment, GateTransactionType $type, int $processedBy): void
    {
        // Unik (appointment_id, type) di DB = jaring terakhir anti gate event ganda.
        $tx = new GateTransaction;
        $tx->appointment_id = $appointment->id;
        $tx->type = $type;
        $tx->processed_by = $processedBy;
        $tx->processed_at = Carbon::now();
        $tx->save();
    }
}
