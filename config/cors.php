<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | SPA dilayani dari origin yang sama dengan API (baseURL '/api/v1'), jadi default
    | = TIDAK ada akses lintas-origin. Bila SPA dipindah ke domain sendiri, isi
    | CORS_ALLOWED_ORIGINS (dipisah koma), mis. "https://tas.example.com". Jangan `*`.
    | Itu BELUM cukup: SPA memakai path relatif (`api/client.ts` baseURL '/api/v1', `echo.ts`
    | authEndpoint '/broadcasting/auth') dan `paths` di bawah tak memuat `broadcasting/*`.
    | Auth memakai Bearer token (bukan cookie) → supports_credentials tetap false.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
