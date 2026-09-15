<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

final class LogoutAction
{
    public function execute(User $user): void
    {
        // Docblock Sanctum menjanjikan PersonalAccessToken, tapi saat runtime bisa TransientToken
        // (actingAs / guard sesi) — hanya token nyata yang bisa dicabut.
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
