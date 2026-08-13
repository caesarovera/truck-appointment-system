<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Role `admin` sengaja tak bisa diedit permission-nya lewat UI — mencegah admin
 * mengunci diri sendiri keluar sistem (hapus `role.manage`/`user.manage` dari
 * role sendiri = tak ada jalan balik tanpa akses DB langsung).
 */
final class ImmutableRoleException extends RuntimeException
{
    public static function admin(): self
    {
        return new self('Permission role admin tidak bisa diubah lewat UI.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'role_immutable',
        ], 422);
    }
}
