<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * `version` yang dikirim tidak cocok dengan baris → ada yang mengubah duluan.
 * 409 supaya klien re-fetch dan coba lagi (transporter & planner bisa edit bersamaan).
 */
final class OptimisticLockException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Appointment sudah berubah sejak terakhir dimuat. Muat ulang lalu coba lagi.',
            'error' => 'version_conflict',
        ], 409);
    }
}
