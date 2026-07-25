<?php

use App\Http\Middleware\IdempotencyKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    })
    // Wajib dipanggil walau kosong: registrasi binding exception handler framework
    // terjadi di sini — menghapusnya bikin BindingResolutionException saat request.
    // (Param $exceptions sengaja tak dipakai; extension point utk kustom error nanti.)
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
