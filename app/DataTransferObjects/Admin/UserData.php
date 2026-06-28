<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

use Spatie\LaravelData\Data;

final class UserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $role,
        public ?string $password = null,
        public ?int $terminalId = null,
        public ?int $companyId = null,
    ) {}
}
