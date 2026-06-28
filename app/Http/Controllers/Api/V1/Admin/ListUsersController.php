<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\UserRepositoryInterface;
use App\Http\Requests\V1\Admin\ListUsersRequest;
use App\Http\Resources\V1\AdminUserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListUsersController
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function __invoke(ListUsersRequest $request): AnonymousResourceCollection
    {
        return AdminUserResource::collection($this->users->all($request->role()));
    }
}
