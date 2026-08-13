import { test, expect } from '@playwright/test';

// Smoke test tunggal (scope disepakati: 1 e2e happy-path, bukan suite penuh).
// Kredensial & data dari DemoSeeder (docs/DUMMY-DATA.md) — DB e2e di-seed
// ulang tiap run lewat global-setup.ts. Gate B / besok dipakai (bukan Gate A
// hari ini) karena window itu satu-satunya yang dijamin belum "Berakhir"
// apa pun jam DB di-seed (lihat database/seeders/DemoSeeder.php).
test('transporter login lalu booking slot berhasil', async ({ page }) => {
    await page.goto('/login');
    await page.getByTestId('login-email').fill('dispatcher@majulog.test');
    await page.getByTestId('login-password').fill('password');
    await page.getByTestId('login-submit').click();

    await expect(page).toHaveURL(/\/$/);

    await page.goto('/slots');
    await page.getByLabel('Gate').selectOption({ label: 'Gate B' });

    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    await page.getByLabel('Tanggal').fill(tomorrow);

    const card = page.getByTestId('slot-card').first();
    await expect(card).toBeVisible();
    await card.getByTestId('book-button').click();

    await page.getByTestId('booking-truck').selectOption({ index: 1 });
    await page.getByTestId('booking-driver').selectOption({ index: 1 });
    await page.getByTestId('booking-container-no').fill(`E2E${Date.now().toString().slice(-7)}`);
    await page.getByTestId('booking-submit').click();

    await expect(page.getByTestId('booking-success')).toBeVisible();
    await expect(page.getByTestId('booking-success')).toContainText('Booking berhasil');
});
