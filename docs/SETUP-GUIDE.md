# docs/SETUP-GUIDE.md — Menjalankan, Menguji & Mengoperasikan TAS (dev)

> Panduan **operasional** tanpa AI: dari `git clone` sampai app jalan, test hijau, dan paham
> **kenapa** lingkungannya berbentuk begini.
>
> Posisi dokumen: *how-to*. Aturan arsitektur ada di `CLAUDE.md`, domain di `BUSINESS-FLOW.md`,
> penjelasan kode di `CODE-WALKTHROUGH.md` / `FRONTEND.md`, status sesi di `HANDOVER.md`.
>
> **Jalur utama = Docker** ([ADR-0007](adr/0007-docker-redis-mysql-migration.md)). PHP native Windows
> hanya dipakai untuk test/IDE/PHPStan/e2e (§4). Semua perintah & angka di dokumen ini dijalankan
> ulang pada 2026-09-14, termasuk dari **clone kosong**.
>
> **Panduan era pra-Docker** (build foundation dari nol di PHP native + SQLite: aktifkan ekstensi,
> `composer require` tiap paket, publish config, dst.) **sudah usang dan dihapus dari sini**. Masih
> utuh di git: `git show pre-docker:docs/SETUP-GUIDE.md`.

---

## Daftar Isi
0. [Prasyarat](#0-prasyarat)
1. [Mulai dari clone (±1 menit)](#1-mulai-dari-clone-1-menit)
2. [Peta service, port & URL](#2-peta-service-port--url)
3. [Sehari-hari: perintah & "setelah mengubah X"](#3-sehari-hari-perintah--setelah-mengubah-x)
4. [Jalur native Windows (test, IDE, PHPStan, e2e)](#4-jalur-native-windows-test-ide-phpstan-e2e)
5. [Data demo & reset database](#5-data-demo--reset-database)
6. [Isolasi test — dua pengaman yang wajib ada](#6-isolasi-test--dua-pengaman-yang-wajib-ada)
7. [Environment (`.env`): native vs Docker](#7-environment-env-native-vs-docker)
8. [Tooling kualitas (konfigurasi)](#8-tooling-kualitas-konfigurasi)
9. [Frontend dev server & gerbang kualitas](#9-frontend-dev-server--gerbang-kualitas) · [9a dev server](#9a-dev-server-vite) · [9b gerbang kualitas](#9b-gerbang-kualitas-sebelum-commit)
10. [Menulis & menjalankan test + referensi API](#10-menulis--menjalankan-test--referensi-api)
11. [Troubleshooting](#11-troubleshooting)
12. [Checklist verifikasi](#12-checklist-verifikasi)
13. [Peta file](#13-peta-file)
14. [CI (GitHub Actions)](#14-ci-github-actions) · [14a untuk apa](#14a-untuk-apa-ci-ini-ada) · [14b apa yang dijalankan](#14b-apa-yang-dijalankan) · [14c cara memakai](#14c-cara-memakainya-sehari-hari)
15. [E2E (Playwright) — lokal-only](#15-e2e-playwright--lokal-only)
16. [Docker: MySQL & database test](#16-docker-mysql--database-test)
17. [Docker: app di container — kenapa bentuknya begini](#17-docker-app-di-container--kenapa-bentuknya-begini)
18. [Docker: Redis, Horizon, scheduler & Reverb](#18-docker-redis-horizon-scheduler--reverb)

---

## 0. Prasyarat

| Tool | Wajib? | Untuk apa | Cek |
|------|--------|-----------|-----|
| Docker Desktop (Compose v2) | **wajib** | seluruh backend: MySQL, Redis, app, Horizon, scheduler, Reverb | `docker compose version` |
| Git | **wajib** | — | `git --version` |
| Node.js 22 + npm | **wajib** | Vite (SPA), Vitest, vue-tsc, build — frontend tetap native | `node -v` |
| PHP 8.3 + Composer (Windows) | opsional | autocomplete IDE, PHPStan di host, `composer test` native, e2e — lihat §4 | `php -v` |

Teruji di: Windows 11 + Docker Desktop (Compose v5.5), Node 22, PHP 8.3.31 (laragon).

---

## 1. Mulai dari clone (±1 menit)

Urutan di bawah dijalankan pada **clone kosong** (tanpa `vendor/`, `node_modules/`, `.env`) —
setiap langkah terverifikasi, angka durasi dari run itu.

```bash
git clone <repo> truck-appointment-system && cd truck-appointment-system
cp .env.example .env                                           # (PowerShell: Copy-Item .env.example .env)

docker compose run --rm --no-deps app composer install        # ±25 dtk — vendor/ milik container (§17)
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d --wait                                    # ±20 dtk — 6 service, lihat §2
docker compose exec app php artisan migrate:fresh --seed       # ±7 dtk — skema + data demo di MySQL `tas`

npm install
npm run dev                                                    # terminal lain; Vite HMR (native)
```

Buka **`http://localhost:8000`**, login `dispatcher@majulog.test` / `password`.

Pastikan semuanya sehat:
```bash
docker compose exec app composer test          # 265 passed (±30 dtk)
docker compose exec app php artisan horizon:status   # Horizon is running
```

> **Kenapa `composer install` lewat container, bukan di Windows?** `vendor/` yang dipakai app
> ada di **volume Linux** container (alasan & angka: §17). `composer install` di Windows mengisi
> `vendor/` yang **lain** — perlu hanya untuk jalur native (§4).
>
> **Kenapa kunci Reverb di `.env.example` sudah terisi?** Nilai **khusus dev lokal**. Dengan kunci
> kosong, setiap job broadcast gagal di Horizon (`Pusher error: Not found`) sementara booking tetap
> 201 — realtime mati tanpa pesan. Detail: §7.

---

## 2. Peta service, port & URL

| Service | Isi | Dari Windows | Dari container lain |
|---------|-----|--------------|---------------------|
| `mysql` | MySQL 8.4 — DB `tas` (dev) + `tas_testing*` (test), §16 | `127.0.0.1:3307` | `mysql:3306` |
| `redis` | Redis 7 — cache/session/queue (`appendonly`) | `127.0.0.1:6379` | `redis:6379` |
| `app` | `php artisan serve --no-reload` — API + shell SPA | **`http://localhost:8000`** | `app:8000` |
| `horizon` | worker antrian (job, reminder, broadcast) | `http://localhost:8000/horizon` | — |
| `scheduler` | `schedule:work` — `NoShowSweepJob` tiap 5 mnt, prune token harian | — | — |
| `reverb` | WebSocket realtime | `ws://localhost:8080` (browser) | `reverb:8080` (publish PHP) |
| *(native)* Vite | HMR aset SPA | `http://localhost:5173` — **bukan** entry point | — |

Port kiri bisa digeser lewat `.env` (`DB_PORT_HOST`, `REDIS_PORT_HOST`, `APP_PORT`,
`REVERB_PORT_HOST`). **3307, bukan 3306**: laragon di mesin dev memegang 3306.

> **Kenapa Reverb punya dua alamat:** di dalam container, `localhost` = container itu sendiri.
> PHP mem-publish ke `reverb:8080` (env compose), browser tersambung ke `localhost:8080`
> (`VITE_REVERB_*` di `.env`). Nilai `localhost` untuk PHP pernah membuat realtime putus di
> container tanpa error — §18.

---

## 3. Sehari-hari: perintah & "setelah mengubah X"

```bash
docker compose up -d                      # nyalakan (container juga hidup lagi sendiri setelah reboot)
docker compose ps                         # mysql & redis harus "healthy"
docker compose logs -f app horizon scheduler reverb
docker compose exec app php artisan ...   # artisan apa pun, di dalam container
docker compose exec app bash              # masuk ke container
docker compose down                       # matikan (data TETAP di volume)
```

**Setelah mengubah X, lakukan Y:**

| Yang berubah | Yang harus dilakukan | Kenapa |
|---|---|---|
| Kode PHP (`app/`, `routes/`, `config/`…) | **tidak ada** — terlihat ≤2 dtk | OPcache `validate_timestamps=1` (§17) |
| Kode job/listener yang dijalankan Horizon | `docker compose exec app php artisan horizon:terminate` | worker proses panjang memegang kode lama di memori |
| `composer.lock` (`composer require`, `git pull`) | `docker compose run --rm --no-deps app composer install` **dan** (bila memakai §4) `composer install` di Windows | ada **dua** `vendor/` (§17); lupa yang container → error keras `Class not found` |
| `.env` | `docker compose up -d --force-recreate app horizon scheduler reverb` | server web jalan dengan `--no-reload` (§17) |
| `docker/php/Dockerfile` | `docker compose build && docker compose up -d` | image dibangun ulang |
| `docker/mysql/init/` | `docker compose down -v && docker compose up -d` (**HAPUS DATA**) | skrip init hanya jalan saat volume kosong |
| `package.json` / `package-lock.json` | `npm install` | frontend native |

> **`composer dev` = NATIVE ONLY.** Ia menjalankan `php artisan serve` + `queue:listen` di Windows
> dengan SQLite. **Jangan** dinyalakan bersamaan container: port 8000 dipegang Docker, `serve`
> diam-diam pindah ke 8001 dengan database lain, dan antriannya tak pernah dilihat Horizon. Sama
> untuk `php artisan reverb:start` native (port 8080 bentrok).

---

## 4. Jalur native Windows (test, IDE, PHPStan, e2e)

Opsional. Butuh PHP 8.3 dengan `pdo_mysql` (+ `pdo_sqlite`/`sqlite3` untuk e2e), dan **stack Docker
tetap menyala** karena test memakai MySQL container.

```bash
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
composer test        # MySQL lewat 127.0.0.1:3307 → 262 passed + 3 skipped (±36 dtk)
composer analyse     # PHPStan lvl 8
composer fix         # Pint
```

- **Kenapa flag platform:** Horizon men-*declare* `ext-pcntl`/`ext-posix`, yang tak ada di PHP
  Windows. Flag hanya membuat Composer mau menulis paket; Horizon sendiri jalan di container.
- **Kenapa 3 test dilewati:** `RedisUniqueJobTest` butuh ekstensi `redis`, yang tak ada di PHP
  Windows. Di container dan CI ketiganya jalan — itulah sebabnya CI wajib punya service `redis`.
- Test di container kini **lebih cepat** dari native (±30 vs ±36 dtk, §17) — native dipakai
  terutama untuk IDE/PHPStan dan e2e (§15).

---

## 5. Data demo & reset database

```bash
docker compose exec app php artisan migrate:fresh --seed   # reset total DB dev `tas`
```

`RolePermissionSeeder` (5 role + permission, `BUSINESS-FLOW.md §1`) → `DemoSeeder` (7 akun, password
semua `password`, 1 terminal + 2 gate, 2 perusahaan + armada + sopir, appointment di semua status).
Detail akun: `docs/DUMMY-DATA.md`.

Dua hal yang sering disangka bug:
- **Data demo relatif ke tanggal saat seed** (`Carbon::today()`). DB yang di-seed beberapa hari lalu
  tak punya window hari ini → seed ulang dulu sebelum curiga ke kode.
- **Scheduler benar-benar jalan.** Appointment demo `BOOKED`/`CONFIRMED` yang window-nya lewat
  grace (30 mnt) akan ditandai `NO_SHOW` oleh `NoShowSweepJob` dalam ≤5 menit. Itu perilaku
  domain yang benar, bukan data rusak.

Test **tak pernah** menyentuh `tas` — lihat §6 & §16.

---

## 6. Isolasi test — dua pengaman yang wajib ada

Test memakai `tas_testing` + `array`/`sync`/`null`, bukan DB dev + Redis + Reverb. Di dalam
container itu **tidak otomatis**, karena service compose menyetel `DB_DATABASE=tas`,
`CACHE_STORE=redis`, `BROADCAST_CONNECTION=reverb` sebagai env var sungguhan. Dua pengaman:

1. **`force="true"`** pada baris isolasi di `phpunit.xml` (termasuk `DB_DATABASE`).
2. **`tests/TestCase.php::createApplication()` menyalin `$_ENV` → `$_SERVER`.** `force` hanya
   menulis `$_ENV` + `putenv()`, sedangkan `env()` Laravel membaca `$_SERVER` **lebih dulu**.

**Keduanya wajib.** Tanpa salah satunya suite di container tetap **hijau** sambil memakai DB dev —
dan satu `pest` non-paralel mengosongkannya (terbukti 2026-09-14: users 7 → 0). Aturannya:
*environment boleh menentukan DI MANA (host/port DB), tidak boleh menentukan APAKAH test terisolasi.*
`DB_HOST`/`DB_PORT` sengaja tanpa `force` karena berbeda antara Windows (`127.0.0.1:3307`),
container (`mysql:3306`), dan CI (`127.0.0.1:3306`). Redis test memakai DB 15 (`REDIS_DB`), karena
`RedisUniqueJobTest` mem-*flush*-nya.

---

## 7. Environment (`.env`): native vs Docker

Satu berkas `.env` dibaca kedua jalur, dengan pembagian tegas:

| Kunci | Dipakai oleh | Di Docker |
|-------|--------------|-----------|
| `DB_*`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `BROADCAST_CONNECTION`, `REDIS_*` | jalur native (`composer dev`, SQLite) | **ditimpa** env var `docker-compose.yml` — jangan diubah untuk Docker |
| `APP_KEY`, `APP_*`, `MAIL_*`, `SANCTUM_EXPIRATION`, `TAS_*` | keduanya | dipakai apa adanya |
| `REVERB_APP_ID/KEY/SECRET` | keduanya | dipakai apa adanya |
| `REVERB_HOST/PORT/SCHEME` → `VITE_REVERB_*` | **browser** (dibaca Vite saat dev/build) | PHP memakai `reverb:8080` dari compose |

- **Kunci Reverb dev terisi di `.env.example`** (`100001` / `tas-local-dev-key` / `tas-local-dev-secret`).
  `php artisan reverb:install` **bukan** penggantinya: ia tak mengisi kunci yang sudah ada walau
  kosong, dan ikut mengubah config proyek. **Produksi wajib nilai acak.**
- **`DB_ROOT_PASSWORD` (default `secret`)** dipakai compose; `phpunit.xml` memakai nilai yang sama.
- **Ubah `.env` → recreate service** (§3). Tanpa itu nilai lama tetap dipakai.

---

## 8. Tooling kualitas (konfigurasi)

| Berkas | Isi yang penting |
|--------|------------------|
| `phpstan.neon` | Larastan, **level 8**, paths `app`/`database`/`routes` (tests tidak dianalisis) |
| `tests/Pest.php` | `Feature` → `RefreshDatabase`; `Concurrency` → `DatabaseTruncation` (§10e); `Unit` tanpa DB |
| `phpunit.xml` | 3 testsuite (`Unit`, `Feature`, `Concurrency`); MySQL `tas_testing`; isolasi di-`force` (§6) |
| `tests/TestCase.php` | sinkron `$_ENV` → `$_SERVER` (§6) |
| `composer.json` scripts | `test` (pest --parallel) · `analyse` · `fix` (pint) · `lint` (pint --test) · `dev` (native only) |
| `app/Providers/AppServiceProvider.php` | `preventLazyLoading` / `preventAccessingMissingAttributes` / `preventSilentlyDiscardingAttributes` (non-prod), bindings repo, rate limiter |

---

## 9. Frontend dev server & gerbang kualitas

### 9a. Dev server (Vite)

Buka app di **`http://localhost:8000`** (Laravel di container) — **bukan** `localhost:5173`.
Port 5173 hanya server aset Vite; container menyajikan HTML yang menunjuk ke sana lewat berkas
`public/hot` (terverifikasi: aset dari `[::1]:5173` termuat 200 dari halaman container).

| Situasi | Perintah |
|---------|----------|
| Coding frontend aktif | `npm run dev` (HMR — edit `.vue` langsung terlihat, state Pinia tetap) |
| Hanya memakai app | `npm run build` sekali; container menyajikan `public/build/` |

> **Jebakan `public/hot` basi.** Kalau proses Vite mati tidak bersih (mis. terminal ditutup paksa),
> `public/hot` bisa **tertinggal** — atau Vite tetap hidup sebagai proses yatim. Gejala: halaman
> kosong / aset gagal dimuat. Hapus `public/hot` dan pastikan port 5173 kosong. Matikan Vite
> dengan **Ctrl+C**.

Penjelasan kode SPA: `docs/FRONTEND.md`.

### 9b. Gerbang kualitas (sebelum commit)

Urutan **fix → analyse → test** (Definition of Done `CLAUDE.md`):

```bash
docker compose exec app composer fix       # Pint
docker compose exec app composer analyse   # PHPStan lvl 8
docker compose exec app composer test      # Pest (265)
npm run test:js && npm run type-check && npm run build
```

Ketiga perintah `composer` bisa juga dijalankan native (§4). Angka test terkini selalu ada di
`HANDOVER.md` §Status — angka di dokumen ini akan basi.

---

## 10. Menulis & menjalankan test + referensi API

### 10a. Test unit murni (tanpa DB)
```php
it('follows the documented transition graph', function (): void {
    expect(AppointmentStatus::BOOKED->canTransitionTo(AppointmentStatus::CONFIRMED))->toBeTrue();
});
```

### 10b. Test feature (DB `tas_testing`, di-reset tiap test)
`tests/Feature/FoundationSeedTest.php`:
```php
use function Pest\Laravel\seed;

it('wires the RBAC matrix', function (): void {
    seed([RolePermissionSeeder::class, DemoSeeder::class]);
    $planner = User::query()->where('email', 'planner@tas.test')->firstOrFail();
    expect($planner->can('slot.manage'))->toBeTrue()
        ->and($planner->can('appointment.write'))->toBeFalse();
});
```

### 10c. Menjalankan sebagian
```bash
docker compose exec app ./vendor/bin/pest tests/Unit/AppointmentStatusTest.php   # satu file
docker compose exec app ./vendor/bin/pest --filter="RBAC"                        # cocokkan nama
docker compose exec app ./vendor/bin/pest --testsuite=Concurrency                # satu testsuite
```

> **Pola TDD wajib (per Action)** — `CLAUDE.md`: test dulu (happy path + edge: kuota penuh → 409,
> double-submit + `Idempotency-Key`, bentrok `version`) → implement sampai hijau →
> `fix && analyse && test` → commit kecil. **Lalu rusak sengaja hal yang dijaga test itu dan
> pastikan ia MERAH** — "hijau" saja tak membuktikan apa-apa (§10e).

### 10d. Referensi endpoint & uji manual (curl)

| Method | Endpoint | Auth | Permission | Guna |
|--------|----------|------|------------|------|
| `POST` | `/api/v1/login` | publik | — | tukar email+password → token |
| `POST` | `/api/v1/logout` | token | — | cabut token saat ini |
| `GET`  | `/api/v1/me` | token | — | profil + role + permission |
| `GET`  | `/api/v1/gates?terminal={id}` | token | `slot.read` | daftar gate (dropdown); `terminal` opsional |
| `GET`  | `/api/v1/me/fleet` | token | `fleet.manage` | truk & sopir milik company transporter (form booking) — truk **ACTIVE saja** |
| `GET`  | `/api/v1/me/trucks` | token | `fleet.manage` | armada truk company sendiri, **semua status** (halaman kelola) |
| `POST` | `/api/v1/me/trucks` | token | `fleet.manage` | tambah truk (body: `plate_no`, `status`); plat unik **per company** → 422 |
| `PATCH` | `/api/v1/me/trucks/{truck}` | token | `fleet.manage` | ubah plat/status; truk company lain → 403 |
| `DELETE` | `/api/v1/me/trucks/{truck}` | token | `fleet.manage` | hapus truk (204); punya riwayat appointment → 409 `entity_in_use` (pakai status INACTIVE) |
| `GET`  | `/api/v1/slots/availability?gate={id}&date=YYYY-MM-DD` | token | `slot.read` | sisa kuota slot |
| `POST` | `/api/v1/appointments` | token | `appointment.write` | booking (kirim `Idempotency-Key`); truk INACTIVE → 422 `truck_inactive`; `driver_id` bukan user ber-role `driver` → 422 `driver_invalid_role` |
| `GET`  | `/api/v1/appointments/{id}` | token | Policy `view` | detail appointment (scope per role) |
| `GET`  | `/api/v1/appointments/qr/{token}` | token | Policy `view` (dicek manual di controller) | verifikasi QR gate-in — `{token}` = `qr_token` ter-sign dari `AppointmentResource`, bukan id polos; 403 `invalid_qr_token` bila diutak-atik/kedaluwarsa |
| `GET`  | `/api/v1/appointments/qr/{token}/image` | token | sama seperti di atas | PNG QR **on-demand** (cetak/email) — di-generate di memori per request, TIDAK PERNAH disimpan ke storage |
| `GET`  | `/api/v1/appointments/{id}/audit` | token | `audit.read` **+** Policy `view` | jejak audit appointment (urut kronologis). Dua lapis: gate-officer & driver LOLOS Policy tapi ditolak 403 karena tak punya `audit.read`. `causer: null` = tindakan sistem |
| `POST` | `/api/v1/appointments/{id}/reschedule` | token | Policy `update` | pindah window (body: `slot_window_id`, `version`) |
| `POST` | `/api/v1/appointments/{id}/cancel` | token | Policy `cancel` | batalkan (kembalikan kuota); body opsional `version` → optimistic lock (409 `version_conflict` bila usang) |
| `POST` | `/api/v1/appointments/{id}/gate-in` | token | Policy `process` | gate-in (CONFIRMED→IN_PROGRESS), idempoten; di luar toleransi jendela → 409 `gate_in_too_early` / `gate_in_too_late` (`config/tas.php` → `gate_in`) |
| `POST` | `/api/v1/appointments/{id}/gate-out` | token | Policy `process` | gate-out (IN_PROGRESS→COMPLETED), idempoten |
| `POST` | `/api/v1/appointments/{id}/no-show` | token | Policy `process` | tandai no-show manual (BOOKED/CONFIRMED→NO_SHOW, kembalikan kuota) — pelengkap `NoShowSweepJob` otomatis |
| `POST` | `/api/v1/slots` | token | `slot.manage` | planner buka window (body: `gate`, `date`, `start_time`, `end_time`, `capacity`) |
| `POST` | `/api/v1/slots/{slotWindow}/close` | token | `slot.manage` | planner tutup window (status CLOSED, idempoten) |
| `GET`  | `/api/v1/me/appointments?status={STATUS}` | token | `appointment.read` + punya company | daftar booking transporter (filter status opsional) |
| `GET`  | `/api/v1/me/appointments/today` | token | `appointment.read.self` | jadwal hari-H sopir |
| `GET`  | `/api/v1/gate/queue?date=YYYY-MM-DD` | token | `gate.process` + punya terminal | antrian gate-officer (CONFIRMED/IN_PROGRESS di terminalnya, default hari ini) |
| `GET`  | `/api/v1/reports/utilization?gate={id}&date=YYYY-MM-DD` | token | planner/admin | utilisasi gate (kuota vs terpakai vs no-show) |
| `GET`  | `/api/v1/me/reports/utilization?gate={id}&date=YYYY-MM-DD` | token | `report.read` + punya company | utilisasi company sendiri per window (angka company lain tak bocor) |
| `GET`  | `/api/v1/reports/gate-history?gate={id}&date=YYYY-MM-DD` | token | `hasAnyRole(admin,planner)` | riwayat gate-in/out per gate+tanggal, termasuk yang sudah COMPLETED (beda dari antrian `/gate/queue`) |

**Admin — master data CRUD** (semua di bawah `/api/v1/admin`, butuh permission manage terkait;
hapus → **409 `entity_in_use`** bila masih ada dependen):

| Method | Endpoint | Permission | Guna |
|--------|----------|------------|------|
| `GET` · `POST` | `/admin/terminals` · `/admin/terminals/{id}` (GET/PUT/DELETE) | `terminal.manage` | CRUD terminal (`code`, `name`); hapus ditolak bila punya gate |
| `GET` · `POST` | `/admin/gates` · `/admin/gates/{id}` (GET/PUT/DELETE) | `gate.manage` | CRUD gate (`terminal_id`, `code`, `name`); hapus ditolak bila punya slot window |
| `GET` · `POST` | `/admin/companies` · `/admin/companies/{id}` (GET/PUT/DELETE) | `company.manage` | CRUD perusahaan angkutan; hapus ditolak bila punya user/appointment |
| `GET` · `POST` | `/admin/users` · `/admin/users/{id}` (GET/PUT/DELETE) | `user.manage` | CRUD user (`name`, `email`, `role`, `password?`, `terminal_id?`, `company_id?`); password di-hash saat dibuat & hanya diubah bila diisi; tak bisa hapus diri sendiri (422) |
| `GET` | `/admin/roles` | `role.manage` | list 5 role + permission masing-masing + `meta.all_permissions` (universe checkbox FE) |
| `PUT` | `/admin/roles/{name}/permissions` | `role.manage` | ganti (sync, bukan tambah) seluruh permission 1 role; `{name}` = nama role bukan id; role `admin` ditolak 422 `role_immutable` — **tak bisa bikin/hapus role baru**, lihat `CODE-WALKTHROUGH §V.6` |

**Uji manual** (terverifikasi 2026-09-14 pada data seed; stack menyala + DB sudah di-seed, §5):

**0) Login** (password `password`):
```bash
curl -s -X POST http://localhost:8000/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"dispatcher@majulog.test","password":"password"}'
# → 201 {"token":"3|abcdef...","token_type":"Bearer","user":{...}}
```

**1) Ambil id truk & sopir milik company-mu, lalu window yang masih buka** (gate 2 = GATE-B; window
**besok** dijamin belum berakhir, jam berapa pun DB di-seed):
```bash
curl -s http://localhost:8000/api/v1/me/fleet -H "Authorization: Bearer TOKEN" -H "Accept: application/json"
# → {"data":{"trucks":[{"id":1,...}],"drivers":[{"id":5,"name":"Budi Santoso"}]}}
curl -s "http://localhost:8000/api/v1/slots/availability?gate=2&date=YYYY-MM-DD" \
  -H "Authorization: Bearer TOKEN" -H "Accept: application/json"
# → {"data":[{"id":25,"start_time":"06:00:00","remaining":5,"status":"OPEN","ended":false},...]}
```

**2) Booking** (id dari langkah 1 — **jangan** menebak: sopir company lain → 422):
```bash
curl -s -X POST http://localhost:8000/api/v1/appointments \
  -H "Authorization: Bearer TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" -H "Idempotency-Key: demo-001" \
  -d '{"slot_window_id":25,"truck_id":1,"driver_id":5,"move_type":"DELIVERY","container_no":"MAUU1234567","iso_type":"22G1","size":20}'
# → 201 {"data":{"status":"CONFIRMED","booking_code":"TAS-....",...}}
```

**3) Uji idempotency** — ulangi (2) **persis** (key sama): respons 201 yang sama diputar ulang dengan
header `Idempotent-Replayed: true`, dan **tidak** ada appointment baru:
`docker compose exec app php artisan tinker --execute="echo App\Models\Appointment::count();"`.

**Respons error yang diharapkan:**
- Slot penuh/tutup/berakhir → `409 {"error":"slot_unavailable"}`
- Kontainer dobel di window sama → `409 {"error":"duplicate_booking"}`
- Truk/sopir company lain → `422` (validation `truck_id`/`driver_id`)
- Tanpa token → `401` · role tanpa `appointment.write` → `403`
- Terlalu sering → `429 Too Many Requests` (+ `Retry-After`)

> **Selalu kirim `Accept: application/json`.** Tanpa header itu, request tanpa token ke endpoint
> ber-auth menghasilkan **500** (`Route [login] not defined`), bukan 401 — bug P3 yang tercatat
> terbuka di `HANDOVER.md` §Temuan D2′. SPA selalu mengirimnya.
>
> **Rate limit** (`AppServiceProvider`): `login` 5/mnt (email+ip), `api` 60/mnt, `booking` 10/mnt per
> user. Nilai via env `TAS_RL_LOGIN` / `TAS_RL_API` / `TAS_RL_BOOKING` (`config/tas.php`).

### 10e. Test konkurensi (`tests/Concurrency/`)

Testsuite **ketiga**, memakai `DatabaseTruncation` (bukan `RefreshDatabase`): RefreshDatabase
membungkus tiap test dalam transaksi yang di-rollback, sehingga data yang dibuat test **tak pernah
terlihat** oleh proses PHP lain — dan test ini justru menyalakan 4 proses PHP sungguhan
(`symfony/process`) untuk memperebutkan satu slot terakhir. Butuh **MySQL**: SQLite tak menerbitkan
`SELECT … FOR UPDATE` sama sekali.

Isinya dua test, **keduanya wajib ada**:
1. **Realistis** — 4 proses menyerbu bersamaan, tepat satu menang.
2. **Deterministik** — memastikan `select … for update` benar-benar diterbitkan.

Kenapa tak cukup yang realistis: saat lock dicabut, test #1 **tetap hijau** — `increment('booked_count')`
tetap mengunci baris di UPDATE-nya dan `DB::transaction(attempts: 3)` mengulang closure yang kalah.
Test #2-lah gerbang yang sesungguhnya. **Jangan hapus #2.**

---

## 11. Troubleshooting

### Docker & lingkungan

| Gejala | Sebab paling mungkin | Tindakan |
|--------|----------------------|----------|
| `SQLSTATE[HY000] [2002]` connection refused | stack mati / belum `healthy` | `docker compose up -d --wait` |
| `port is already allocated` saat `up` | port dipakai proses lain (laragon, Vite/`serve`/`reverb:start` native) | matikan yang bentrok, atau geser `*_PORT_HOST` di `.env` |
| `Class "..." not found` di container setelah `git pull` | `composer.lock` berubah, `vendor/` container belum di-install | `docker compose run --rm --no-deps app composer install` (§3) |
| App lambat, tiap request beberapa detik | `vendor/` volume hilang / kembali ke bind mount | cek `docker compose config` memuat volume `vendor`; lalu `composer install` di container |
| Edit `.env` tak berefek | server web jalan `--no-reload` | recreate service (§3) |
| Halaman kosong / aset SPA gagal dimuat | `public/hot` basi atau Vite yatim | §9a |
| Realtime diam, `failed_jobs` berisi `Pusher error: Not found` | `REVERB_APP_*` kosong di `.env` | isi dari `.env.example`, recreate service (§7) |
| Realtime diam tanpa failed job | Vite jalan dengan `.env` lama (`VITE_REVERB_*`), atau `reverb` mati | restart `npm run dev`; `docker compose ps reverb` |
| Login lewat `:8000` sukses tapi data "hilang"/beda | yang terbuka adalah `composer dev` native (8001, SQLite) | lihat peringatan di §3 |
| `Unknown database 'tas_testing'` | volume MySQL dibuat sebelum skrip init | `docker compose down -v && docker compose up -d` (**hapus data**) |
| `Access denied for user 'root'` | password tak cocok | samakan `DB_ROOT_PASSWORD` (default `secret`) |
| Test konkurensi: 0 pemenang | anak proses gagal boot/konek, bukan bug lock | baca "Hasil mentah" di pesan gagalnya |
| `storage/logs/laravel.log` membingungkan | log container & native bercampur (bind mount) | tandai waktu, atau `docker compose logs` |

### PHP / PHPStan (masih berlaku, dari pembangunan foundation)

- **`Strict comparison ... will always evaluate to false` (enum)** — Larastan menebak kolom sebagai
  `string`. Tambah docblock `@property AppointmentStatus $status` di model.
- **`method.childReturnType` di factory `definition()`** — docblock `@return array<string,mixed>`
  lebih lebar dari tipe induk. **Hapus** docblock itu.
- **`offsetAccess.notFound` "Offset '10' does not exist"** — PHP menormalkan kunci string angka jadi
  int. Pakai kunci integer konsisten (lihat `DemoSeeder::windows()`).
- **`argument.type` pada `Model::create(array_merge(...))`** — bangun model lalu
  `->forceFill($attrs)->save()`.
- **Fatal "Declaration of ...::data() must be compatible with Request::data()"** — FormRequest sudah
  punya `data()`/`date()`. Ganti nama helper (`toData()`, `requestedDate()`).
- **`Cache::tags()` "does not support tagging"** — hanya store `database`/`file` (jalur native).
  Proyek sengaja memakai key eksplisit + `Cache::forget` (`CLAUDE.md` §Cache).

---

## 12. Checklist verifikasi

Setelah setup (§1) atau perubahan infrastruktur:

- [ ] `docker compose ps` — 6 service `Up`, `mysql` & `redis` `healthy`
- [ ] `docker compose exec app php artisan horizon:status` → running
- [ ] `docker compose exec scheduler php artisan schedule:list` memuat `NoShowSweepJob`
- [ ] `http://localhost:8000` tampil, login akun demo berhasil
- [ ] `docker compose exec app composer test` → hijau; **DB dev `tas` masih berisi** setelahnya
- [ ] `docker compose exec app composer analyse` → No errors · `composer lint` → bersih
- [ ] `npm run test:js && npm run type-check && npm run build` → hijau
- [ ] Booking di satu browser → sisa kuota berubah tanpa refresh di browser lain (realtime)

---

## 13. Peta file

```
app/
  Enums/            AppointmentStatus (state machine), MoveType, SlotWindowStatus,
                    GateTransactionType, TruckStatus
  Models/           User, Terminal, Gate, TransportCompany, Truck, SlotWindow,
                    Appointment, Container, GateTransaction
  Actions/          Book/Reschedule/Cancel/GateIn/GateOut/MarkNoShow/Open|CloseSlotWindow
                    + Admin/ (CRUD master data + role permission) + Fleet/ (CRUD truk)
  Contracts/        interface repository (di-bind di AppServiceProvider)
  Repositories/     impl Eloquent tiap interface
  Http/Controllers/Api/V1/  controller invokable (+ Admin/, Fleet/)
  Jobs/             NoShowSweepJob, AppointmentReminderJob, ProcessGateEventJob
  Events/ Listeners/  event domain + broadcast (SlotAvailabilityChanged, GateQueueUpdated)
  Exceptions/       409/422/403 domain (SlotUnavailable, Duplicate*, OptimisticLock, EntityInUse, ...)
database/           migrations · factories · seeders (RolePermissionSeeder, DemoSeeder)
routes/             api.php · channels.php · console.php (jadwal scheduler)
resources/js/       SPA Vue (docs/FRONTEND.md)
tests/
  Pest.php          trait DB per testsuite
  TestCase.php      sinkron $_ENV → $_SERVER (§6)
  Unit/ Feature/    per area: Auth · Booking · Appointments · Gate · Slots · Jobs · Reports ·
                    Reference · Admin · Fleet · Realtime · Hardening
  Concurrency/      ConcurrentBookingTest + bin/book-once.php (§10e)
  js/               Vitest · e2e/  Playwright (§15)
docker/
  php/Dockerfile    PHP 8.3 CLI + ekstensi + OPcache dev + CMD serve --no-reload (§17)
  mysql/init/       membuat tas_testing (§16)
docker-compose.yml  6 service, env PHP bersama (anchor), volume data + vendor
phpunit.xml         MySQL tas_testing + isolasi di-force (§6)
.github/workflows/ci.yml   §14
```

---

## 14. CI (GitHub Actions)

### 14a. Untuk apa CI ini ada

Tiap `git push` ke `main` dan tiap PR, seluruh gerbang kualitas dijalankan ulang di mesin bersih —
supaya "hijau" berhenti bergantung pada disiplin & isi folder lokal seseorang. **Bukan** pengganti
test lokal: loop TDD tetap di laptop; CI jaring pengaman terakhir.

### 14b. Apa yang dijalankan

File: `.github/workflows/ci.yml`. Dua job **paralel**:

| Job | Langkah | Perintah |
|-----|---------|----------|
| **backend** | format → analisis → test | `composer lint` · `composer analyse` · `composer test` |
| **frontend** | test → tipe → bundel | `npm run test:js` · `npm run type-check` · `npm run build` |

Perintahnya **sama persis dengan lokal** — kalau CI memakai rangkaiannya sendiri, "hijau di CI" dan
"hijau di laptop" berhenti berarti hal yang sama. Konsekuensinya: **gerbang baru di lokal wajib
ikut ditambahkan ke workflow**, kalau tidak ia tak berlaku saat push.

Keputusan di dalam workflow:
* **Service container `mysql:8.4` + `redis:7-alpine`.** Test butuh MySQL (row lock sungguhan, §10e)
  dan Redis (`RedisUniqueJobTest` — tanpa Redis ia dilewati, dan gerbang yang dilewati di CI sama
  saja dengan tak ada). Port DB di CI 3306 lewat `env:` step Pest (§6).
* **`if: ${{ !cancelled() }}`** pada langkah setelah yang pertama → satu push melaporkan **semua**
  gerbang yang rusak.
* **Tanpa `--ignore-platform-req`.** Runner Linux punya `pcntl`/`posix`, jadi CI memverifikasi
  dependensi apa adanya — satu-satunya hal yang tak bisa diuji `composer install` di Windows.
* **Versi dipatok** menyamai dev (PHP 8.3, Node 22). Naik di dev → naikkan juga di workflow.
* CI **tidak** memakai Docker Compose; ia menjalankan PHP langsung dengan service container.

### 14c. Cara memakainya sehari-hari

```bash
git push                      # CI langsung jalan
```

Hasil: repo di GitHub → tab **Actions** → run teratas. Repo ini **privat**, jadi REST API
GitHub tak terbaca tanpa token dan `gh` tak terpasang di mesin dev — status CI dicek lewat tab
Actions (atau `gh` / token bila kelak dipasang).

> **Satu run per PUSH, bukan per COMMIT.** Push 3 commit sekaligus → hanya **1** run, di commit
> terakhir. Isi commit di tengah tetap teruji lewat keturunannya, tapi tak ada bukti hijau yang
> menempel padanya sendiri — relevan bila kelak di-`revert`/`cherry-pick` sendirian.

**Kalau CI merah, jangan menebak lewat push berulang** — jalankan gerbang yang sama di lokal (§9b).

| Gejala di CI | Biasanya karena |
|--------------|-----------------|
| `backend` merah di **Pint** | lupa `composer fix` sebelum commit |
| `backend` merah di **Install dependensi** | `composer.lock` tak sinkron dengan `composer.json` |
| `backend` merah di **Pest**, hijau di container lokal | nilai env yang hanya ada di compose, atau test bergantung data dev — cek §6 |
| `frontend` merah di **Vitest** tapi hijau lokal | file test/aset baru belum di-commit |
| `frontend` merah di **Build** saja | error yang hanya muncul saat bundling |
| Semua job merah setelah ganti versi PHP/Node lokal | versi di workflow belum ikut dinaikkan |

> Alasan CI didahulukan dan e2e ditunda: `docs/adr/0005-ci-github-actions.md`.

---

## 15. E2E (Playwright) — lokal-only

**Jalur native**, terpisah total dari Docker: `php artisan serve --env=e2e --port=8010` di Windows
dengan SQLite `database/database.e2e.sqlite`. Tak bentrok dengan stack container (port & DB
berbeda). **Belum masuk CI** (biaya browser ~140 MB + waktu run) — keputusan sadar.

### 15a. Setup sekali

Butuh jalur native (§4: PHP + `composer install` di Windows).
```bash
cp .env.e2e.example .env.e2e
php artisan key:generate --env=e2e
npm run test:e2e:install      # unduh Chromium (~115 MB), sekali per mesin
```

### 15b. Jalankan

```bash
npm run test:e2e
```

`globalSetup` (`tests/e2e/global-setup.ts`) otomatis: bikin `database/database.e2e.sqlite` kalau
belum ada, `npm run build` (aset produksi — bukan Vite dev server), lalu
`migrate:fresh --seed --env=e2e`. **DB e2e terpisah dari semua DB lain** — `test:e2e` tak pernah
menyentuh data dev.

### 15c. Cakupan saat ini

5 spec (`tests/e2e/*.spec.ts`): `booking-happy-path`, `booking-errors` (kontainer duplikat → 409
tampil di UI), `gate-in-out` (planner buka window "sekarang ±" → booking → gate-in → gate-out),
`reschedule-cancel` (2 test). Belum ada spec untuk admin/utilisasi planner/no-show/QR. `data-testid`
dipakai: `login-*` (`LoginPage.vue`), `booking-*` (`BookingForm.vue`),
`slot-card`/`book-button`/`booking-success` (`SlotAvailabilityPage.vue`),
`booking-row`/`reschedule-*`/`cancel-*`/`confirm-cancel` (`MyBookingsPage.vue`, `RescheduleDialog.vue`),
`window-option` (`RescheduleDialog.vue`), `open-window` (`PlannerWindowsPage.vue`),
`queue-row`/`gate-in`/`gate-in-confirm`/`gate-out`/`gate-out-confirm` (`GateDashboardPage.vue` — 2 klik
sejak `ConfirmButton`). **Wajib `workers: 1`** — backend 1 proses `artisan serve` + SQLite file;
>1 worker terbukti membuat request lewat timeout (kontensi, bukan bug logic).

### 15d. Jebakan

- **`.env.e2e` tanpa `APP_KEY`** → `global-setup.ts` gagal cepat dengan pesan jelas.
- **Dua mekanisme env sekaligus:** flag `--env=e2e` (membuat `ServeCommand` mengawasi `.env.e2e`)
  **dan** env var `APP_ENV=e2e` di `playwright.config.ts` (dipakai proses PHP yang di-spawn). Keduanya
  perlu; satu saja tidak cukup.
- **`MAIL_MAILER=array`** di `.env.e2e`: dengan `QUEUE_CONNECTION=sync`, reminder dirender inline di
  request booking; `log` cukup lambat untuk melewati timeout Playwright.
- **Jam di helper wajib UTC** (`toISOString`), sama dengan `APP_TIMEZONE=UTC` backend — jam lokal
  (UTC+7) membuat window "sekarang ±" meleset 7 jam.

---

## 16. Docker: MySQL & database test

| Database | Isi | Dibuat oleh |
|----------|-----|-------------|
| `tas` | data dev (app, Horizon, scheduler) | `MYSQL_DATABASE` di compose |
| `tas_testing` | test tanpa `--parallel` | `docker/mysql/init/01-databases.sql` |
| `tas_testing_test_1..N` | satu DB **per proses** Pest `--parallel` | Laravel, otomatis saat runtime |

- **DB test terpisah dari DB dev** karena `RefreshDatabase` menjalankan `migrate:fresh`. Pemisahan ini
  **baru benar-benar berlaku di container sejak 2026-09-14** (§6) — sebelumnya test di container
  menembak `tas` dan meninggalkan schema `tas_test_N`. Kalau schema `tas_test_*` muncul lagi,
  pengaman §6 bocor.
- Test terhubung sebagai `root` karena pembuatan DB per-proses butuh hak `CREATE DATABASE`.
- **Skrip init hanya jalan saat volume kosong.** Mengubah `docker/mysql/init/` menuntut
  `docker compose down -v` (**hapus data**).
- Healthcheck `mysqladmin ping` membuat `up --wait` & CI menunggu MySQL **siap melayani**, bukan
  sekadar prosesnya hidup.

---

## 17. Docker: app di container — kenapa bentuknya begini

Setiap keputusan di bawah punya kegagalan nyata di belakangnya; jangan "dirapikan" tanpa
membaca alasannya.

### 17a. Kode lewat bind mount, `vendor/` di volume Linux

`.:/var/www/html` di-bind mount → edit di editor Windows langsung terlihat tanpa rebuild. Tapi
repo ada di NTFS, sehingga setiap `stat()` berkas dari container menyeberang ke Windows
(milidetik, bukan mikrodetik). Satu request Laravel memuat **~925 berkas, 816 di antaranya
`vendor/`**. Karena itu `vendor/` di-*mount* dari named volume `tas-vendor`, **hanya di dalam
container** — `vendor/` di Windows tetap utuh untuk IDE/PHPStan/test native.

Diukur dengan skrip yang sama (request API ber-token):

| Konfigurasi | Beruntun (median) | Setelah idle 15 dtk | Edit PHP terlihat |
|---|---|---|---|
| bind mount semua, OPcache CLI mati | 3,37 dtk (maks 8,4) | 3,38 dtk | langsung |
| + `opcache.enable_cli=1` saja | 4,04 dtk | 3,59 dtk | langsung — **tidak membantu** |
| + `validate_timestamps=0` (eksperimen) | 0,2 dtk | 0,2 dtk | **hanya setelah restart** — ditolak |
| **+ `vendor/` di volume (dipakai)** | **0,12 dtk** | **0,68 dtk** | ≤2 dtk |

Efek samping: `composer test` di container turun **68 → ±30 dtk**, kini lebih cepat dari native.
Harganya **dua `composer install`** saat `composer.lock` berubah (§3). Keputusan D2′ yang dulu
menolak volume vendor ("IDE & PHPStan jadi buta") keliru: host tetap punya `vendor/` sendiri.
Repo **tidak** dipindah ke filesystem WSL2 (lebih cepat lagi, tapi mengubah lokasi proyek & alur IDE).

### 17b. OPcache untuk CLI

`php artisan serve` adalah SAPI **CLI**, dan default `opcache.enable_cli=0`. `docker/php/Dockerfile`
menyalakannya dengan `validate_timestamps=1` (wajib untuk dev). **Khusus dev** — di produksi kode ada
di dalam image dan `validate_timestamps=0` justru benar.

### 17c. `php artisan serve --no-reload`

Tanpa `--no-reload`, selama `.env` ada, `ServeCommand` menjalankan server web sebagai proses anak dan
**membuang semua env var di luar whitelist** (demi auto-reload `.env`). Image tanpa `php.ini`
(`variables_order=EGPCS`) → seluruh env compose ikut terbuang → server web membaca `.env` jalur
native. **Akibat nyata sampai 2026-09-14:** app web di `:8000` melayani dari SQLite, job dari request
web masuk tabel yang tak dibaca Horizon, `/broadcasting/auth` membalas 200 kosong — sementara CLI
(artisan, Horizon, test) memakai MySQL/Redis. Harga flag: ubah `.env` butuh recreate service.

### 17d. Env disatukan lewat anchor

Env semua service PHP didefinisikan sekali (`x-php-env` di `docker-compose.yml`). Env yang berbeda
antar service PHP adalah bug **diam**: worker dengan `QUEUE_CONNECTION` beda dari pendorong job tak
pernah mengambilnya; publisher dengan `REVERB_HOST` beda tak pernah sampai. Catatan YAML: `<<:`
menggabung **dangkal** — service yang menulis ulang `environment:`/`depends_on:` mengganti seluruh map.

### 17e. Isi image

`php:8.3-cli` + `pdo_mysql` (DB) · `pcntl`/`posix` (Horizon — alasan utama containerisasi) · `gd`
(`endroid/qr-code`) · `zip` (Composer) · `redis` (pecl, klien `phpredis`) · OPcache dev · user non-root
UID 1000 (juga pemilik `vendor/` agar volume kosong tak jadi milik root).

### 17f. Produksi: jangan salin setup ini

| | Dev (sekarang) | Produksi |
|---|---|---|
| Kode + vendor | bind mount + volume vendor | `COPY` + `composer install --no-dev --optimize-autoloader` **di image** |
| Web server | `artisan serve` | nginx + php-fpm (atau FrankenPHP/Octane) |
| OPcache | `validate_timestamps=1` | `validate_timestamps=0` (deploy = image baru) |
| Cache Laravel | tidak | `config:cache` · `route:cache` · `event:cache` · `view:cache` |
| `.env` | berkas bind mount | env var / secret platform |
| Kunci Reverb | nilai dev di `.env.example` | acak, dari secret |

Volume di produksi hanya untuk data yang harus bertahan (MySQL, Redis), bukan kode. Belum ada
produksi di proyek ini — backlog di `HANDOVER.md`.

---

## 18. Docker: Redis, Horizon, scheduler & Reverb

### 18a. Redis & Horizon

`app`, `horizon`, `scheduler`, `reverb` memakai `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` =
`redis` (identik, §17d). Redis jalan dengan `appendonly yes`: antrian menyimpan **job** (termasuk
reminder H-2 jam yang tertunda), dan tanpa persistensi `docker compose restart` membuangnya.

```bash
docker compose exec app php artisan horizon:status     # -> Horizon is running
```
Dashboard: `http://localhost:8000/horizon`.

### 18b. Scheduler

`schedule:work` memanggil `schedule:run` tiap menit (pengganti cron di dev). Job yang dijadwalkan
**didorong** ke Redis lalu **dieksekusi** Horizon — terverifikasi di log keduanya. **Satu instance
saja**: dua scheduler = setiap sweep terkirim dua kali. Sebelum 2026-09-14 **tak ada yang pernah
menjalankan scheduler** (tidak di container, tidak di `composer dev`), dan test tetap hijau karena
memanggil job langsung.

```bash
docker compose exec scheduler php artisan schedule:list
```

### 18c. Reverb

`reverb:start --host=0.0.0.0 --port=8080`, port `8080:8080`. PHP mem-publish ke `reverb:8080`; browser
tersambung ke `localhost:8080` (§2). Terverifikasi end-to-end: booking lewat API → event
`slot.availability.changed` di channel privat diterima klien di Windows; mutasi (reverb dimatikan /
`REVERB_HOST=localhost` untuk PHP) → publish gagal.

### 18d. `ShouldBeUnique` diuji lewat perilaku

`tests/Feature/Hardening/RedisUniqueJobTest.php` menguji `ShouldBeUnique` di atas Redis nyata:
duplikat ditahan selama job pending, reminder hasil reschedule (`uniqueId()` =
`{appointment}:{window}`) tetap lolos. Mutasi `uniqueId()` → appointment id saja → 2 dari 3 test
merah. Dilewati tanpa ekstensi `redis` atau tanpa Redis terjangkau (§4) — karena itu CI wajib punya
service `redis`.

### 18e. Jebakan

- **`PendingDispatch` baru mendorong job saat dihancurkan.** Menyimpan `dispatch()` ke variabel di
  dalam loop menunda push satu iterasi — ukuran antrian terbaca meleset dan mudah disangka lock
  unique tak bekerja.
  ```php
  foreach ([1,2,3] as $i) { $r = dispatch($job); }   // SALAH: push tertunda 1 iterasi
  foreach ([1,2,3] as $i) { dispatch($job); }        // benar
  ```
- **Broadcast gagal masuk `failed_jobs`, tidak hilang diam** — cek `php artisan queue:failed` saat
  realtime tak jalan (§11).
