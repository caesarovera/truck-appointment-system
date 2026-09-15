<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * API wajib membalas JSON walau klien tak mengirim `Accept: application/json`
 * (curl polos, integrasi pihak ketiga, URL dibuka di browser). Tanpa itu Laravel
 * memperlakukan request sebagai halaman web: 401 jadi 500 (`Route [login] not
 * defined`), 422 jadi redirect 302, 404 jadi halaman HTML.
 */

it('answers an unauthenticated API call with 401 JSON', function (): void {
    get('/api/v1/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('answers an invalid API payload with 422 JSON instead of a redirect', function (): void {
    post('/api/v1/login')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('answers an unknown API route with 404 JSON', function (): void {
    get('/api/v1/tidak-ada')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('answers an unauthenticated broadcasting auth with 401 JSON', function (): void {
    post('/broadcasting/auth')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('still serves the SPA shell as HTML for web routes', function (): void {
    get('/bookings')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});
