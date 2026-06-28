<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class EntityInUseException extends RuntimeException
{
    public static function terminal(): self
    {
        return new self('Terminal masih memiliki gate. Hapus gate terlebih dahulu.');
    }

    public static function gate(): self
    {
        return new self('Gate masih memiliki slot window. Hapus slot window terlebih dahulu.');
    }

    public static function company(): self
    {
        return new self('Perusahaan masih memiliki data terkait (user/truk/appointment). Hapus semua data terkait terlebih dahulu.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'entity_in_use',
        ], 409);
    }
}
