<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AffectsSlotAvailability;
use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Dipancarkan setelah NoShowSweepJob menandai appointment NO_SHOW. Kuota window dikembalikan. */
final class AppointmentNoShow implements AffectsSlotAvailability
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $releasedWindowId,
    ) {}

    /** @return array<int, int> */
    public function windowIdsToRefresh(): array
    {
        return [$this->releasedWindowId];
    }
}
