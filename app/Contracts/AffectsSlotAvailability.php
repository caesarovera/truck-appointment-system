<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Event yang mengubah ketersediaan slot (booking/cancel/reschedule).
 * Listener `InvalidateSlotAvailabilityCache` membuang cache window terdampak.
 */
interface AffectsSlotAvailability
{
    /** @return array<int, int> id slot window yang ketersediaannya berubah */
    public function windowIdsToRefresh(): array;
}
