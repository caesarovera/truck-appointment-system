# Truck Appointment System (TAS)

Sistem penjadwalan kedatangan truk ke terminal peti kemas: perusahaan angkutan
**booking slot** gate berkuota per jendela waktu, truk datang di slotnya, petugas
gate melakukan **gate-in → bongkar/muat → gate-out**. Tujuan: meratakan antrian,
cegah penumpukan, audit penuh.

Arsitektur **API-first decoupled**: Laravel 12 (REST API murni) + Vue 3 (SPA terpisah).

## Stack
**Backend:** Laravel 12 · PHP 8.3+ · Sanctum · Redis/Horizon · Reverb (WebSocket) ·
Spatie Permission/Activity Log/Laravel Data · Pest · PHPStan level 8 (Larastan) · Pint.
**Frontend:** Vue 3 (`<script setup>` + TS) · Pinia · Vue Router · TanStack Query ·
Axios · Vitest (di `resources/js`, lihat [`docs/FRONTEND.md`](docs/FRONTEND.md)).

Status: backend MVP API lengkap & ber-test (**152 Pest**); SPA mencakup UI **4 persona**
(transporter, driver, gate-officer, planner) + **CRUD master data admin** (terminal/gate/
company/user) (**57 Vitest**). Detail hidup: [`HANDOVER.md`](HANDOVER.md).

## Mulai dari mana (urutan onboarding)

Developer baru, baca berurutan:
1. **README ini** — gambaran + cara jalankan (di bawah).
2. [`CLAUDE.md`](CLAUDE.md) — kontrak arsitektur. **Wajib sebelum menulis kode.**
3. [`docs/SETUP-GUIDE.md`](docs/SETUP-GUIDE.md) — siapkan & jalankan project langkah-demi-langkah.
4. [`docs/BUSINESS-FLOW.md`](docs/BUSINESS-FLOW.md) — domain: RBAC, state machine, alur, ERD.
5. [`docs/CODE-WALKTHROUGH.md`](docs/CODE-WALKTHROUGH.md) (backend) & [`docs/FRONTEND.md`](docs/FRONTEND.md) (SPA) — "kenapa" tiap kode.
6. [`HANDOVER.md`](HANDOVER.md) — status terkini & langkah berikutnya. **Baca di awal tiap sesi.**

## Peta dokumentasi (baca sesuai kebutuhan)

| Dokumen | Isi | Baca saat |
|---------|-----|-----------|
| [`CLAUDE.md`](CLAUDE.md) | **Kontrak arsitektur** (aturan layer, hardening, larangan) | sebelum menulis kode apa pun |
| [`docs/PRD.md`](docs/PRD.md) | **Kenapa** & batas scope MVP | menentukan scope |
| [`docs/BUSINESS-FLOW.md`](docs/BUSINESS-FLOW.md) | **Apa**-nya: RBAC §1 · state machine §2 · alur §3 · ERD §4 | menyentuh status/akses/skema |
| [`docs/SETUP-GUIDE.md`](docs/SETUP-GUIDE.md) | **Buku panduan setup & build manual** langkah-demi-langkah | menyiapkan/menjalankan project |
| [`docs/CODE-WALKTHROUGH.md`](docs/CODE-WALKTHROUGH.md) | **Penjelasan detail kode backend** + contoh | memahami "kenapa" sebuah kode backend |
| [`docs/FRONTEND.md`](docs/FRONTEND.md) | **Penjelasan detail frontend** (Vue SPA): arsitektur, TanStack Query, tiap halaman + "kenapa" | menyentuh/memahami SPA |
| [`docs/DUMMY-DATA.md`](docs/DUMMY-DATA.md) | Akun & data demo | butuh data uji |
| [`HANDOVER.md`](HANDOVER.md) | Status hidup antar-sesi + langkah berikutnya | awal tiap sesi |

## Mulai cepat

```bash
# Prasyarat: PHP 8.3+, Composer 2.8+, driver pdo_sqlite aktif (lihat SETUP-GUIDE §1)
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed     # bikin skema + data demo

composer test        # pest
composer analyse     # phpstan level 8
composer fix         # pint

# Frontend (SPA Vue di resources/js) — buka app di http://localhost:8000
php artisan serve    # shell + API (port 8000)
npm install && npm run dev   # Vite HMR (di terminal lain)
npm run test:js      # Vitest
npm run build        # bundel produksi → public/build
```

Detail tiap langkah, troubleshooting, dan contoh output: **[`docs/SETUP-GUIDE.md`](docs/SETUP-GUIDE.md)**
(frontend: §9a, daftar endpoint: §10d).

## Akun demo
Password semua `password`. Mis. `admin@tas.test`, `planner@tas.test`,
`gate@tas.test`, `dispatcher@majulog.test`, `budi@majulog.test`.
Selengkapnya: [`docs/DUMMY-DATA.md`](docs/DUMMY-DATA.md).

---

<sub>Dibangun di atas [Laravel](https://laravel.com) (MIT). Lihat dokumentasi Laravel
untuk referensi framework.</sub>
