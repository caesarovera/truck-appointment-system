<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\GateTransactionType;
use App\Models\Appointment;

/**
 * Ditandai oleh event gate (TruckGatedIn/TruckGatedOut). Satu listener
 * (ProcessGateEventOnTos) mencocokkan lewat interface ini & mendorong
 * ProcessGateEventJob ke TOS terminal — mirror pola AffectsSlotAvailability.
 */
interface RecordsGateEvent
{
    public function gateAppointment(): Appointment;

    public function gateType(): GateTransactionType;
}
