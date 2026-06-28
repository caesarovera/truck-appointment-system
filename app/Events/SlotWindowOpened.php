<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AffectsSlotAvailability;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Planner membuka window baru → ketersediaan gate bertambah (BUSINESS-FLOW §3.1). */
final class SlotWindowOpened implements AffectsSlotAvailability
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly int $slotWindowId) {}

    /** @return array<int, int> */
    public function windowIdsToRefresh(): array
    {
        return [$this->slotWindowId];
    }
}
