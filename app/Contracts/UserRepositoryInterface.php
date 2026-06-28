<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Admin\UserData;
use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /** @return Collection<int, User> */
    public function all(?string $role = null): Collection;

    public function find(int $id): User;

    public function create(UserData $data): User;

    public function update(User $user, UserData $data): User;

    public function delete(User $user, User $actor): void;
}
