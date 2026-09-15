<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Contracts\UserRepositoryInterface;
use App\DataTransferObjects\Auth\IssuedToken;
use App\DataTransferObjects\Auth\LoginData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function execute(LoginData $data): IssuedToken
    {
        $user = $this->users->findByEmail($data->email);

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            // Pesan generik (jangan bocorkan email mana yang terdaftar).
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Token abilities = permission milik role user (scope per role, BUSINESS-FLOW §1).
        $abilities = $user->getAllPermissions()->pluck('name')->all();

        return new IssuedToken(
            user: $user,
            plainTextToken: $user->createToken($data->deviceName, $abilities)->plainTextToken,
        );
    }
}
