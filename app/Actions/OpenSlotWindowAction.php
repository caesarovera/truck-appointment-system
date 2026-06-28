<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\SlotRepositoryInterface;
use App\DataTransferObjects\OpenSlotWindowData;
use App\Events\SlotWindowOpened;
use App\Exceptions\DuplicateSlotWindowException;
use App\Models\SlotWindow;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Planner membuka jendela slot baru (BUSINESS-FLOW §3.1). Event SlotWindowOpened
 * (impl AffectsSlotAvailability) memicu invalidasi cache + broadcast sisa kuota.
 */
final class OpenSlotWindowAction
{
    public function __construct(private readonly SlotRepositoryInterface $slots) {}

    public function execute(OpenSlotWindowData $data): SlotWindow
    {
        try {
            $window = $this->slots->create($data);
        } catch (UniqueConstraintViolationException) {
            // Unik (gate_id, date, start_time): window itu sudah ada.
            throw new DuplicateSlotWindowException;
        }

        SlotWindowOpened::dispatch($window->id);

        return $window;
    }
}
