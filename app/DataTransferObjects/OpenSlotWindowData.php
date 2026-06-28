<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;

/** Input pembukaan slot window oleh planner, sudah tervalidasi & bertipe kuat. */
final class OpenSlotWindowData extends Data
{
    public function __construct(
        public int $gateId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public int $capacity,
    ) {}
}
