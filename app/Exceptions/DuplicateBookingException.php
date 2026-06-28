<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Kontainer sudah punya booking aktif di window yang sama (pelanggaran unik
 * (slot_window_id, container_no)) → 409. Ini pertahanan terakhir bila idempotency
 * di lapisan HTTP terlewat (mis. double-tap mobile).
 */
final class DuplicateBookingException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Kontainer ini sudah dibooking di slot tersebut.',
            'error' => 'duplicate_booking',
        ], 409);
    }
}
