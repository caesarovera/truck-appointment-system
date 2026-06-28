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
 */
final class IdempotencyKey
{
    private const TTL_HOURS = 24;

    private const LOCK_SECONDS = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $scope = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
        $cacheKey = "idem:{$scope}:{$key}";

        $replay = $this->replay($cacheKey);
        if ($replay !== null) {
            return $replay;
        }

        $lock = Cache::lock("{$cacheKey}:lock", self::LOCK_SECONDS);

        if (! $lock->get()) {
            abort(Response::HTTP_CONFLICT, 'Permintaan dengan Idempotency-Key yang sama sedang diproses.');
        }

        try {
            $response = $next($request);

            if ($response instanceof JsonResponse && $response->getStatusCode() < 400) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getData(true),
                ], now()->addHours(self::TTL_HOURS));
            }

            return $response;
        } finally {
            $lock->release();
        }
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
