<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ResolveQrAppointmentAction;
use App\Services\AppointmentQrTokenService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Gambar QR PNG on-demand untuk cetak/email; render utama tetap di FE dari `qr_token`
 * (AppointmentQrCode.vue).
 */
final class AppointmentQrImageController
{
    public function __invoke(string $token, ResolveQrAppointmentAction $resolve, AppointmentQrTokenService $tokens): Response
    {
        Gate::authorize('view', $resolve->execute($token));

        return response($tokens->renderPng($token), 200, ['Content-Type' => 'image/png']);
    }
}
