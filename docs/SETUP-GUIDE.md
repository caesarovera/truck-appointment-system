# docs/SETUP-GUIDE.md — Buku Panduan Setup & Build Foundation (manual)

> Panduan **langkah-demi-langkah tanpa AI/vibe coding**. Tujuannya: siapa pun bisa
> membangun ulang foundation TAS dari nol persis seperti kondisi sekarang, lalu
> menjalankan, menguji, dan memahami **kenapa** tiap langkah dilakukan.
>
> Posisi dokumen: ini *how-to operasional*. Aturan arsitektur ada di `CLAUDE.md`,
> domain di `BUSINESS-FLOW.md`, status sesi di `HANDOVER.md`.
>
> Target akhir panduan ini: `migrate:fresh --seed` hijau · PHPStan lvl 8 bersih ·
> Pint bersih · Pest hijau.

---

## Daftar Isi
0. [Prasyarat & versi](#0-prasyarat--versi)
1. [Aktifkan ekstensi PHP (SQLite)](#1-aktifkan-ekstensi-php-sqlite)
2. [Siapkan project & environment](#2-siapkan-project--environment)
3. [Install paket wajib](#3-install-paket-wajib)
4. [Publish config & scaffolding paket](#4-publish-config--scaffolding-paket)
5. [Bangun skema database (migrasi)](#5-bangun-skema-database-migrasi)
6. [Bangun Enum, Model, Factory](#6-bangun-enum-model-factory)
7. [Jalankan migrasi + seeder](#7-jalankan-migrasi--seeder)
8. [Pasang tooling kualitas](#8-pasang-tooling-kualitas)
9. [Menjalankan gerbang kualitas](#9-menjalankan-gerbang-kualitas) · [9a dev server](#9a-dev-server-php-artisan-serve-vs-npm-run-dev) · [9b quality gates](#9b-gerbang-kualitas-sebelum-commit)
10. [Cara menulis & menjalankan test](#10-cara-menulis--menjalankan-test)
11. [Troubleshooting (error nyata yang kami temui)](#11-troubleshooting)
12. [Checklist verifikasi akhir](#12-checklist-verifikasi-akhir)
13. [Lampiran: peta file yang dihasilkan](#13-lampiran-peta-file-yang-dihasilkan)

---

## 0. Prasyarat & versi

Yang harus ada di mesin (versi yang teruji di sesi ini):

| Tool | Versi teruji | Cek |
|------|--------------|-----|
| PHP | 8.3.31 | `php -v` |
| Composer | 2.8.11 | `composer --version` |
| Node.js + npm | LTS (untuk frontend nanti) | `node -v && npm -v` |
| Git | apa saja | `git --version` |
| (opsional) Docker | untuk Horizon/Reverb/Redis | `docker --version` |

> **Catatan Windows/laragon:** PHP berada di
> `C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64\`. Horizon & Reverb butuh
> `ext-pcntl`/`ext-posix` yang **tidak ada** di PHP Windows native → dijalankan
> lewat Docker (Linux). Lihat langkah 3 & bagian Troubleshooting.

Verifikasi cepat:
```bash
php -v
composer --version
```

---

## 1. Aktifkan ekstensi PHP (SQLite)

Project default memakai **SQLite** (`DB_CONNECTION=sqlite`). PHP harus punya driver
`pdo_sqlite`. Cek dulu:

```bash
php -r "print_r(PDO::getAvailableDrivers());"
```

Kalau output **tidak** memuat `sqlite` (mis. hanya `mysql`), aktifkan ekstensi.

Edit `php.ini` (temukan lokasinya dengan `php --ini` → baris *Loaded Configuration File*).
Cari dua baris ini dan **hapus tanda titik-koma** di depannya:

```ini
;extension=pdo_sqlite   →   extension=pdo_sqlite
;extension=sqlite3      →   extension=sqlite3
```

Simpan, lalu verifikasi:
```bash
php -r "print_r(PDO::getAvailableDrivers());"
# Harus muncul: Array ( [0] => mysql [1] => sqlite )
```

> **Kenapa:** tanpa driver ini, perintah `php artisan migrate` gagal dengan
> `PDOException: could not find driver`.

---

## 2. Siapkan project & environment

Jika project sudah ada (kasus kita), cukup pastikan `.env` dan APP_KEY siap.

```bash
cd C:/Dev/Personal-Projects/truck-appointment-system

# 1) Buat .env kalau belum ada
cp .env.example .env          # (Windows PowerShell: Copy-Item .env.example .env)

# 2) Buat file database SQLite kosong
#    (Bash)         touch database/database.sqlite
#    (PowerShell)   New-Item -ItemType File database/database.sqlite

# 3) Generate APP_KEY (wajib, untuk enkripsi/sesi/token)
php artisan key:generate
```

Pastikan di `.env`:
```env
DB_CONNECTION=sqlite
# DB_DATABASE dibiarkan default → file database/database.sqlite
```

> **Kenapa:** Laravel butuh `APP_KEY` untuk enkripsi. `DB_CONNECTION=sqlite`
> membuat dev ringan tanpa server DB terpisah.

---

## 3. Install paket wajib

Paket-paket ini adalah **kontrak** (lihat `CLAUDE.md` → Stack). Pasang dalam 3
kelompok agar mudah dibaca dan di-debug.

### 3a. Paket runtime inti
```bash
composer require \
  laravel/sanctum \
  spatie/laravel-permission \
  spatie/laravel-activitylog \
  spatie/laravel-data
```

### 3b. Horizon + Reverb (butuh flag platform di Windows)
```bash
composer require laravel/horizon laravel/reverb \
  --ignore-platform-req=ext-pcntl \
  --ignore-platform-req=ext-posix
```

> **Kenapa flag itu:** Horizon (queue worker) & Reverb (WebSocket) memakai
> `ext-pcntl`/`ext-posix` yang tak tersedia di PHP Windows native. Flag membuat
> Composer tetap menulis paket ke `composer.json`/`.lock`. **Saat dijalankan**,
> keduanya hidup di container Docker (Linux). `composer install` berikutnya di
> Windows juga perlu flag yang sama.

### 3c. Paket dev (test + analisis)
```bash
composer require --dev \
  pestphp/pest \
  pestphp/pest-plugin-laravel \
  larastan/larastan \
  --with-all-dependencies \
  --ignore-platform-req=ext-pcntl \
  --ignore-platform-req=ext-posix
```

Verifikasi tidak ada celah keamanan:
```bash
composer audit
# Harapan: "No security vulnerability advisories found."
```

| Paket | Untuk apa |
|-------|-----------|
| `laravel/sanctum` | Auth token API + scope per role |
| `spatie/laravel-permission` | RBAC (role & permission) |
| `spatie/laravel-activitylog` | Audit trail perubahan status |
| `spatie/laravel-data` | DTO (input/output terstruktur) |
| `laravel/horizon` | Dashboard + worker queue Redis |
| `laravel/reverb` | Server WebSocket (realtime kuota/antrian) |
| `pestphp/pest` | Framework test |
| `larastan/larastan` | PHPStan + pemahaman Eloquent (analisis statis) |

---

## 4. Publish config & scaffolding paket

### 4a. Scaffolding API (Sanctum)
```bash
php artisan install:api
```
Ini membuat `routes/api.php`, mendaftarkannya di `bootstrap/app.php`, membuat
`routes/channels.php`, dan migrasi `personal_access_tokens`.

> **Catatan:** perintah ini juga mencoba `php artisan migrate`. Kalau langkah 1
> (SQLite) belum beres, di sinilah ia gagal "could not find driver".

### 4b. Publish migrasi/config Spatie
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
```

### 4c. Horizon & Reverb
```bash
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Reverb\ReverbServiceProvider"
```

> **Realtime sisi server SUDAH jadi & ber-test** (event `ShouldBroadcast`
> `SlotAvailabilityChanged`/`GateQueueUpdated` + listener + channel auth di
> `routes/channels.php` + seam `GateEventGateway`). Yang **belum** dilakukan untuk
> benar-benar menyiarkan: (1) `php artisan reverb:start` (Docker) + set
> `BROADCAST_CONNECTION=reverb` di `.env`; (2) daftarkan
> `Broadcast::routes(['middleware' => ['auth:sanctum']])` agar auth channel privat
> jalan untuk SPA Sanctum; (3) sambungkan Laravel Echo di frontend Vue. TOS push
> masih `LoggingGateEventGateway` (placeholder) — swap binding saat ada TOS riil.

---

## 5. Bangun skema database (migrasi)

Semua migrasi ada di `database/migrations/`. Buat dengan:
```bash
php artisan make:migration create_terminals_table
# ...dst untuk tiap tabel
```
Lalu isi sesuai ERD `BUSINESS-FLOW.md §4`. Urutan **penting** karena foreign key:

```
1. terminals
2. gates                 (FK → terminals)
3. transport_companies
4. trucks                (FK → transport_companies)
5. slot_windows          (FK → gates)
6. appointments          (FK → companies, trucks, users, slot_windows)
7. containers            (FK → appointments;  + slot_window_id nullable)
8. gate_transactions     (FK → appointments, users)
```

Plus 1 perubahan kecil di migrasi **users** bawaan: tambah kolom
`terminal_id` & `company_id` (nullable, ber-index, tanpa FK lintas-tabel agar
`migrate:fresh` di SQLite tidak rewel soal urutan ALTER).

Aturan hardening yang ditanam di skema (lihat `CLAUDE.md`):
- `slot_windows`: unik `(gate_id, date, start_time)` + index `(gate_id, date, status)`.
- `appointments`: `version` (optimistic lock), `booking_code` unik, banyak index status.
- `containers`: unik `(slot_window_id, container_no)` — pertahanan terakhir anti
  double-booking. `slot_window_id` nullable: saat cancel/no-show di-NULL-kan agar
  kontainer bisa dipakai lagi (NULL ganda diizinkan SQLite & MySQL).
- `gate_transactions`: unik `(appointment_id, type)` — cegah double gate-in/out.

> **Kenapa enum disimpan sebagai `string` di DB, bukan `enum()` MySQL:** portabel
> (SQLite + MySQL sama), dan tipe-amannya ditegakkan di level aplikasi via PHP Enum
> (lihat langkah 6).

---

## 6. Bangun Enum, Model, Factory

### 6a. Enum (di `app/Enums/`)
Lima enum: `AppointmentStatus`, `MoveType`, `SlotWindowStatus`,
`GateTransactionType`, `TruckStatus`.

`AppointmentStatus` **memuat state machine** (`BUSINESS-FLOW.md §2`) sebagai
method, bukan sekadar daftar nilai:
```php
AppointmentStatus::BOOKED->canTransitionTo(AppointmentStatus::CONFIRMED); // true
AppointmentStatus::COMPLETED->isFinal();                                  // true
AppointmentStatus::CONFIRMED->holdsQuota();                               // true
```
> **Kenapa di enum:** satu sumber kebenaran transisi; Action tinggal memanggilnya,
> tidak menyebar `if` status ke mana-mana.

### 6b. Model (di `app/Models/`)
8 model + update `User`. Yang penting:
- `User`: tambah trait `HasApiTokens` (Sanctum) & `HasRoles` (Spatie), set
  `protected string $guard_name = 'api';`, tambah `terminal_id`/`company_id` ke
  `$fillable`, relasi `terminal()`/`company()`.
- `Appointment`: pakai trait `LogsActivity` (audit), casts enum, relasi lengkap,
  helper `isGatedIn()`, plus docblock `@property` (lihat catatan PHPStan).
- `SlotWindow`: casts + helper `isOpen()`, `hasCapacity()`, `remaining()`.

> **Wajib `@property` di model untuk PHPStan level 8.** Larastan menebak tipe kolom
> dari DB sebagai `string`; tanpa anotasi `@property AppointmentStatus $status`,
> perbandingan enum dianggap "selalu false". Anotasi memberi tahu tipe sebenarnya.

### 6c. Factory (di `database/factories/`)
Satu factory per model untuk test & seeding. Contoh state berguna:
```php
SlotWindow::factory()->nearlyFull()->create(); // sisa 1 slot → uji race
SlotWindow::factory()->full()->create();        // penuh → uji 409
Appointment::factory()->confirmed()->create();
```

> **Jebakan PHPStan pada factory:** JANGAN tulis `/** @return array<string,mixed> */`
> di atas `definition()`. Induk `Factory::definition()` (versi Larastan) bertipe
> `array<model property of TModel, mixed>` yang lebih sempit; menimpa dengan tipe
> lebih lebar melanggar covariance → error `method.childReturnType`. Solusi:
> **hapus docblock-nya** agar mewarisi tipe induk. (Detailnya di Troubleshooting.)

---

## 7. Jalankan migrasi + seeder

```bash
php artisan migrate:fresh --seed
```

Urutan seeder diatur `database/seeders/DatabaseSeeder.php`:
`RolePermissionSeeder` (role & permission dulu) → `DemoSeeder` (data demo).

Output yang diharapkan: semua migrasi `DONE`, lalu:
```
Database\Seeders\RolePermissionSeeder ... DONE
Database\Seeders\DemoSeeder ........... DONE
```

Apa yang dibuat (lihat `docs/DUMMY-DATA.md`):
- 5 role + permission sesuai matriks RBAC `BUSINESS-FLOW.md §1`.
- 7 akun demo (password semua `password`), 1 terminal + 2 gate, 2 perusahaan +
  armada + sopir, slot kemarin/hari-ini/besok, dan appointment menyentuh **semua**
  status. Satu window hari ini sengaja **hampir penuh** untuk demo race.

Cek cepat lewat tinker:
```bash
php artisan tinker
>>> App\Models\Appointment::count();
>>> App\Models\User::where('email','planner@tas.test')->first()->can('slot.manage'); // true
```

---

## 8. Pasang tooling kualitas

### 8a. PHPStan (`phpstan.neon` di root)
```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    level: 8
    paths:
        - app
        - database
        - routes
    checkModelProperties: true
```

### 8b. Pest (`tests/Pest.php`)
```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
pest()->extend(Tests\TestCase::class)->in('Unit');
```

### 8c. DB test in-memory (`phpunit.xml`)
Aktifkan dua baris ini (hapus komentarnya):
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```
> **Kenapa wajib:** `RefreshDatabase` akan *migrate fresh* tiap run. Tanpa
> `:memory:`, ia akan **menghapus** isi `database/database.sqlite` (data demo dev).
> In-memory = cepat + terisolasi + dev DB aman.

### 8d. Shortcut composer (`composer.json` → scripts)
```json
"test": "pest --parallel",
"analyse": "phpstan analyse --memory-limit=1G",
"fix": "pint",
"lint": "pint --test"
```

### 8e. Anti N+1 (`app/Providers/AppServiceProvider.php` → boot())
```php
Model::preventLazyLoading(! $this->app->isProduction());
Model::preventAccessingMissingAttributes(! $this->app->isProduction());
```

---

## 9. Menjalankan gerbang kualitas

### 9a. Dev server: `php artisan serve` vs `npm run dev`

Untuk melihat app, buka **`http://localhost:8000`** (Laravel) — bukan `localhost:5173`.
Port 5173 adalah Vite HMR server, bukan entry point app.

| Situasi | Command |
|---------|---------|
| Lagi coding aktif | `php artisan serve` + `npm run dev` |
| Hanya test fitur, tidak edit kode | `php artisan serve` saja (asal sudah ada hasil build) |
| Deploy ke server | `npm run build` sekali, Vite tidak diperlukan lagi |

**Tanpa `npm run dev`:** browser load JS/CSS dari hasil build terakhir di `public/build/`.
Edit `.vue`/`.ts` → harus `npm run build` manual → refresh browser manual.

**Dengan `npm run dev`:** Hot Module Replacement (HMR) aktif — edit komponen Vue →
browser otomatis update tanpa full refresh, state Pinia tetap. Perubahan terlihat
dalam milidetik. `npm run dev` murni untuk kenyamanan developer, bukan keharusan fungsional.

> **Penjelasan kode SPA** (arsitektur, TanStack Query, tiap halaman + *kenapa*):
> `docs/FRONTEND.md`. Test frontend: `npm run test:js` · type-check: `npm run type-check`.

---

### 9b. Gerbang kualitas (sebelum commit)

Jalankan ketiganya tiap sebelum commit (Definition of Done di `CLAUDE.md`):

```bash
composer fix        # atau: ./vendor/bin/pint        → rapikan format
composer analyse    # atau: ./vendor/bin/phpstan ...  → analisis statis lvl 8
composer test       # atau: ./vendor/bin/pest         → semua test
```

Contoh output sehat:
```
# composer analyse
 [OK] No errors

# composer fix
.................  (PASS) atau daftar file yang dirapikan

# composer test
  PASS  Tests\Unit\AppointmentStatusTest
  PASS  Tests\Feature\FoundationSeedTest
  ...
  Tests:    165 passed (439 assertions)
```

> Urutan disarankan: **fix → analyse → test**. `fix` merapikan dulu agar `analyse`
> tidak terganggu gaya kode; baru pastikan logika lewat `test`.

---

## 10. Cara menulis & menjalankan test

### 10a. Test unit murni (tanpa DB) — contoh enum
`tests/Unit/AppointmentStatusTest.php`:
```php
it('follows the documented transition graph', function (): void {
    expect(AppointmentStatus::BOOKED->canTransitionTo(AppointmentStatus::CONFIRMED))
        ->toBeTrue();
});
```

### 10b. Test feature (pakai DB in-memory) — contoh seed/RBAC
`tests/Feature/FoundationSeedTest.php`:
```php
use function Pest\Laravel\seed;

it('wires the RBAC matrix', function (): void {
    seed([RolePermissionSeeder::class, DemoSeeder::class]);
    $planner = User::query()->where('email','planner@tas.test')->firstOrFail();
    expect($planner->can('slot.manage'))->toBeTrue()
        ->and($planner->can('appointment.write'))->toBeFalse();
});
```

### 10c. Menjalankan sebagian
```bash
./vendor/bin/pest tests/Unit/AppointmentStatusTest.php   # satu file
./vendor/bin/pest --filter="RBAC"                        # cocokkan nama test
./vendor/bin/pest --parallel                             # paralel (cepat)
```

> **Pola TDD wajib (per Action)** sesuai `CLAUDE.md`: tulis test dulu (happy path +
> edge: kuota penuh→409, double-submit + Idempotency-Key, bentrok `version`) →
> implement sampai hijau → `fix && analyse && test` → commit kecil.

### 10d. Menguji API booking secara MANUAL (curl)

Endpoint yang sudah ada:

| Method | Endpoint | Auth | Permission | Guna |
|--------|----------|------|------------|------|
| `POST` | `/api/v1/login` | publik | — | tukar email+password → token |
| `POST` | `/api/v1/logout` | token | — | cabut token saat ini |
| `GET`  | `/api/v1/me` | token | — | profil + role + permission |
| `GET`  | `/api/v1/gates?terminal={id}` | token | `slot.read` | daftar gate (dropdown); `terminal` opsional |
| `GET`  | `/api/v1/me/fleet` | token | `fleet.manage` | truk & sopir milik company transporter (form booking) |
| `GET`  | `/api/v1/slots/availability?gate={id}&date=YYYY-MM-DD` | token | `slot.read` | sisa kuota slot |
| `POST` | `/api/v1/appointments` | token | `appointment.write` | booking (kirim `Idempotency-Key`) |
| `GET`  | `/api/v1/appointments/{id}` | token | Policy `view` | detail appointment (scope per role) |
| `POST` | `/api/v1/appointments/{id}/reschedule` | token | Policy `update` | pindah window (body: `slot_window_id`, `version`) |
| `POST` | `/api/v1/appointments/{id}/cancel` | token | Policy `cancel` | batalkan (kembalikan kuota); body opsional `version` → optimistic lock (409 `version_conflict` bila usang) |
| `POST` | `/api/v1/appointments/{id}/gate-in` | token | Policy `process` | gate-in (CONFIRMED→IN_PROGRESS), idempoten |
| `POST` | `/api/v1/appointments/{id}/gate-out` | token | Policy `process` | gate-out (IN_PROGRESS→COMPLETED), idempoten |
| `POST` | `/api/v1/slots` | token | `slot.manage` | planner buka window (body: `gate`, `date`, `start_time`, `end_time`, `capacity`) |
| `POST` | `/api/v1/slots/{slotWindow}/close` | token | `slot.manage` | planner tutup window (status CLOSED, idempoten) |
| `GET`  | `/api/v1/me/appointments?status={STATUS}` | token | `appointment.read` + punya company | daftar booking transporter (filter status opsional) |
| `GET`  | `/api/v1/me/appointments/today` | token | `appointment.read.self` | jadwal hari-H sopir |
| `GET`  | `/api/v1/gate/queue?date=YYYY-MM-DD` | token | `gate.process` + punya terminal | antrian gate-officer (CONFIRMED/IN_PROGRESS di terminalnya, default hari ini) |
| `GET`  | `/api/v1/reports/utilization?gate={id}&date=YYYY-MM-DD` | token | planner/admin | utilisasi gate (kuota vs terpakai vs no-show) |

**Admin — master data CRUD** (semua di bawah `/api/v1/admin`, butuh permission manage terkait;
hapus → **409 `entity_in_use`** bila masih ada dependen):

| Method | Endpoint | Permission | Guna |
|--------|----------|------------|------|
| `GET` · `POST` | `/admin/terminals` · `/admin/terminals/{id}` (GET/PUT/DELETE) | `terminal.manage` | CRUD terminal (`code`, `name`); hapus ditolak bila punya gate |
| `GET` · `POST` | `/admin/gates` · `/admin/gates/{id}` (GET/PUT/DELETE) | `gate.manage` | CRUD gate (`terminal_id`, `code`, `name`); hapus ditolak bila punya slot window |
| `GET` · `POST` | `/admin/companies` · `/admin/companies/{id}` (GET/PUT/DELETE) | `company.manage` | CRUD perusahaan angkutan; hapus ditolak bila punya user/appointment |
| `GET` · `POST` | `/admin/users` · `/admin/users/{id}` (GET/PUT/DELETE) | `user.manage` | CRUD user (`name`, `email`, `role`, `password?`, `terminal_id?`, `company_id?`); password di-hash saat dibuat & hanya diubah bila diisi; tak bisa hapus diri sendiri (422) |

Jalankan server: `php artisan serve` (default `http://127.0.0.1:8000`), pastikan data
demo ada (`php artisan migrate:fresh --seed`).

**0) Login** untuk dapat token (akun demo, password `password`):
```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"dispatcher@majulog.test","password":"password"}'
# → 201 {"token":"3|abcdef...","token_type":"Bearer","user":{...,"permissions":[...]}}
```
Salin nilai `token` → pakai sebagai `TOKEN` di langkah berikutnya.

**1) Lihat ketersediaan slot** (ganti `TOKEN` & `GATE_ID`):
```bash
curl -s http://127.0.0.1:8000/api/v1/slots/availability?gate=1 \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"
# → {"data":[{"id":..,"remaining":..,"status":"OPEN",...}]}
```

**2) Booking** (pakai id slot/truk/sopir milik company dispatcher tsb):
```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/appointments \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: demo-001" \
  -d '{"slot_window_id":5,"truck_id":1,"driver_id":6,"move_type":"DELIVERY","container_no":"MAUU1234567","iso_type":"22G1","size":20}'
# → 201 {"data":{"status":"CONFIRMED","booking_code":"TAS-....",...}}
```

**3) Uji idempotency** — ulangi perintah (2) **persis** (Idempotency-Key sama):
respons 201 yang sama diputar ulang, header `Idempotent-Replayed: true`, dan **tidak**
ada appointment baru. Cek jumlah: `php artisan tinker --execute="echo App\Models\Appointment::count();"`.

**Respons error yang diharapkan:**
- Slot penuh/tutup → `409 {"error":"slot_unavailable"}`
- Kontainer dobel di window sama → `409 {"error":"duplicate_booking"}`
- Truk/sopir company lain → `422` (validation `truck_id`/`driver_id`)
- Tanpa token → `401` · role tanpa `appointment.write` → `403`
- Terlalu sering (rate limit) → `429 Too Many Requests` (+ header `Retry-After`)

> **Rate limit (CLAUDE.md §Hardening).** Named limiter di `AppServiceProvider`:
> `login` (5/mnt, kunci email+ip — anti brute-force), `api` (60/mnt per user/ip — batas
> umum endpoint ber-auth), `booking` (10/mnt per user — lebih ketat, anti bot borong slot).
> Nilai bisa di-set via env `TAS_RL_LOGIN` / `TAS_RL_API` / `TAS_RL_BOOKING` (lihat
> `config/tas.php`). Saat uji manual berturut-turut, jangan kaget bila kena `429`.

---

## 11. Troubleshooting

Error nyata yang kami temui di sesi ini + solusinya.

### "could not find driver" saat migrate
Driver SQLite belum aktif. → **Langkah 1** (aktifkan `pdo_sqlite` + `sqlite3`).

### Horizon gagal install: "requires ext-pcntl"
PHP Windows tak punya `ext-pcntl`. → install dengan
`--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`, jalankan
Horizon/Reverb di Docker.

### `reverb:install` menggantung / error prompt
Installer-nya interaktif. → di lingkungan non-interaktif, publish config saja
(`vendor:publish ... ReverbServiceProvider`). Realtime sisi server sudah ber-event &
ber-test; untuk menyiarkan sungguhan jalankan `reverb:start` (Docker) +
`BROADCAST_CONNECTION=reverb` + `Broadcast::routes(auth:sanctum)` (lihat langkah 4c).

### PHPStan: `Strict comparison ... will always evaluate to false` (enum)
Larastan menebak kolom sebagai `string`. → tambah docblock `@property` di model,
mis. `@property AppointmentStatus $status`.

### PHPStan: `method.childReturnType` di factory `definition()`
Docblock `@return array<string,mixed>` lebih lebar dari tipe induk. → **hapus**
docblock itu (mewarisi tipe induk yang presisi).

### PHPStan: `offsetAccess.notFound` "Offset '10' does not exist"
PHP menormalkan kunci string angka (`'10'`) menjadi **int** `10`, sehingga array
bercampur kunci string/int. → pakai **kunci integer konsisten** (lihat
`DemoSeeder::windows()` yang di-key per jam `6..17`).

### PHPStan: `argument.type` pada `Model::create(array_merge(...))`
`create()` minta `array<model property,...>`; `array_merge` menghasilkan
`array<string,mixed>`. → bangun model lalu `->forceFill($attrs)->save()`.

### Test "menghapus" data dev
`RefreshDatabase` jalan di DB file. → aktifkan `:memory:` di `phpunit.xml`
(**Langkah 8c**).

### Fatal: "Declaration of ...::data() must be compatible with Request::data()"
FormRequest (turunan `Request`) sudah punya method `data()` dan `date()`. Method
helper buatanmu yang bernama sama dengan signature beda → bentrok. → **ganti nama**
(mis. `toData()`, `requestedDate()`).

### `Cache::tags()` error "does not support tagging"
Cache store `database`/`file` tidak mendukung tag. → pakai **key eksplisit** +
`Cache::forget`, atau pindah ke Redis. Lihat `SlotRepository` & changelog `HANDOVER.md`.

---

## 12. Checklist verifikasi akhir

Tandai semua sebelum menganggap foundation selesai:

- [ ] `php -r "print_r(PDO::getAvailableDrivers());"` memuat `sqlite`
- [ ] `composer audit` → no advisories
- [ ] `php artisan migrate:fresh --seed` → hijau
- [ ] `composer analyse` → **No errors** (PHPStan level 8)
- [ ] `composer lint` → Pint bersih
- [ ] `composer test` → semua hijau
- [ ] Login data demo bekerja (cek via tinker `->can(...)`)
- [ ] `database/database.sqlite` tetap berisi data demo setelah `composer test`
      (bukti `:memory:` aktif)

---

## 13. Lampiran: peta file yang dihasilkan

```
app/
  Enums/            AppointmentStatus, MoveType, SlotWindowStatus,
                    GateTransactionType, TruckStatus
  Models/           User(updated), Terminal, Gate, TransportCompany, Truck,
                    SlotWindow, Appointment, Container, GateTransaction
  Actions/          Book/Reschedule/Cancel/GateIn/GateOut/MarkNoShow/
                    Open|CloseSlotWindow + Admin/ (CRUD terminal/gate/company/user)
  Contracts/        Slot/Appointment/Gate/Fleet/Terminal/Company/User repo interfaces
  Repositories/     impl Eloquent dari tiap interface (bound di AppServiceProvider)
  Http/Controllers/Api/V1/  invokable controllers + Admin/ (20 controller CRUD)
  Exceptions/       SlotUnavailable, Duplicate*, OptimisticLock,
                    InvalidAppointmentState, EntityInUse (409 cascade-delete guard)
  Providers/        AppServiceProvider (preventLazyLoading + repo bindings + rate limiters)
database/
  migrations/       8 migrasi domain + users(updated) + (publish: sanctum,
                    permission, activitylog)
  factories/        1 factory per model
  seeders/          RolePermissionSeeder, DemoSeeder, DatabaseSeeder
routes/
  api.php           (dari install:api)
  channels.php      (dari install:api)
config/             permission.php, activitylog.php, reverb.php, sanctum.php ...
tests/
  Pest.php          binding TestCase + RefreshDatabase
  Unit/             AppointmentStatusTest
  Feature/          FoundationSeedTest
phpstan.neon        level 8
phpunit.xml         DB :memory: untuk test
composer.json       scripts: test / analyse / fix / lint
```

---

### Status & langkah berikutnya
**Backend MVP API + SPA 4 persona + admin CRUD sudah lengkap & hijau** (status hidup:
`HANDOVER.md`). Backend: data layer → booking (anti-race) → auth Sanctum + Policy →
reschedule/cancel → gate-in/out → job no-show/reminder → realtime broadcast (+ seam TOS)
→ endpoint pendukung (me/today + utilisasi) → slot-window open/close → rate-limit
hardening → master data CRUD admin. Frontend: SPA Vue untuk transporter, driver,
gate-officer, planner, + halaman admin. Penjelasan tiap slice: `docs/CODE-WALKTHROUGH.md`
(§J–§V backend) & `docs/FRONTEND.md` (SPA).

Gerbang kualitas terakhir: `composer test` → **165 pass (439 assertions)** ·
`composer analyse` PHPStan lvl 8 ✅ · `npm run test:js` → **57 pass**.

Langkah berikutnya (lihat `HANDOVER.md` → *Langkah berikutnya*): **wiring realtime
sungguhan** (Reverb server + `Broadcast::routes` auth:sanctum + Laravel Echo di SPA +
swap `GateEventGateway` ke TOS riil); polish layout/nav bersama; laporan utilisasi
company-scoped transporter.
