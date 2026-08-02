<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Truk datang di luar toleransi jendelanya (BUSINESS-FLOW §2 & §3.5) → 409.
 *
 * Sengaja TERPISAH dari InvalidAppointmentStateException: di sini status-nya
 * sah (CONFIRMED), yang salah cuma waktunya. Petugas gate perlu tahu bedanya —
 * "belum waktunya, suruh tunggu" ≠ "appointment ini memang tak bisa masuk".
 */
final class GateInWindowException extends RuntimeException
{
    private function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function tooEarly(): self
    {
        return new self('Truk datang terlalu awal dari jendela slotnya.', 'gate_in_too_early');
    }

    public static function tooLate(): self
    {
        return new self('Jendela slot untuk appointment ini sudah lewat.', 'gate_in_too_late');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->errorCode,
        ], 409);
    }
}
