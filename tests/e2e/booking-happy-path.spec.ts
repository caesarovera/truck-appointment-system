import { test, expect } from '@playwright/test';
import { ACCOUNTS, bookSlot, login, tomorrow, uniqueContainerNo } from './helpers';

// Smoke test happy-path dasar. Gate B / besok dipakai (bukan Gate A hari ini)
// karena window itu satu-satunya yang dijamin belum "Berakhir" apa pun jam DB
// di-seed (lihat database/seeders/DemoSeeder.php). Jam 06:00 dipakai konsisten
// di seluruh spec e2e supaya tiap spec punya window sendiri (kapasitas 5/window,
// tak saling tabrakan) — lihat reschedule-cancel.spec.ts & booking-errors.spec.ts.
test('transporter login lalu booking slot berhasil', async ({ page }) => {
    await login(page, ACCOUNTS.transporter.email, ACCOUNTS.transporter.password);

    await bookSlot(page, {
        gateLabel: 'Gate B',
        date: tomorrow(),
        startTime: '06:00',
        containerNo: uniqueContainerNo('SMOKE'),
    });

    await expect(page.getByTestId('booking-success')).toContainText('Booking berhasil');
});
