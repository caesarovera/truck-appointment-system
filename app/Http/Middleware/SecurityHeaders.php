<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk SEMUA respons (API JSON + SPA shell), termasuk respons
 * error — dipasang global di bootstrap/app.php. Backlog B6 (d), slice 1/2.
 *  - nosniff        : browser tak menebak tipe konten (JSON tak bisa dijalankan sebagai script).
 *  - X-Frame-Options: SPA tak boleh di-iframe (clickjacking). Tak ada kebutuhan embed.
 *  - Referrer-Policy: URL lengkap (mis. /appointments/{id}) tak bocor ke situs lain.
 *  - HSTS           : HANYA lewat HTTPS — lewat HTTP diabaikan browser (RFC 6797 §8.1).
 *                     Tanpa includeSubDomains/preload: preload sulit ditarik kembali.
 *                     Di belakang reverse proxy, isSecure() butuh TrustProxies (B6).
 * CSP sengaja belum (slice 2): risikonya merusak SPA (Vite HMR, style inline, WebSocket).
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $maxAge = (int) config('tas.security.hsts_max_age');
        if ($request->isSecure() && $maxAge > 0) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.$maxAge);
        }

        return $response;
    }
}
