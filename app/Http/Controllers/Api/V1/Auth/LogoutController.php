<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LogoutAction;
use App\Http\Resources\V1\MessageResource;
use App\Models\User;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request, LogoutAction $action): MessageResource
    {
        /** @var User $user */
        $user = $request->user();
        $action->execute($user);

        return MessageResource::make('Logged out.');
    }
}
