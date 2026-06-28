<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\AffectsSlotAvailability;
use App\Contracts\SlotRepositoryInterface;
use App\Models\SlotWindow;

/**
 * Buang cache ketersediaan untuk semua window yang berubah akibat booking/cancel/
 * reschedule. Auto-discovered via type-hint interface AffectsSlotAvailability —
 * dispatcher Laravel mencocokkan listener lewat interface event.
 */
final class InvalidateSlotAvailabilityCache
{
    public function __construct(private readonly SlotRepositoryInterface $slots) {}

    public function handle(AffectsSlotAvailability $event): void
    {
        // Query by id (bukan lazy-load) supaya aman dari preventLazyLoading.
        $windows = SlotWindow::query()
            ->whereKey($event->windowIdsToRefresh())
            ->get(['id', 'gate_id', 'date']);

        foreach ($windows as $window) {
            $this->slots->forgetAvailability($window->gate_id, $window->date->toDateString());
        }
    }
}
