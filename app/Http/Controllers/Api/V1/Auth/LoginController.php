<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Resources\V1\LoginResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class LoginController
{
    public function __invoke(LoginRequest $request, LoginAction $action): JsonResponse
    {
        return LoginResource::make($action->execute($request->toData()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
