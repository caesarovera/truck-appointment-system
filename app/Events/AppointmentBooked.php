<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AffectsSlotAvailability;
use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan SETELAH transaksi booking commit (lihat BookAppointmentAction).
 * Listener menangani efek samping: invalidasi cache, (nanti) reminder, broadcast
 * sisa kuota. JANGAN dispatch di dalam DB::transaction.
 */
final class AppointmentBooked implements AffectsSlotAvailability
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    /** @return array<int, int> */
    public function windowIdsToRefresh(): array
    {
        return [$this->appointment->slot_window_id];
    }
}
