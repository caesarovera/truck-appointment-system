<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\SlotRepositoryInterface;
use App\DataTransferObjects\OpenSlotWindowData;
use App\Enums\AppointmentStatus;
use App\Enums\SlotWindowStatus;
use App\Models\SlotWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class SlotRepository implements SlotRepositoryInterface
{
    public function create(OpenSlotWindowData $data): SlotWindow
    {
        $window = new SlotWindow;
        $window->gate_id = $data->gateId;
        $window->date = Carbon::parse($data->date);
        $window->start_time = $data->startTime;
        $window->end_time = $data->endTime;
        $window->capacity = $data->capacity;
        $window->booked_count = 0;
        $window->status = SlotWindowStatus::OPEN;
        $window->save();

        return $window;
    }

    public function markClosed(SlotWindow $window): void
    {
        $window->status = SlotWindowStatus::CLOSED;
        $window->save();
    }

    public function lockForUpdate(int $slotWindowId): ?SlotWindow
    {
        return SlotWindow::query()
            ->whereKey($slotWindowId)
            ->lockForUpdate()
            ->first();
    }

    public function incrementBooked(SlotWindow $window): void
    {
        $window->increment('booked_count');
    }

    public function decrementBooked(SlotWindow $window): void
    {
        // Jangan pernah negatif (jaga-jaga bila dipanggil ganda).
        if ($window->booked_count > 0) {
            $window->decrement('booked_count');
        }
    }

    public function cachedAvailability(int $gateId, string $date): Collection
    {
        // Cache::flexible: stale-while-revalidate. Segar 10 dtk, masih boleh
        // disajikan sampai 30 dtk sambil di-refresh di belakang (anti-stampede).
        return Cache::flexible(
            $this->availabilityKey($gateId, $date),
            [10, 30],
            fn (): Collection => $this->queryAvailability($gateId, $date),
        );
    }

    public function forgetAvailability(int $gateId, string $date): void
    {
        Cache::forget($this->availabilityKey($gateId, $date));
    }

    public function utilization(int $gateId, string $date, ?int $companyId = null): Collection
    {
        $active = array_map(
            fn (AppointmentStatus $s): string => $s->value,
            [AppointmentStatus::BOOKED, AppointmentStatus::CONFIRMED, AppointmentStatus::ARRIVED, AppointmentStatus::IN_PROGRESS],
        );

        // Saring per-company (laporan transporter): diterapkan ke SEMUA subquery
        // hitungan supaya angka company lain tak pernah bocor. capacity/booked_count
        // tidak disaring — itu properti window (konteks gate global).
        $scoped = fn ($q) => $companyId === null ? $q : $q->where('company_id', $companyId);

        return SlotWindow::query()
            ->where('gate_id', $gateId)
            ->whereDate('date', $date)
            ->withCount([
                'appointments as completed_count' => fn ($q) => $scoped($q->where('status', AppointmentStatus::COMPLETED->value)),
                'appointments as no_show_count' => fn ($q) => $scoped($q->where('status', AppointmentStatus::NO_SHOW->value)),
                'appointments as cancelled_count' => fn ($q) => $scoped($q->where('status', AppointmentStatus::CANCELLED->value)),
                'appointments as active_count' => fn ($q) => $scoped($q->whereIn('status', $active)),
            ])
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, SlotWindow> */
    private function queryAvailability(int $gateId, string $date): Collection
    {
        return SlotWindow::query()
            ->where('gate_id', $gateId)
            ->whereDate('date', $date)
            ->where('status', SlotWindowStatus::OPEN)
            ->orderBy('start_time')
            ->get();
    }

    private function availabilityKey(int $gateId, string $date): string
    {
        return "slots:availability:gate:{$gateId}:date:{$date}";
    }
}
