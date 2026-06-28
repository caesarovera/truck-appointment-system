<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

use Spatie\LaravelData\Data;

final class TerminalData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
    ) {}
}
