<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;

final class DeleteUserAction
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function execute(User $user, User $actor): void
    {
        $this->users->delete($user, $actor);
    }
}
