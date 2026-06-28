<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\RecordsGateEvent;
use App\Events\Broadcasting\GateQueueUpdated;

/**
 * Siarkan pergerakan antrian gate ke channel terminal tiap gate-in/out.
 * Auto-discovered via type-hint interface RecordsGateEvent.
 */
final class BroadcastGateQueue
{
    public function handle(RecordsGateEvent $event): void
    {
        $appointment = $event->gateAppointment();
        $appointment->loadMissing('slotWindow.gate');

        $terminalId = $appointment->slotWindow?->gate?->terminal_id;

        if ($terminalId === null) {
            return;
        }

        GateQueueUpdated::dispatch(
            $terminalId,
            $appointment->id,
            $appointment->booking_code,
            $appointment->status->value,
            $event->gateType()->value,
        );
    }
}
