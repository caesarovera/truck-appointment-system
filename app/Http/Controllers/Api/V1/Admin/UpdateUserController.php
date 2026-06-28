<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\UpdateUserAction;
use App\Http\Requests\V1\Admin\UpdateUserRequest;
use App\Http\Resources\V1\AdminUserResource;
use App\Models\User;

final class UpdateUserController
{
    public function __invoke(UpdateUserRequest $request, User $user, UpdateUserAction $action): AdminUserResource
    {
        return AdminUserResource::make($action->execute($user, $request->toData()));
    }
}
