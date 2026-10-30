<?php

declare(strict_types=1);

use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withoutVite;

// Backlog B6 (d), slice 1/2: header keamanan dasar di SEMUA respons (API + SPA shell) dan
// HSTS hanya lewat HTTPS. CSP sengaja menyusul di slice 2 (risiko merusak SPA lebih besar).

it('adds the baseline security headers to API responses, including errors', function (): void {
    // 401 dirender exception handler — header tetap harus ada, bukan hanya di 200.
    getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('adds the baseline security headers to the SPA shell', function (): void {
    // Job Backend di CI tak menjalankan `npm run build` → tanpa ini @vite gagal (manifest tak ada).
    withoutVite();

    get('/bookings')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('adds the headers to responses produced by framework middleware too', function (): void {
    // Review 2026-10-30: dengan append() middleware ini paling DALAM, jadi respons yang lahir di
    // middleware global bawaan (ValidatePathEncoding 400, HandleCors preflight, ValidatePostSize
    // 413, maintenance 503) lolos tanpa header. Harus prepend() — paling luar.
    get('/%FF')
        ->assertStatus(400)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');

    call('OPTIONS', '/api/v1/login', server: [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ])
        ->assertNoContent()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('never sends HSTS over plain HTTP', function (): void {
    // HSTS lewat HTTP diabaikan browser (RFC 6797 §8.1) — dan di dev justru menjebak bila
    // suatu saat localhost dilayani HTTPS lalu kembali ke HTTP.
    getJson('/api/v1/me')->assertHeaderMissing('Strict-Transport-Security');
});

it('sends HSTS over HTTPS with the configured max-age', function (): void {
    config(['tas.security.hsts_max_age' => 600]);

    getJson('https://localhost/api/v1/me')
        ->assertHeader('Strict-Transport-Security', 'max-age=600');
});

it('can switch HSTS off with a zero max-age', function (): void {
    config(['tas.security.hsts_max_age' => 0]);

    getJson('https://localhost/api/v1/me')->assertHeaderMissing('Strict-Transport-Security');
});

// Reverb mencocokkan HOST Origin saja (parse_url → Str::is), bukan URL lengkap — maka isinya
// daftar host. Cek Origin hanya menahan BROWSER; isolasi data tetap tugas channel auth.

/**
 * Evaluasi ulang config/reverb.php dengan env tertentu (config sudah di-load saat boot).
 *
 * @return list<string>
 */
function reverbAllowedOriginsWith(?string $origins, string $appUrl): array
{
    $keys = ['REVERB_ALLOWED_ORIGINS' => $origins, 'APP_URL' => $appUrl];
    $backup = [];

    foreach ($keys as $key => $value) {
        $backup[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    try {
        /** @var array{apps: array{apps: list<array{allowed_origins: list<string>}>}} $config */
        $config = require config_path('reverb.php');

        return $config['apps']['apps'][0]['allowed_origins'];
    } finally {
        foreach ($backup as $key => [$env, $server]) {
            $_ENV[$key] = $env;
            $_SERVER[$key] = $server;
        }
    }
}

it('limits Reverb origins to the APP_URL host by default, never *', function (): void {
    $origins = config('reverb.apps.apps.0.allowed_origins');

    expect($origins)
        ->toBe([parse_url((string) config('app.url'), PHP_URL_HOST)])
        ->not->toContain('*');

    expect(reverbAllowedOriginsWith('', 'https://tas.example.com'))->toBe(['tas.example.com']);
});

it('takes Reverb origins from REVERB_ALLOWED_ORIGINS as a host list', function (): void {
    expect(reverbAllowedOriginsWith(' spa.example.com , ,*.tas.example.com ', 'https://tas.example.com'))
        ->toBe(['spa.example.com', '*.tas.example.com']);
});

it('drops wildcard entries that would open Reverb wider than intended', function (): void {
    // `*` → Reverb meloloskan SEMUA origin (in_array('*')). `*example.com` → Str::is juga cocok
    // dengan `evilexample.com`. Hanya awalan `*.` utuh yang sah. Semua entri tak sah → default.
    expect(reverbAllowedOriginsWith('*', 'https://tas.example.com'))->toBe(['tas.example.com'])
        ->and(reverbAllowedOriginsWith('spa.example.com,*example.com,*.tas.example.com,a*.b.com,*.*.c.com', 'https://tas.example.com'))
        ->toBe(['spa.example.com', '*.tas.example.com']);
});

it('rejects every Reverb origin when APP_URL has no host and no list is given', function (): void {
    // Gagal tertutup: lebih baik realtime diam daripada kembali ke `*`.
    expect(reverbAllowedOriginsWith('', ''))->toBe([]);
});
