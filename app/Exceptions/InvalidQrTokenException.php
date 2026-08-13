<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Token QR gate-in tak valid → 403 Forbidden. Bukan 422: token yang sampai ke
 * endpoint verifikasi/gambar sudah lolos routing sebagai string biasa — yang
 * gagal adalah keasliannya (tanda tangan/format/kedaluwarsa), bukan bentuk
 * input HTTP-nya. Laravel otomatis memakai render() ini untuk membentuk
 * response, sama seperti SlotUnavailableException.
 */
final class InvalidQrTokenException extends RuntimeException
{
    public static function malformed(): self
    {
        return new self('Format token QR tidak valid.');
    }

    public static function tampered(): self
    {
        return new self('Token QR tidak valid atau telah diubah.');
    }

    public static function expired(): self
    {
        return new self('Token QR sudah kedaluwarsa.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_qr_token',
        ], 403);
    }
}
