<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Transisi status tidak sah (mis. cancel/reschedule setelah truk tiba) → 409.
 * State machine ditegakkan di Action, bukan lewat update() bebas (CLAUDE.md).
 */
final class InvalidAppointmentStateException extends RuntimeException
{
    public static function cannotCancel(): self
    {
        return new self('Appointment tidak bisa dibatalkan pada status saat ini.');
    }

    public static function cannotReschedule(): self
    {
        return new self('Appointment tidak bisa dijadwalkan ulang pada status saat ini.');
    }

    public static function cannotGateIn(): self
    {
        return new self('Appointment tidak bisa gate-in pada status saat ini (butuh CONFIRMED).');
    }

    public static function cannotGateOut(): self
    {
        return new self('Appointment tidak bisa gate-out pada status saat ini (butuh IN_PROGRESS).');
    }

    public static function cannotMarkNoShow(): self
    {
        return new self('Appointment tidak bisa ditandai no-show pada status saat ini.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_state',
        ], 409);
    }
}
