<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Truk yang dipilih berstatus INACTIVE (dipensiunkan: rusak/dijual/izin mati) → 422.
 *
 * Sejajar dengan FleetOwnershipException: sama-sama menolak pilihan armada yang
 * tidak sah pada saat booking. Dipisah karena sebabnya berbeda bagi transporter —
 * "bukan milik Anda" vs "aktifkan dulu truknya".
 */
final class InactiveTruckException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Truk berstatus INACTIVE tidak bisa dijadwalkan. Aktifkan dulu di halaman Armada.',
            'error' => 'truck_inactive',
        ], 422);
    }
}
