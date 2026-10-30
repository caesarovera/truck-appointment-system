import { test, expect } from '@playwright/test';
import { ACCOUNTS, login } from './helpers';

// Guard akses & error SPA (temuan sweep Playwright 2026-10-30). Spec ini juga
// satu-satunya penjaga WIRING retry di resources/js/app.ts — app.ts adalah entry
// ber-efek samping yang tak pernah di-impor Vitest.

test('sopir membuka /admin lewat URL → pesan "Tidak punya akses", bukan halaman kosong', async ({ page }) => {
    await login(page, ACCOUNTS.driver.email, ACCOUNTS.driver.password);

    await page.goto('/admin');

    await expect(page.getByRole('heading', { name: 'Tidak punya akses' })).toBeVisible();
    await expect(page).toHaveURL(/\/forbidden\?from=\/admin$/);
});

test('URL yang tidak ada → halaman 404 dengan jalan kembali ke beranda', async ({ page }) => {
    await login(page, ACCOUNTS.driver.email, ACCOUNTS.driver.password);

    await page.goto('/tidak-ada');

    await expect(page.getByRole('heading', { name: 'Halaman tidak ditemukan' })).toBeVisible();
    await page.getByRole('link', { name: 'Ke Beranda' }).click();
    await expect(page.getByRole('heading', { name: /Selamat datang/ })).toBeVisible();
});

test('query yang dijawab 403 tidak dicoba ulang', async ({ page }) => {
    await login(page, ACCOUNTS.transporter.email, ACCOUNTS.transporter.password);
    let calls = 0;
    await page.route('**/api/v1/gates**', (route) => {
        calls++;
        return route.fulfill({ status: 403, contentType: 'application/json', body: '{"message":"Forbidden"}' });
    });

    await page.goto('/slots');
    // Default TanStack: 3 retry dgn backoff 1 s, 2 s, 4 s → ≥ 3 panggilan dalam 3,5 s.
    await page.waitForTimeout(3500);

    expect(calls).toBe(1);
});
