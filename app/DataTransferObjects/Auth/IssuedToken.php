<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\Models\User;

final readonly class IssuedToken
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}
