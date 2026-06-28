<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Window dengan (gate_id, date, start_time) yang sama sudah ada (pelanggaran unik
 * di slot_windows) → 409. Pertahanan terakhir bila dua planner membuka window
 * yang sama bersamaan.
 */
final class DuplicateSlotWindowException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Slot window untuk gate, tanggal, dan jam mulai itu sudah ada.',
            'error' => 'duplicate_slot_window',
        ], 409);
    }
}
