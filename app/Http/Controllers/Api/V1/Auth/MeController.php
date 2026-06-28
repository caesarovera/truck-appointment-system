<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MeController
{
    public function __invoke(Request $request): UserResource
    {
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        return new UserResource($user);
    }
}
