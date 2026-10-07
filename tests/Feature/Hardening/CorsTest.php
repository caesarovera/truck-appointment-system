<?php

declare(strict_types=1);

use function Pest\Laravel\call;
use function Pest\Laravel\withHeaders;

// Audit 2026-09-16 (c): config CORS tak ada → default framework `allowed_origins => ['*']`.
// SPA same-origin (baseURL '/api/v1'), jadi default aman = tak ada akses lintas-origin;
// origin SPA terpisah didaftarkan lewat env CORS_ALLOWED_ORIGINS.

it('does not allow cross origin access by default', function (): void {
    expect(config('cors.allowed_origins'))->toBe([]);

    withHeaders(['Origin' => 'https://evil.example'])
        ->postJson('/api/v1/login', [])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('answers a preflight from a foreign origin without granting access', function (): void {
    $response = call('OPTIONS', '/api/v1/login', server: [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('allows only the origins listed in config', function (): void {
    // Dua origin: dengan satu origin saja, library CORS selalu memantulkan origin tunggal itu pada
    // SEMUA respons (browser yang menolak yang tak cocok), jadi header tak pernah "hilang".
    config(['cors.allowed_origins' => ['https://spa.example', 'https://admin.example']]);

    withHeaders(['Origin' => 'https://spa.example'])
        ->postJson('/api/v1/login', [])
        ->assertHeader('Access-Control-Allow-Origin', 'https://spa.example');

    withHeaders(['Origin' => 'https://evil.example'])
        ->postJson('/api/v1/login', [])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
