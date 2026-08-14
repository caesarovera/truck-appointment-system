import { test, expect } from '@playwright/test';
import { ACCOUNTS, bookSlot, login, tomorrow, uniqueContainerNo } from './helpers';

// Window jam berbeda per spec (07:00 di sini, 08:00 di booking-errors.spec.ts,
// 06:00 di booking-happy-path.spec.ts) — supaya tak berebut kapasitas dgn spec
// lain yang jalan di sesi Playwright yang sama (1 DB e2e dipakai bareng, lihat
// global-setup.ts).

test('transporter reschedule appointment ke window lain', async ({ page }) => {
    const containerNo = uniqueContainerNo('RSC');
    await login(page, ACCOUNTS.transporter.email, ACCOUNTS.transporter.password);
    await bookSlot(page, { gateLabel: 'Gate B', date: tomorrow(), startTime: '07:00', containerNo });

    await page.goto('/bookings');
    const row = page.getByTestId('booking-row').filter({ hasText: containerNo });
    await expect(row).toBeVisible();
    await expect(row).toContainText('07:00');

    await row.getByTestId('reschedule-button').click();
    // Window tujuan beda jam dari yang sedang dibooking (bukan index — deterministik
    // lepas dari urutan render list, sama seperti bookSlot).
    await page.getByTestId('window-option').filter({ hasText: /^09:00/ }).click();
    await page.getByTestId('reschedule-submit').click();

    // Dialog tertutup (RescheduleDialog unmount setelah event `rescheduled`).
    await expect(page.getByTestId('reschedule-submit')).toHaveCount(0);
    await expect(row).toContainText('09:00');
});

test('transporter batalkan appointment', async ({ page }) => {
    const containerNo = uniqueContainerNo('CXL');
    await login(page, ACCOUNTS.transporter.email, ACCOUNTS.transporter.password);
    await bookSlot(page, { gateLabel: 'Gate B', date: tomorrow(), startTime: '08:00', containerNo });

    await page.goto('/bookings');
    const row = page.getByTestId('booking-row').filter({ hasText: containerNo });
    await expect(row).toBeVisible();

    await row.getByTestId('cancel-button').click();
    await row.getByTestId('confirm-cancel').click();

    await expect(row).toContainText('CANCELLED');
    // Pasca-cancel appointment tak lagi manageable (isCancellable() false) —
    // tombol reschedule/cancel hilang, bukan cuma badge yang berubah.
    await expect(row.getByTestId('cancel-button')).toHaveCount(0);
    await expect(row.getByTestId('reschedule-button')).toHaveCount(0);
});
