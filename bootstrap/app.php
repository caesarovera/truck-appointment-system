<?php

use App\Http\Middleware\IdempotencyKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // SPA memakai Bearer token Sanctum (localStorage), bukan session cookie —
    // maka /broadcasting/auth wajib ber-guard `auth:sanctum`, bukan `web`
    // (default framework bila channels didaftarkan lewat withRouting(channels:)).
    // Didaftarkan DI SINI, bukan di withRouting, supaya channels.php tak ter-load
    // ganda (route /broadcasting/auth terdaftar sekali).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => IdempotencyKey::class,
        ]);
        // Default framework `route('login')` dievaluasi di middleware SEBELUM exception dilempar,
        // dan route bernama `login` tak ada (API-only) → 500. Tak ada halaman web ber-login
        // (SPA catch-all publik), jadi tamu tak pernah di-redirect; handler membalas 401 JSON.
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    // Wajib dipanggil: registrasi binding exception handler framework terjadi di sini.
    // API selalu JSON walau tanpa `Accept: application/json` — kalau tidak, 401 jadi 500
    // (`Route [login] not defined`), 422 jadi redirect 302, 404 jadi halaman HTML.
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*', 'broadcasting/*') || $request->expectsJson(),
        );
    })->create();
