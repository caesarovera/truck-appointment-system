<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidQrTokenException;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Token QR gate-in (BUSINESS-FLOW §3.4/§3.5): "appointment id ter-sign", bukan
 * booking_code polos. Payload + tanda tangan HMAC-SHA256 memakai APP_KEY —
 * pola yang sama dengan signed URL bawaan Laravel, tapi dikemas sebagai
 * string mandiri (bukan URL penuh) supaya pas dijadikan konten QR.
 *
 * Murni derivasi tanpa side-effect/state (tidak query, tidak menulis apa
 * pun) — Service, bukan Action.
 */
final class AppointmentQrTokenService
{
    /**
     * TTL token = akhir window + toleransi telat gate-in (config/tas.php).
     * Selaras dengan batas gate-in itu sendiri: lewat itu gate-in memang
     * sudah pasti ditolak (409 gate_in_too_late), jadi QR wajar ikut basi
     * di titik yang sama — tak perlu TTL terpisah untuk dijaga sinkron.
     *
     * Butuh `slotWindow` sudah di-eager-load (dipanggil dari Resource lewat
     * `whenLoaded`) — sengaja tidak `loadMissing` di sini supaya konsisten
     * dengan `preventLazyLoading` (N+1 harus kelihatan dari titik pemanggil).
     */
    public function generate(Appointment $appointment): string
    {
        $window = $appointment->slotWindow;

        if ($window === null) {
            // `slot_window_id` wajib di skema, jadi ini hanya bisa kejadian
            // kalau relasi dipaksa null (mis. test) — bukan kondisi 409, tapi
            // bug pemanggilan yang wajar meledak dini.
            throw new RuntimeException('Appointment tanpa slotWindow tak bisa dibuatkan token QR.');
        }

        $expiresAt = $window->endsAt()->addMinutes((int) config('tas.gate_in.late_minutes'));
        $payload = "{$appointment->id}|{$expiresAt->getTimestamp()}";

        return $this->sign($payload);
    }

    /** @throws InvalidQrTokenException */
    public function verify(string $token): int
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            throw InvalidQrTokenException::malformed();
        }

        [$encodedPayload, $signature] = $parts;
        $payload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);

        if ($payload === false) {
            throw InvalidQrTokenException::malformed();
        }

        $expectedSignature = hash_hmac('sha256', $payload, (string) config('app.key'));

        if (! hash_equals($expectedSignature, $signature)) {
            throw InvalidQrTokenException::tampered();
        }

        $segments = explode('|', $payload, 2);

        if (count($segments) !== 2 || ! ctype_digit($segments[0]) || ! ctype_digit($segments[1])) {
            throw InvalidQrTokenException::malformed();
        }

        [$appointmentId, $expiresAtTimestamp] = $segments;

        if ((int) $expiresAtTimestamp < Carbon::now()->getTimestamp()) {
            throw InvalidQrTokenException::expired();
        }

        return (int) $appointmentId;
    }

    private function sign(string $payload): string
    {
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));
        $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        return "{$encodedPayload}.{$signature}";
    }
}
