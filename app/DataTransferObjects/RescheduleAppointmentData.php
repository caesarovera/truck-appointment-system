<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;

final class RescheduleAppointmentData extends Data
{
    public function __construct(
        public int $slotWindowId,
        public int $expectedVersion, // optimistic lock
    ) {}
}
