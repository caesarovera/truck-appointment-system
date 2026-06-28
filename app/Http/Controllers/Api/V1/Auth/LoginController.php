<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class LoginController
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            // Pesan generik (jangan bocorkan email mana yang terdaftar).
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Token abilities = permission milik role user (scope per role, BUSINESS-FLOW §1).
        $abilities = $user->getAllPermissions()->pluck('name')->all();

        $token = $user->createToken($request->deviceName(), $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }
}
