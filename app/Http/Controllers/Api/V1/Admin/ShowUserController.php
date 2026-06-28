<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\UserRepositoryInterface;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Http\Resources\V1\AdminUserResource;
use App\Models\User;

final class ShowUserController
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function __invoke(AdminRequest $request, User $user): AdminUserResource
    {
        return AdminUserResource::make($this->users->find($user->id));
    }
}
