<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AffectsSlotAvailability;
use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Dipancarkan setelah reschedule commit. Kuota pindah dari window lama ke baru. */
final class AppointmentRescheduled implements AffectsSlotAvailability
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $fromWindowId,
        public readonly int $toWindowId,
    ) {}

    /** @return array<int, int> */
    public function windowIdsToRefresh(): array
    {
        return [$this->fromWindowId, $this->toWindowId];
    }
}
