import { expect, type Page } from '@playwright/test';

// Kredensial dari DemoSeeder (docs/DUMMY-DATA.md) — DB e2e di-seed ulang tiap
// run lewat global-setup.ts.
export const ACCOUNTS = {
    transporter: { email: 'dispatcher@majulog.test', password: 'password' },
    planner: { email: 'planner@tas.test', password: 'password' },
    gateOfficer: { email: 'gate@tas.test', password: 'password' },
} as const;

export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await expect(page).toHaveURL(/\/$/);
}

export async function logout(page: Page): Promise<void> {
    await page.getByTestId('logout').click();
    await expect(page).toHaveURL(/\/login$/);
}

export function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export function tomorrow(): string {
    return new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
}

export function uniqueContainerNo(prefix: string): string {
    // Backend: unique per (slot_window_id, container_no) di appointment aktif —
    // prefix per spec + timestamp cukup unik lintas run tanpa perlu counter global.
    return `${prefix}${Date.now().toString().slice(-7)}`;
}

/**
 * Booking lewat UI (/slots) — dipakai berulang di beberapa spec. `startTime`
 * mencocokkan teks kartu window (mis. "07:00") supaya deterministik lepas
 * dari urutan render, bukan index.
 */
export async function bookSlot(
    page: Page,
    opts: { gateLabel: string; date: string; startTime: string; containerNo: string },
): Promise<void> {
    await page.goto('/slots');
    await page.getByLabel('Gate').selectOption({ label: opts.gateLabel });
    await page.getByLabel('Tanggal').fill(opts.date);

    // Anchor ke AWAL teks kartu — "07:00" tanpa anchor cocok baik kartu
    // "06:00–07:00" (sbg jam akhir) maupun "07:00–08:00" (sbg jam mulai).
    const card = page.getByTestId('slot-card').filter({ hasText: new RegExp(`^${opts.startTime}`) });
    await expect(card).toBeVisible();
    await card.getByTestId('book-button').click();

    await page.getByTestId('booking-truck').selectOption({ index: 1 });
    await page.getByTestId('booking-driver').selectOption({ index: 1 });
    await page.getByTestId('booking-container-no').fill(opts.containerNo);
    await page.getByTestId('booking-submit').click();

    await expect(page.getByTestId('booking-success')).toBeVisible();
}

/**
 * "HH:MM" **UTC** — cocok dgn `today()` (juga UTC via `toISOString()`) dan
 * `APP_TIMEZONE=UTC` backend (`config/app.php`). **Bukan** `toTimeString()`
 * (jam lokal mesin): di mesin dev Windows ini lokal = UTC+7, jadi window yang
 * dimaksud "now ±" bisa meleset 7 jam dari `now()` sungguhan di server —
 * `gate-in-out.spec.ts` gagal 409 `gate_in_too_early` karena window yang
 * dibuka planner ternyata jauh di depan `now()` UTC backend, bukan di
 * sekitarnya seperti yang dimaksud.
 */
export function hhmm(d: Date): string {
    return d.toISOString().slice(11, 16);
}
