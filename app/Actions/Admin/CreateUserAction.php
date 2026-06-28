<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\UserRepositoryInterface;
use App\DataTransferObjects\Admin\UserData;
use App\Models\User;

final class CreateUserAction
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function execute(UserData $data): User
    {
        return $this->users->create($data);
    }
}
