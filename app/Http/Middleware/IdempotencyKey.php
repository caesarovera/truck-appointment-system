<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key untuk endpoint mutasi (booking, gate event). Mobile sopir/
 * transporter sering double-tap. Strategi:
 *  - Tanpa header → lewat (header opsional, tapi sangat disarankan klien kirim).
 *  - Header sama & respons sukses sebelumnya tersimpan → putar ulang respons itu
 *    (tidak membuat record baru).
 *  - Permintaan kembar masih in-flight → 409 (lock belum lepas).
 *
 * Nilai header di-hash (sha256) jadi kunci cache: panjang/karakter apa pun aman
 * lintas store (mis. batas 250 char memcached) & tak bisa "menyuntik" kunci.
 */
final class IdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request, $key);

        $replay = $this->replay($cacheKey);
        if ($replay !== null) {
            return $replay;
        }

        // TTL lock > durasi worst-case handler → cegah lock kedaluwarsa di tengah
        // jalan (yang akan membiarkan request kembar menyelinap). Lihat config/tas.php.
        $lockSeconds = (int) config('tas.idempotency.lock_seconds', 60);
        $lock = Cache::lock("{$cacheKey}:lock", $lockSeconds);

        if (! $lock->get()) {
            abort(Response::HTTP_CONFLICT, 'Permintaan dengan Idempotency-Key yang sama sedang diproses.');
        }

        try {
            $response = $next($request);

            if ($response instanceof JsonResponse && $response->getStatusCode() < 400) {
                $ttlHours = (int) config('tas.idempotency.ttl_hours', 24);
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getData(true),
                ], now()->addHours($ttlHours));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Kunci cache ber-scope user/ip + endpoint + hash nilai header (bounded & aman).
     * Endpoint (method+path) ikut di-hash: key sama yang dipakai ulang di endpoint
     * berbeda (mis. booking lalu gate-in) TIDAK boleh memutar ulang respons endpoint
     * lain — idempotency berlaku per operasi, bukan per nilai header global.
     */
    private function cacheKey(Request $request, string $key): string
    {
        $scope = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        return 'idem:'.$scope.':'.hash('sha256', $request->method().'|'.$request->path().'|'.$key);
    }

    private function replay(string $cacheKey): ?JsonResponse
    {
        $stored = Cache::get($cacheKey);

        if (! is_array($stored) || ! isset($stored['status'], $stored['body'])) {
            return null;
        }

        $status = is_int($stored['status']) ? $stored['status'] : Response::HTTP_OK;
        $body = is_array($stored['body']) ? $stored['body'] : [];

        return response()->json($body, $status)->header('Idempotent-Replayed', 'true');
    }
}
