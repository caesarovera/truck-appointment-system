<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GateEventGateway;
use App\Enums\GateTransactionType;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

/**
 * Implementasi default TOS gateway: catat ke log. Placeholder sampai integrasi
 * TOS riil (HTTP client) menggantikan binding di AppServiceProvider.
 */
final class LoggingGateEventGateway implements GateEventGateway
{
    public function push(Appointment $appointment, GateTransactionType $type): void
    {
        Log::info('Gate event pushed to TOS', [
            'appointment_id' => $appointment->id,
            'booking_code' => $appointment->booking_code,
            'type' => $type->value,
        ]);
    }
}
