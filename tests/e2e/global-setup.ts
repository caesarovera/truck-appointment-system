import { execSync } from 'node:child_process';
import { closeSync, existsSync, openSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Jalan sekali sebelum semua spec e2e: pastikan `.env.e2e` ada+ber-APP_KEY,
// build asset produksi (blade @vite butuh manifest — bukan Vite dev server),
// lalu migrate:fresh --seed ke DB e2e (database/database.e2e.sqlite — TERPISAH
// dari DB dev, lihat ADR-0005 §Kapan ditinjau ulang & .env.e2e.example).
export default function globalSetup(): void {
    const root = resolve(__dirname, '../..');
    const envFile = resolve(root, '.env.e2e');

    if (!existsSync(envFile)) {
        throw new Error(
            '.env.e2e tidak ditemukan. Jalankan dulu:\n' +
                '  cp .env.e2e.example .env.e2e\n' +
                '  php artisan key:generate --env=e2e\n' +
                'Lihat docs/SETUP-GUIDE.md §15.',
        );
    }

    const envContent = readFileSync(envFile, 'utf-8');
    if (/^APP_KEY=\s*$/m.test(envContent)) {
        throw new Error('.env.e2e ada tapi APP_KEY kosong. Jalankan: php artisan key:generate --env=e2e');
    }

    const env = { ...process.env, APP_ENV: 'e2e' };

    // Laravel tak membuat file sqlite sendiri — tanpa ini migrate gagal
    // "unable to open database file".
    const dbFile = resolve(root, 'database/database.e2e.sqlite');
    if (!existsSync(dbFile)) {
        closeSync(openSync(dbFile, 'w'));
    }

    execSync('npm run build', { cwd: root, stdio: 'inherit', env });
    execSync('php artisan migrate:fresh --seed --env=e2e', { cwd: root, stdio: 'inherit', env });
}
