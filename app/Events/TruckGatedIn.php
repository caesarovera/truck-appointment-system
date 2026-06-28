<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\RecordsGateEvent;
use App\Enums\GateTransactionType;
use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Dipancarkan setelah gate-in commit (status → IN_PROGRESS). */
final class TruckGatedIn implements RecordsGateEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function gateAppointment(): Appointment
    {
        return $this->appointment;
    }

    public function gateType(): GateTransactionType
    {
        return GateTransactionType::IN;
    }
}
