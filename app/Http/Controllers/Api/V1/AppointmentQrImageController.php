<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Services\AppointmentQrTokenService;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Gambar QR PNG on-demand (opsi B — cetak/email; render utama tetap di FE dari
 * `qr_token`, lihat AppointmentQrCode.vue). Dibuat DI MEMORI per request lewat
 * `endroid/qr-code`, TIDAK PERNAH `Storage::put()`. Kontennya token itu sendiri
 * (§AppointmentQrTokenService) — begitu token kedaluwarsa, gambar yang sudah
 * dicetak/di-screenshot memang wajar percuma, dan karena tak ada file yang
 * pernah tersimpan, tak ada apa pun yang perlu dibersihkan (tidak menambah
 * beban storage server, sesuai keputusan desain — lihat HANDOVER.md).
 */
final class AppointmentQrImageController
{
    public function __invoke(string $token, AppointmentQrTokenService $tokens): Response
    {
        $appointmentId = $tokens->verify($token);
        $appointment = Appointment::findOrFail($appointmentId);

        Gate::authorize('view', $appointment);

        $result = Builder::create()->data($token)->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
        ]);
    }
}
