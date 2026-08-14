import { test, expect } from '@playwright/test';
import { ACCOUNTS, bookSlot, login, tomorrow, uniqueContainerNo } from './helpers';

// Error path yang bisa dipicu deterministik lewat UI tanpa mengandalkan race
// condition: kontainer duplikat di window yang sama (unique constraint
// `(slot_window_id, container_no)` — hardening §Idempotency di CLAUDE.md).
// Window "penuh" TIDAK bisa dites lewat UI — tombol Booking disembunyikan
// begitu `remaining <= 0` (SlotAvailabilityPage.vue), jadi 409 kuota-habis
// cuma bisa dipicu lewat race 2 request bersamaan, di luar cakupan e2e
// single-browser ini (sudah ditutupi Pest — lihat tests/Feature/Booking).
test('booking gagal — kontainer sama dibooking dua kali di window sama', async ({ page }) => {
    const containerNo = uniqueContainerNo('DUP');
    await login(page, ACCOUNTS.transporter.email, ACCOUNTS.transporter.password);

    await bookSlot(page, { gateLabel: 'Gate B', date: tomorrow(), startTime: '11:00', containerNo });

    // Percobaan kedua: window & container_no identik → 409 duplicate_booking.
    await page.goto('/slots');
    await page.getByLabel('Gate').selectOption({ label: 'Gate B' });
    await page.getByLabel('Tanggal').fill(tomorrow());

    const card = page.getByTestId('slot-card').filter({ hasText: /^11:00/ });
    await card.getByTestId('book-button').click();
    await page.getByTestId('booking-truck').selectOption({ index: 1 });
    await page.getByTestId('booking-driver').selectOption({ index: 1 });
    await page.getByTestId('booking-container-no').fill(containerNo);
    await page.getByTestId('booking-submit').click();

    await expect(page.getByTestId('booking-error')).toBeVisible();
    await expect(page.getByTestId('booking-error')).toContainText('sudah dibooking');
    // Gagal — bukan dialog booking baru yang nutup sendiri.
    await expect(page.getByTestId('booking-submit')).toBeVisible();
});
