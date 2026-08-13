import { defineConfig, devices } from '@playwright/test';

// E2E lokal-only untuk sekarang (belum di CI) — lihat ADR-0005 §Kapan
// ditinjau ulang & docs/SETUP-GUIDE.md §15. DB terpisah dari dev
// (database/database.e2e.sqlite via .env.e2e) supaya `migrate:fresh --seed`
// di globalSetup tak pernah menyentuh data dev.
const PORT = 8010;
const BASE_URL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        // Asset produksi (npm run build), bukan Vite dev server — 1 proses
        // saja yang perlu di-boot/dimatikan Playwright, dan lebih dekat ke
        // apa yang beneran di-deploy.
        command: 'php artisan serve --env=e2e --port=8010',
        url: BASE_URL,
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
        env: { APP_ENV: 'e2e' },
    },
});
