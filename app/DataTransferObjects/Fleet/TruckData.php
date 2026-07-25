<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Fleet;

use App\Enums\TruckStatus;
use Spatie\LaravelData\Data;

final class TruckData extends Data
{
    public function __construct(
        public string $plateNo,
        public TruckStatus $status,
    ) {}
}
