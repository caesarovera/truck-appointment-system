<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\AffectsSlotAvailability;
use App\Events\Broadcasting\SlotAvailabilityChanged;
use App\Models\SlotWindow;

/**
 * Siarkan sisa kuota live tiap kali ketersediaan slot berubah. Auto-discovered
 * via type-hint interface AffectsSlotAvailability (sejajar dgn cache invalidate).
 */
final class BroadcastSlotAvailability
{
    public function handle(AffectsSlotAvailability $event): void
    {
        // Query by id (bukan lazy-load) supaya aman dari preventLazyLoading.
        $windows = SlotWindow::query()
            ->whereKey($event->windowIdsToRefresh())
            ->get(['id', 'gate_id', 'date', 'capacity', 'booked_count', 'status']);

        foreach ($windows->groupBy('gate_id') as $gateId => $group) {
            SlotAvailabilityChanged::dispatch(
                (int) $gateId,
                $group->map(fn (SlotWindow $window): array => [
                    'id' => $window->id,
                    'date' => $window->date->toDateString(),
                    'capacity' => $window->capacity,
                    'booked_count' => $window->booked_count,
                    'remaining' => $window->remaining(),
                    'status' => $window->status->value,
                ])->values()->all(),
            );
        }
    }
}
