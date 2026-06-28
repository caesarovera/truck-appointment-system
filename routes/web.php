<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Catch-all: semua route non-API dilayani shell SPA Vue (routing di sisi klien).
// Kecualikan path API & infra (build, storage, dll. dilayani langsung server web).
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api).*$');
