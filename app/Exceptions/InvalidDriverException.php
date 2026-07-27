<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * `driver_id` menunjuk user se-company yang BUKAN ber-role `driver` → 422.
 *
 * Sejajar dengan InactiveTruckException: sama-sama menolak pilihan armada yang
 * lolos kepemilikan tapi tak layak dijadwalkan. Dipisah dari FleetOwnershipException
 * karena sebabnya berbeda bagi transporter — "bukan milik Anda" vs "itu bukan sopir".
 *
 * Kenapa ditegakkan di Action, bukan cukup menyaring dropdown `/me/fleet`:
 * role bisa dicabut selagi form booking terbuka, dan klien API mana pun bisa
 * mengirim id user sembarang se-company.
 */
final class InvalidDriverException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Sopir yang dipilih bukan akun ber-role sopir. Pilih sopir dari daftar armada Anda.',
            'error' => 'driver_invalid_role',
        ], 422);
    }
}
