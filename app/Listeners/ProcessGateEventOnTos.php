<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\RecordsGateEvent;
use App\Jobs\ProcessGateEventJob;

/**
 * Teruskan setiap gate event ke TOS terminal lewat job antrian (efek samping
 * di luar transaksi). Auto-discovered via type-hint interface RecordsGateEvent.
 */
final class ProcessGateEventOnTos
{
    public function handle(RecordsGateEvent $event): void
    {
        ProcessGateEventJob::dispatch($event->gateAppointment()->getKey(), $event->gateType());
    }
}
