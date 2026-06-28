<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\GateTransactionType;
use App\Models\Appointment;

/**
 * Seam ke Terminal Operating System (TOS). ProcessGateEventJob mendorong gate
 * event ke sini (di luar transaksi DB). Implementasi nyata di-swap saat
 * integrasi TOS; default LoggingGateEventGateway hanya mencatat.
 */
interface GateEventGateway
{
    public function push(Appointment $appointment, GateTransactionType $type): void;
}
