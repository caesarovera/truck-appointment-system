<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Truk/sopir yang dipilih bukan milik company si transporter → 422.
 * Pertahanan domain agar isolasi antar-company tidak bocor lewat booking.
 */
final class FleetOwnershipException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Truk atau sopir bukan milik perusahaan Anda.',
            'error' => 'fleet_ownership',
        ], 422);
    }
}
