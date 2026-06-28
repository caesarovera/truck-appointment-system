# HANDOVER.md — truck-appointment-system

> Status hidup proyek **antar-sesi & antar-perangkat**.
> Update di **akhir tiap sesi** Claude Code, dan **setiap kali** kontrak/dokumen/seeder berubah.
> Sesi atau perangkat baru: baca file ini **setelah** `CLAUDE.md`.
>
> Pembagian peran (jangan dicampur):
> `CLAUDE.md` = konstitusi (aturan tetap) · `docs/*` = detail rujukan · `HANDOVER.md` = jurnal (keadaan + keputusan terbaru).

---

## Status
- Update terakhir: `2026-06-28` · Sesi: **Backlog hardening** (version di cancel · dueForNoShow chunking · idempotency lock TTL+hash).
- Branch: `main` (repo di-init + push ke GitHub `caesarovera/truck-appointment-system`).
- Build backend: `composer test` → ✅ **100 pass / 289 assert** · `composer analyse` → ✅ PHPStan lvl 8 · `composer fix` → ✅ Pint bersih.
- Build frontend: `npm run test:js` → ✅ **7 pass** · `npm run type-check` (vue-tsc) → ✅ · `npm run build` → ✅.

## Sudah selesai
- [x] Paket wajib terpasang: Sanctum, Horizon, Reverb, Spatie Permission/ActivityLog/Data, Pest, Larastan. Config/migrasi sudah dipublish; `routes/api.php` + `channels.php` ter-wire (install:api).
- [x] Skema + model (BUSINESS-FLOW §4): 8 migrasi domain + kolom `terminal_id`/`company_id` di users. Model + relasi + casts + 5 Enum (`AppointmentStatus` memuat state machine). Factory untuk semua model.
- [x] RolePermissionSeeder + DemoSeeder jalan (`migrate:fresh --seed` hijau).
- [x] Tooling: `phpstan.neon` (lvl 8), `tests/Pest.php` (RefreshDatabase di Feature), composer scripts `test/analyse/fix/lint`, `AppServiceProvider::preventLazyLoading`. phpunit pakai sqlite `:memory:` (dev db tidak ke-wipe).
- [x] **Data layer:** `SlotRepositoryInterface`/`AppointmentRepositoryInterface` + impl Eloquent, bound di `AppServiceProvider`.
- [x] **BookAppointmentAction + test race condition:** `DB::transaction(attempts:3)` + `lockForUpdate` + cek penuh/tutup (409) + `containers.slot_window_id` + cek kepemilikan fleet. Event `AppointmentBooked` → listener invalidasi cache. 12 test (Action + endpoint).
- [x] **Endpoint:** `GET /api/v1/slots/availability` (Cache::flexible) + `POST /api/v1/appointments` (auth:sanctum + middleware `idempotency`). FormRequest+DTO masuk, Resource keluar.

- [x] **Auth Sanctum:** `POST /api/v1/login` (token + abilities = permission role), `POST /logout` (cabut token), `GET /me`. `UserResource`. 7 test.
- [x] **AppointmentPolicy + show:** `GET /api/v1/appointments/{id}` via middleware `can:view,appointment`. Policy: admin/planner semua, gate-officer per terminal, transporter per company, driver per `driver_id`. 7 test. (Auto-discovered, tak perlu daftar manual.)
- [x] **Reschedule & Cancel:** `POST /api/v1/appointments/{id}/reschedule` (optimistic `version`, lock 2 window, pindah kuota, `version++`, pindah container) + `POST .../cancel` (kembalikan kuota, NULL container). Policy `update`/`cancel`. Events `AppointmentRescheduled`/`AppointmentCancelled` + cache invalidate via interface `AffectsSlotAvailability`. Exceptions `OptimisticLockException`/`InvalidAppointmentStateException` (409). 9 test.
- [x] **Gate-in & Gate-out:** `POST .../gate-in` (CONFIRMED→ARRIVED→IN_PROGRESS, MVP satu aksi) + `POST .../gate-out` (IN_PROGRESS→COMPLETED). `GateInAction`/`GateOutAction` = `DB::transaction(attempts:3)` + row `lockForUpdate` + guard idempoten (`isGatedIn` / `COMPLETED` → no-op, tak ada transaksi ganda) + middleware `idempotency`. Repo `recordGateIn`/`recordGateOut` buat `gate_transactions` (unik `(appointment_id,type)`). Policy `process` (gate-officer per terminal, admin via before). Events `TruckGatedIn`/`TruckGatedOut` (interface `RecordsGateEvent`) → listener `ProcessGateEventOnTos` → `ProcessGateEventJob` (`ShouldBeUnique`+`WithoutOverlapping` per appointment, guard cek transaksi, push TOS = TODO realtime slice). Resource: `gate_in_at`/`gate_out_at`. 10 test.
- [x] **No-show sweep & Reminder:** `NoShowSweepJob` (`Schedule::job(...)->everyFiveMinutes()` di `routes/console.php`, `WithoutOverlapping('no-show-sweep')->dontRelease()`) → repo `dueForNoShow(now, grace)` (saring kasar DB `whereDate<=` + refine PHP window.end+grace, portabel sqlite/mysql) → per kandidat `MarkNoShowAction` (mirror cancel: `DB::transaction(attempts:3)`+lock+`canMarkNoShow`+`markNoShow`+kembalikan kuota+NULL container, event `AppointmentNoShow` impl `AffectsSlotAvailability`; balapan gate-in/cancel → catch `InvalidAppointmentStateException`). `AppointmentReminderJob` (`ShouldBeUnique` uniqueId=appointment id, tries/backoff/failed) kirim `AppointmentReminderNotification` (mail) ke sopir, cek status terkini (BOOKED/CONFIRMED saja → tahan reschedule/cancel). Listener `ScheduleAppointmentReminder` on `AppointmentBooked` dispatch reminder delayed H-(`reminder_lead_minutes`). Grace & lead di `config/tas.php`. 12 test.
- [x] **Realtime broadcast & TOS seam:** broadcast events `SlotAvailabilityChanged` (channel `slot.{gateId}`, payload datar sisa kuota) & `GateQueueUpdated` (channel `gate.queue.{terminalId}`), keduanya `ShouldBroadcast`. Listener `BroadcastSlotAvailability` (on `AffectsSlotAvailability`, group window by gate) & `BroadcastGateQueue` (on `RecordsGateEvent`, resolve terminal dari slotWindow.gate) — auto-discovered, sejajar listener cache/TOS yang sudah ada. Channel auth di `routes/channels.php` (`slot.{gateId}`→`can('slot.read')`; `gate.queue.{terminalId}`→admin/planner/driver, gate-officer per terminal). TOS seam: contract `GateEventGateway` + `LoggingGateEventGateway` (bound di AppServiceProvider) → `ProcessGateEventJob::handle()` ganti TODO jadi `$tos->push()` (guard idempoten tetap). `phpunit.xml`: `BROADCAST_CONNECTION=null`. 7 test.
- [x] **Endpoint pendukung:** `GET /api/v1/me/appointments/today` (driver, `TodayAppointmentsRequest::authorize`→`appointment.read.self`; repo `todayForDriver(driverId,date)` eager-load truck/driver/company/slotWindow.gate/containers; output `AppointmentResource::collection`) + `GET /api/v1/reports/utilization?gate=&date=` (planner/admin only via `UtilizationReportRequest`; repo `SlotRepository::utilization` `withCount` alias completed/no_show/cancelled/active per window; `SlotUtilizationResource` + `meta.summary` total via `->additional()`). 7 test. CATATAN: laporan utilisasi = agregat lintas-company (planner/admin); laporan company-scoped transporter belum dibuat.
- [x] **Slot-window management (planner):** `POST /api/v1/slots` (`OpenSlotWindowAction`: repo `create` window OPEN+booked_count 0, unik `(gate_id,date,start_time)` → `DuplicateSlotWindowException` 409; event `SlotWindowOpened`) + `POST /api/v1/slots/{slotWindow}/close` (`CloseSlotWindowAction`: `DB::transaction(attempts:3)`+`lockForUpdate`, status→CLOSED bukan delete, idempoten, event `SlotWindowClosed`). Keduanya event impl `AffectsSlotAvailability` → reuse listener cache-invalidate + broadcast. Auth via FormRequest `slot.manage`. DTO `OpenSlotWindowData` (Spatie Data). Validasi: date `after_or_equal:today`, time `H:i:s` + `after:start_time`, capacity 1..1000. 12 test (termasuk verifikasi window muncul/hilang di endpoint availability).

- [x] **Frontend foundation + Auth/Login (slice 1):** SPA Vue 3 in-repo di `resources/js` (Vite + laravel-vite-plugin + Tailwind v4 yang sudah ada). Stack: Vue 3 (Composition + `<script setup>`) + TS + Pinia + Vue Router + TanStack Query + Axios. Build: `@vitejs/plugin-vue` di `vite.config.js` (input → `app.ts`, alias `@`→`resources/js`), `tsconfig.json` (extends `@vue/tsconfig`), `vitest.config.ts` (jsdom). Shell `resources/views/app.blade.php` + catch-all `routes/web.php` (`^(?!api).*$`). Kode: `api/client.ts` (axios `/api/v1` + Bearer interceptor + 401 handler, token di localStorage), `stores/auth.ts` (login/logout/fetchMe/restore/can/hasRole), `router/index.ts` (guard requiresAuth/guestOnly + restore sesi), `pages/LoginPage.vue` + `DashboardPage.vue`, `App.vue`, `app.ts`. 7 test Vitest (store + komponen login). CATATAN respons: `/login` flat (`{token,user}`), `/me` terbungkus `{data}` — store menangani keduanya.

- [x] **Hardening: rate limiting (slice keamanan):** named limiter di `AppServiceProvider::configureRateLimiters()` — `login` (anti brute-force, kunci `email|ip`), `api` (batas umum endpoint ber-auth, kunci user id/ip), `booking` (lebih ketat dari `api`, anti bot borong slot). Nilai di `config/tas.php` → `rate_limits` (env `TAS_RL_LOGIN`=5, `TAS_RL_API`=60, `TAS_RL_BOOKING`=10). Pasang di `routes/api.php`: `throttle:login` pada login, group ber-auth `throttle:api`, booking tambah `throttle:booking`. Menutup gap kontrak CLAUDE.md §Hardening (rate limit) yang sebelumnya KOSONG. 3 test (`tests/Feature/Hardening/`: login 429 + keyed-by-email, booking 429 per user). CACHE_STORE=array → limiter ter-reset per test (tak ada bleed antar-test).

## Senior review (2026-06-28) — temuan & keputusan
> Audit menyeluruh actions/repos/middleware/policy/migrasi/auth. Kesimpulan: foundation kuat (race handling, layering, idempotency benar). Temuan & status:
- **[FIXED] Tidak ada rate limiting** (melanggar kontrak) → slice di atas. Login brute-forceable & booking bisa di-borong bot — kini ber-throttle.
- **[DEFERRED — sengaja tidak diimplementasikan] Token abilities Sanctum tak ditegakkan.** Login mencetak token dgn abilities = SELURUH permission role, dan tak ada jalur token ber-scope sempit. Maka `abilities:` middleware tak pernah bisa menolak yang Policy/permission belum tolak → murni redundan + friksi (paksa semua test `actingAs` kirim `['*']`). Tegakkan NANTI saat aplikasi benar-benar menerbitkan token sempit (mis. token mobile read-only). Otorisasi saat ini tetap aman lewat Policy + FormRequest `can()`.
- **[FIXED] `version` optimistic lock tak konsisten:** `cancel` kini terima `version` opsional → bila dikirim, optimistic lock ditegakkan (`OptimisticLockException` 409 `version_conflict`); bila tidak, cancel tetap jalan (backward compatible). `CancelAppointmentRequest` + `CancelAppointmentAction::execute($appointment, ?int $expectedVersion)`. 4 test.
- **[FIXED] `dueForNoShow` muat semua kandidat ke memori:** kini dipindai `chunkById` (size dari `config('tas.no_show_chunk_size')` default 500) → hanya N baris di-hydrate per iterasi; hanya yang lewat grace ditahan. Test lintas-batas chunk (size=2, 5 due).
- **[FIXED] Idempotency lock TTL 10 dtk → 60 dtk** (`config('tas.idempotency.lock_seconds')`, ttl_hours juga) supaya lock tak kedaluwarsa di tengah request berat. Nilai header di-`hash('sha256')` jadi kunci cache (bounded lintas store, anti key-injection). 2 test (replay key panjang + contention 409).
- **[catatan] `$fillable` memuat `status`/`version`/`company_id`:** aman sekarang (Action set eksplisit, tak ada mass-assign), tapi ranjau laten — pertimbangkan `$guarded` kolom yang hanya Action boleh ubah.

- [x] **Backlog hardening (3 item dari senior review):** (1) optimistic `version` opsional di cancel, (2) `dueForNoShow` chunked scan (config `no_show_chunk_size`), (3) idempotency lock TTL 60s + key hashing (config `idempotency.lock_seconds`/`ttl_hours`). Semua via `config/tas.php` (tunable env). +8 test di `tests/Feature/{Appointments,Jobs,Hardening}`. Detail di *Senior review* (status [FIXED]).

## Sedang dikerjakan
- (kosong) — backlog hardening selesai di checkpoint hijau (100 pass).

## Langkah berikutnya (urut)
1. **Frontend slice berikutnya:** layout ber-auth (sidebar/nav per role via `auth.can/hasRole`) → ketersediaan slot (`GET /slots/availability`, TanStack Query) → booking → dashboard gate → jadwal driver → planner kelola window.
2. **Wiring realtime sungguhan:** `reverb:start` (Docker) + `BROADCAST_CONNECTION=reverb` + `Broadcast::routes(auth:sanctum)` + sambung Laravel Echo di SPA; swap `GateEventGateway` ke TOS riil.
3. **Opsional backend:** laporan utilisasi company-scoped untuk transporter; CRUD master data (terminal/gate/truck/driver) untuk admin.
4. **Backlog hardening sisa:** token abilities sempit (lihat *Senior review*) — ditegakkan saat aplikasi menerbitkan token ber-scope sempit. (3 item lain sudah [FIXED].)

## Changelog kontrak / dokumen / seeder
> Catat tiap perubahan yang menyentuh CLAUDE.md, docs/*, atau seeder.
> Format: `tanggal: APA yang berubah → file mana yang ikut diupdate. Alasan.`
- `2026-06-27`: DemoSeeder.windows() key diubah dari string '06'..'17' → **integer** 6..17.
  Alasan: PHP menormalkan kunci string angka ('10') jadi int → array kunci campuran bikin
  PHPStan lvl 8 `offsetAccess.notFound`. Tidak ada perubahan kontrak/docs.
- `2026-06-27`: `containers.slot_window_id` (nullable) **ditambah** untuk menegakkan unik
  `(slot_window_id, container_no)` per appointment aktif (cancel/no-show → NULL melepas slot).
  Belum tercermin di BUSINESS-FLOW §4 ERD — **TODO** sinkronkan saat slice booking.
- `2026-06-28`: `phpunit.xml` set `BROADCAST_CONNECTION=null` agar event `ShouldBroadcast`
  (Slot/GateQueue) tidak menembak driver `log` (.env) saat test. Bukan perubahan kontrak.
- `2026-06-28`: Tambah `config/tas.php` (`no_show_grace_minutes`=30, `reminder_lead_minutes`=120),
  sumber nilai untuk `NoShowSweepJob` & `ScheduleAppointmentReminder`. Bukan perubahan kontrak/
  docs; nilai bisa di-override via env `TAS_NO_SHOW_GRACE_MINUTES`/`TAS_REMINDER_LEAD_MINUTES`.
- `2026-06-28`: Tambah `config/tas.php` → `rate_limits` (login=5, api=60, booking=10) + named limiter
  di `AppServiceProvider::configureRateLimiters()` + `throttle:*` di `routes/api.php`. Menutup kontrak
  CLAUDE.md §Hardening (rate limit). Override env `TAS_RL_LOGIN`/`TAS_RL_API`/`TAS_RL_BOOKING`. Bukan
  perubahan kontrak (justru memenuhinya); SETUP-GUIDE §10d ditandai respons 429.
- `2026-06-28`: Token abilities Sanctum **sengaja TIDAK ditegakkan** lewat middleware (lihat *Senior
  review*). Keputusan, bukan utang diam-diam: enforcement redundan selama login hanya cetak token
  full-scope. Tak ada perubahan kode rute selain throttle.
- `2026-06-28`: Backlog hardening (3 item) → `config/tas.php` tambah `no_show_chunk_size`=500,
  `idempotency.lock_seconds`=60, `idempotency.ttl_hours`=24 (env `TAS_NO_SHOW_CHUNK_SIZE`/
  `TAS_IDEMPOTENCY_LOCK_SECONDS`/`TAS_IDEMPOTENCY_TTL_HOURS`). `cancel` terima `version` opsional
  (backward compatible). Bukan perubahan kontrak. CODE-WALKTHROUGH §S.5 ditambah; SETUP-GUIDE
  endpoint cancel ditandai body opsional `version`.
- `2026-06-27`: Cache ketersediaan slot pakai **explicit-key `Cache::flexible` + `Cache::forget`**
  (di `SlotRepository`), BUKAN `Cache::tags` seperti contoh CLAUDE.md. Alasan: cache dev
  (`CACHE_STORE=database`) tidak mendukung tagging; explicit-key jalan di semua store. Saat
  pindah ke Redis, boleh refaktor ke tags. Bukan perubahan kontrak, hanya implementasi.

## Jebakan / catatan
- **Git belum di-init.** `is git repo: false`. Init dulu sebelum commit pertama (1 slice = 1 commit).
- **Horizon/Reverb butuh `ext-pcntl`/`ext-posix`** yang tidak ada di PHP Windows native →
  di-install dengan `--ignore-platform-req`. Jalankan keduanya di **Docker (Linux)**, bukan Windows.
  `composer install` di Windows juga perlu flag itu.
- **Realtime sisi server sudah ber-event & ber-test, tapi belum disiarkan sungguhan.** Event
  `ShouldBroadcast` + listener + channel auth + TOS seam sudah jadi. Yang BELUM: (1) jalankan
  `php artisan reverb:start` (Docker) + set `BROADCAST_CONNECTION=reverb` di `.env`; (2) endpoint
  auth channel privat — default guard `web`; untuk SPA Sanctum daftarkan `Broadcast::routes(['middleware'=>['auth:sanctum']])` saat wiring frontend; (3) sambungkan Laravel Echo di Vue.
  Push TOS masih `LoggingGateEventGateway` (placeholder) — swap binding saat ada TOS riil.
- **php.ini diubah** (mesin dev): `pdo_sqlite` + `sqlite3` di-enable (tadinya disabled) agar
  `.env` sqlite jalan. Driver DB lain yang aktif hanya mysql.
- **Frontend versi di-pin ke vite 6.** Proyek pakai `vite@^6` (kompat `laravel-vite-plugin@1.2`).
  Karena itu `vue-router@^4` (bukan 5, yang menuntut vite 7/8) & `@vitejs/plugin-vue@^5`.
  `npm install` paket baru: cek peer vs vite 6 dulu. TS 6 men-deprecate `baseUrl` → `tsconfig`
  pakai `paths` relatif tanpa `baseUrl`.
- Akun demo & password: lihat `docs/DUMMY-DATA.md` (semua `password`).
- Tests pakai sqlite `:memory:` (phpunit.xml) — aman, tidak menyentuh `database/database.sqlite`.
- Akun demo & password: lihat `docs/DUMMY-DATA.md`.

## Lingkungan (dev)
> Setup manual langkah-demi-langkah + troubleshooting: `docs/SETUP-GUIDE.md`.
> Penjelasan detail tiap kode yang sudah dibuat: `docs/CODE-WALKTHROUGH.md`.
```bash
php artisan migrate:fresh --seed
php artisan horizon        # queue (jalan di Docker/Linux — butuh ext-pcntl)
php artisan reverb:start   # websocket
composer test && composer analyse
# Frontend (SPA Vue di resources/js):
npm run dev               # Vite dev server (+ php artisan serve untuk shell)
npm run test:js           # Vitest
npm run type-check        # vue-tsc --noEmit
npm run build             # bundel produksi → public/build
```

---

### Checklist sebelum commit (anti-drift)
- [ ] Dokumen sumber kebenaran sudah diubah **sebelum** kode.
- [ ] Perubahan dicatat di *Changelog* di atas (apa & kenapa).
- [ ] Seeder ikut diupdate; `migrate:fresh --seed` hijau.
- [ ] `RolePermissionSeeder` & Policy cocok dengan matriks RBAC (BUSINESS-FLOW §1).
- [ ] `DemoSeeder` menyentuh semua status (BUSINESS-FLOW §2) & semua entitas (§4).
- [ ] `composer test / analyse / fix` bersih.
- [ ] Dokumen + kode + seeder + HANDOVER dalam **satu** PR.
