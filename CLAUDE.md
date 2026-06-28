# CLAUDE.md — **truck-appointment-system**

Architecture contract. Baca ini sebelum menulis kode apa pun. File ini di-load tiap sesi → jaga tetap ramping. Detail panjang (state machine, ERD, business flow, RBAC matrix) ada di `/docs` — jangan tulis ulang di sini.

## Domain (1 paragraf)

Sistem penjadwalan kedatangan truk ke terminal peti kemas. Perusahaan angkutan **booking slot** gate per jendela waktu yang kuotanya terbatas. Truk datang di slotnya → petugas gate melakukan **gate-in**, proses bongkar/muat, lalu **gate-out**. Tujuan: meratakan antrian, cegah penumpukan, audit trail penuh. Move type: `DELIVERY` (ambil kontainer impor) & `RECEIVAL` (drop kontainer ekspor).

**Glosarium:** Slot Window = jendela waktu + kuota per gate. Appointment = 1 booking truk. Gate Transaction = event gate-in/gate-out. No-show = tidak datang sampai grace period habis.

Detail lengkap ada di satu file: `docs/BUSINESS-FLOW.md` — **§1** RBAC & akun · **§2** state machine · **§3** alur proses · **§4** ERD. (Tidak ada file `RBAC.md`/`STATE-MACHINE.md` terpisah; semua jadi section di sini.) Kenapa & batas scope MVP: `docs/PRD.md`.

## Identitas

API-first decoupled. Laravel 12 = REST API murni. Vue 3 = SPA terpisah. Bukan Inertia, bukan monolith.

## Stack

* Backend: Laravel 12, PHP 8.3+ (`declare(strict_types=1)` wajib)
* Auth: Sanctum (token + scopes) — scope per role, lihat `docs/BUSINESS-FLOW.md §1`
* Queue/Cache/Session: Redis 7 + Horizon
* Realtime: Reverb (WebSocket) + Laravel Echo — channel `slot.{gateId}` (kuota live) & `gate.queue.{terminalId}`
* RBAC: Spatie Permission · Audit: Spatie Activity Log · DTO: Spatie Laravel Data
* Frontend: Vue 3 (Composition API) + TypeScript + Pinia + Vue Router + TanStack Query + Axios
* Test: Pest (backend) + Vitest (frontend) · Analysis: PHPStan level 8
* Infra: Docker Compose · CI: GitHub Actions

## Aturan layer (non-negotiable)

```
HTTP layer     → Controllers (invokable), Form Requests, API Resources. TANPA business logic.
Business layer → Actions (1 tugas), Services, Events/Listeners, DTOs.
Data layer     → Repository (interface + Eloquent impl), Models, Jobs, Notifications.
```

* Controller hanya memanggil Action/Service lalu kembalikan Resource. Tidak ada query/logika di controller.
* Setiap Action 1 tanggung jawab. Nama = kata kerja: `BookAppointmentAction`, `RescheduleAppointmentAction`, `CancelAppointmentAction`, `GateInAction`, `GateOutAction`, `MarkNoShowAction`, `OpenSlotWindowAction`, `CloseSlotWindowAction`.
* Side-effect (email, push, cache invalidate, broadcast) lewat Event/Listener — bukan dipanggil langsung di Action. Event inti: `SlotWindowOpened`, `AppointmentBooked`, `AppointmentRescheduled`, `AppointmentCancelled`, `TruckGatedIn`, `TruckGatedOut`, `AppointmentNoShow`.
* Repository selalu punya interface di `Contracts/`, di-bind di `AppServiceProvider`: `SlotRepositoryInterface`, `AppointmentRepositoryInterface`.
* Semua endpoint di bawah `/api/v1/`. Versi baru = folder baru, jangan mutasi v1.
* Output API selalu lewat Resource. Input selalu lewat Form Request + DTO. Dilarang `Model::create($request->all())`.

## Hardening (wajib, dicek tiap PR) — anchor ke TAS

### Idempotency
* `POST /api/v1/appointments` (booking) & gate-in/gate-out → middleware `Idempotency-Key` + `Cache::lock`. Mobile sopir/transporter sering double-tap.
* Unique constraint DB sebagai pertahanan terakhir: `(slot_window_id, container_no)` unik per appointment aktif.
* `ProcessGateEventJob` (efek eksternal ke TOS terminal) → cek state dulu: `if ($appointment->isGatedIn()) return;`.

### N+1
* `Model::preventLazyLoading(! app()->isProduction())` di `AppServiceProvider::boot()`.
* `AppointmentResource` selalu butuh `truck`, `driver`, `company`, `slotWindow`, `containers` → `->with()` di repository.
* Resource pakai `whenLoaded()` / `whenCounted()`. `SlotWindowResource->withCount('appointments')` untuk sisa kuota.

### Race condition (INTI proyek ini)
* Booking slot: kuota terakhir diperebutkan 2 transporter → `SlotWindow::lockForUpdate()` di dalam `DB::transaction()`. Tolak jika `bookedCount >= capacity`.
* Reschedule appointment → optimistic lock kolom `version` (transporter & planner bisa edit bersamaan).
* Counter `booked_count` per slot window → naik/turun di dalam transaksi yang sama dengan create/cancel appointment.

### Cache
* `Cache::tags(['slot', "gate:{$gateId}"])` untuk invalidasi selektif saat booking/cancel. Jangan `Cache::flush()`.
* Endpoint publik ketersediaan slot (`GET /api/v1/slots/availability`) di-poll banyak transporter → `Cache::flexible($key, [10, 30], $cb)` (anti-stampede).

### Queue
* Job: `implements ShouldQueue, ShouldBeUnique`. Set `$tries`, `$backoff`, `uniqueId()`, `failed()`.
* `AppointmentReminderJob` (H-2 jam) → `uniqueId()` = appointment id.
* `NoShowSweepJob` (cron tiap 5 menit) → `WithoutOverlapping`, tandai appointment lewat grace period jadi `NO_SHOW` + balikin kuota.
* `ProcessGateEventJob` → `WithoutOverlapping` per appointment.

### Transaction & rate limit
* `DB::transaction($cb, attempts: 3)` untuk auto-retry deadlock pada booking & gate event.
* Jangan dispatch job / HTTP call ke TOS di dalam `DB::transaction()` — commit dulu baru dispatch (lewat `AppointmentBooked` listener).
* Rate limiter by `user()?->id ?: ip()`. Endpoint booking lebih ketat (anti bot borong slot).

## Perintah

```bash
composer test            # pest --parallel
composer analyse         # phpstan level 8
composer fix             # pint
php artisan migrate:fresh --seed   # reset + demo data (lihat docs/DUMMY-DATA.md)
php artisan horizon      # queue worker (dev)
php artisan reverb:start # websocket (dev)
npm run dev | test | build
```

## Cara Eksekusi — Vibe Coding di Claude Code

Alur: brainstorm di mobile (claude.ai) → eksekusi presisi di Claude Code. CLAUDE.md ini **kontrak**; kalau "vibe" bentrok dengan kontrak, kontrak yang menang.

**Tiap sesi dimulai dengan:**
1. Baca `CLAUDE.md` + doc relevan: `docs/BUSINESS-FLOW.md` (domain, state machine, RBAC) saat menyentuh status/akses; `docs/DUMMY-DATA.md` saat butuh data uji.
2. **Plan dulu, kode belakangan.** Sebutkan file yang akan dibuat/diubah + test yang ditulis. Tunggu OK bila lingkupnya besar.
3. Kerjakan **1 vertical slice per sesi** (1 Action + test + wiring), bukan seluruh fitur sekaligus. Konteks tetap ramping.

**Urutan build (sekali di awal):**
1. Scaffold Laravel 12 + Docker Compose + Pest + PHPStan(8) + Pint + GitHub Actions.
2. Install paket inti (konfirmasi via `composer.json` dulu): Sanctum, Horizon, Reverb, Spatie Permission/Activity Log/Laravel Data.
3. Migrasi + Model + Factory dari `BUSINESS-FLOW.md §4`, lalu jalankan seeder yang sudah ada.
4. Contracts + Repository (`SlotRepositoryInterface`, `AppointmentRepositoryInterface`) → bind di `AppServiceProvider`.
5. DTO (Laravel Data) + Form Request + API Resource.
6. **Mulai dari jantung domain:** slot availability → `BookAppointmentAction` (di sinilah race condition diuji). Baru gate-in/out, reschedule, cancel, lalu `NoShowSweepJob`.
7. Event/Listener + Job + Policy + channel Reverb.
8. Frontend Vue belakangan, setelah API stabil & ber-test.

**Loop per Action (TDD, wajib — lihat skill laravel-tdd):**
```
1. Tulis Pest test DULU: happy path + edge (kuota penuh → 409, double-submit + Idempotency-Key, optimistic clash version).
2. Implement Action sampai hijau. Hormati layer & state machine.
3. composer test && composer analyse && composer fix
4. Commit kecil, pesan jelas (1 slice = 1 commit).
```

**Agent WAJIB stop & tanya** sebelum: tambah paket, ubah migrasi yang sudah jalan, menyentuh apa pun di bagian JANGAN, atau melebar dari plan yang disepakati.

**Definition of Done per fitur:** Pest hijau (+ Vitest bila ada UI) · PHPStan level 8 lolos · Pint bersih · input lewat Form Request+DTO, output lewat Resource · hardening relevan terpasang (cek checklist Hardening di atas) · perubahan status tercatat di Activity Log.

## JANGAN

* Jangan tambah package tanpa konfirmasi (cek `composer.json` dulu).
* Jangan ubah migrasi yang sudah jalan di prod — buat migrasi baru.
* Jangan taruh secret di kode. `.env` only.
* Jangan bikin file baru kalau bisa edit yang ada.
* Jangan skip test saat ubah Action/Repository.
* Jangan kurangi/tambah kuota slot langsung lewat update Model — selalu lewat Action ber-lock.
* Jangan ubah status appointment lewat `update()` bebas — hormati state machine di `docs/BUSINESS-FLOW.md §2`.
