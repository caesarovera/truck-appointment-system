<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\SlotRepositoryInterface;
use App\Enums\SlotWindowStatus;
use App\Events\SlotWindowClosed;
use App\Models\SlotWindow;
use Illuminate\Support\Facades\DB;

/**
 * Planner menutup jendela slot (BUSINESS-FLOW §3.1): status → CLOSED, BUKAN
 * delete. Lock baris supaya tidak balapan dengan booking yang sedang memegang
 * window. Idempoten: window yang sudah CLOSED dikembalikan apa adanya.
 */
final class CloseSlotWindowAction
{
    public function __construct(private readonly SlotRepositoryInterface $slots) {}

    public function execute(SlotWindow $window): SlotWindow
    {
        $closed = DB::transaction(function () use ($window): SlotWindow {
            $locked = SlotWindow::query()->whereKey($window->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === SlotWindowStatus::CLOSED) {
                return $locked;
            }

            $this->slots->markClosed($locked);

            return $locked;
        }, attempts: 3);

        SlotWindowClosed::dispatch($closed->id);

        return $closed;
    }
}
