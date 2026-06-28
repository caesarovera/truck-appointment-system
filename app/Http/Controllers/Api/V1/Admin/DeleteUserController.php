<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\DeleteUserAction;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Models\User;
use Illuminate\Http\Response;

final class DeleteUserController
{
    public function __invoke(AdminRequest $request, User $user, DeleteUserAction $action): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->execute($user, $actor);

        return response()->noContent();
    }
}
