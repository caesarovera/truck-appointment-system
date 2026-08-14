import { test, expect } from '@playwright/test';
import { ACCOUNTS, bookSlot, hhmm, login, logout, today, uniqueContainerNo } from './helpers';

// Gate-in butuh appointment CONFIRMED di window yang "sedang berlangsung"
// (GateInAction menolak 409 gate_in_too_early/gate_in_too_late di luar
// toleransi config/tas.php gate_in — lihat BUSINESS-FLOW §3.5). Window
// hourly bawaan DemoSeeder (06:00–18:00) cuma valid kalau spec ini kebetulan
// jalan jam segitu — TIDAK dipakai di sini. Sebagai gantinya planner membuka
// window baru yang start/end-nya dihitung dari jam SEKARANG ("now" ±, appointment
// benar-benar "di dalam" window) — deterministik di jam berapa pun spec dijalankan.
test('planner buka window → transporter booking → gate-officer gate-in lalu gate-out', async ({ page }) => {
    const now = new Date();
    const start = hhmm(new Date(now.getTime() - 10 * 60_000));
    const end = hhmm(new Date(now.getTime() + 50 * 60_000));
    const date = today();
    const containerNo = uniqueContainerNo('GATE');

    // 1) Planner: buka window ad-hoc di Gate A (bukan Gate B — spec lain pakai
    // Gate B/besok, gate ini sengaja beda supaya tak bisa tabrakan sama sekali).
    await login(page, ACCOUNTS.planner.email, ACCOUNTS.planner.password);
    await page.goto('/planner');
    await page.getByLabel('Gate').selectOption({ label: 'Gate A' });
    await page.getByLabel('Tanggal').fill(date);
    await page.getByLabel('Mulai').fill(start);
    await page.getByLabel('Selesai').fill(end);
    await page.getByLabel('Kapasitas').fill('5');
    await page.getByTestId('open-window').click();
    await expect(page.getByText('Window dibuka.')).toBeVisible();
    await logout(page);

    // 2) Transporter: booking window yang baru dibuka.
    await login(page, ACCOUNTS.transporter.email, ACCOUNTS.transporter.password);
    await bookSlot(page, { gateLabel: 'Gate A', date, startTime: start, containerNo });
    await logout(page);

    // 3) Gate-officer: gate-in lalu gate-out. Booking di atas membuat status
    // langsung CONFIRMED (bukan lewat BOOKED — lihat AppointmentRepository),
    // jadi sudah eligible gate-in tanpa langkah konfirmasi terpisah.
    await login(page, ACCOUNTS.gateOfficer.email, ACCOUNTS.gateOfficer.password);
    await page.goto('/gate');

    const row = page.getByTestId('queue-row').filter({ hasText: containerNo });
    await expect(row).toBeVisible();
    await row.getByTestId('gate-in').click();
    await row.getByTestId('gate-in-confirm').click();

    await expect(row.getByTestId('gate-out')).toBeVisible();
    await row.getByTestId('gate-out').click();
    await row.getByTestId('gate-out-confirm').click();

    // COMPLETED tak lagi masuk antrian (queueForTerminal: WHERE status IN
    // (CONFIRMED, IN_PROGRESS)) — baris hilang dari daftar begitu gate-out sukses.
    await expect(row).toHaveCount(0);
});
