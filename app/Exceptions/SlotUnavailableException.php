<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Window penuh atau ditutup saat booking → 409 Conflict.
 * Laravel otomatis memakai method render() ini untuk membentuk response.
 */
final class SlotUnavailableException extends RuntimeException
{
    public static function full(): self
    {
        return new self('Kuota slot sudah penuh.');
    }

    public static function closed(): self
    {
        return new self('Slot window sudah ditutup untuk booking.');
    }

    public static function expired(): self
    {
        return new self('Slot window sudah berakhir.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'slot_unavailable',
        ], 409);
    }
}
