<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CreateUserAction;
use App\Http\Requests\V1\Admin\CreateUserRequest;
use App\Http\Resources\V1\AdminUserResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateUserController
{
    public function __invoke(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute($request->toData());

        return AdminUserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
