<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

use Spatie\LaravelData\Data;

final class GateData extends Data
{
    public function __construct(
        public int $terminalId,
        public string $code,
        public string $name,
    ) {}
}
