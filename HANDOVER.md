# HANDOVER.md — truck-appointment-system

> Status hidup proyek **antar-sesi & antar-perangkat**.
> Update di **akhir tiap sesi** Claude Code, dan **setiap kali** kontrak/dokumen/seeder berubah.
> Sesi atau perangkat baru: baca file ini **setelah** `CLAUDE.md`.
>
> Pembagian peran (jangan dicampur):
> `CLAUDE.md` = konstitusi (aturan tetap) · `docs/*` = detail rujukan · `HANDOVER.md` = jurnal (keadaan + keputusan terbaru).

---

## Status
- Update terakhir: `2026-07-07` · Sesi: **Senior review ronde 2** — fix idempotency scope per endpoint + guard mass-assignment (ADR-0004) + tolak booking ke window yang sudah berakhir.
- Branch: `main` (repo di-init + push ke GitHub `caesarovera/truck-appointment-system`).
- Build backend: `composer test` → ✅ **161 pass / 434 assert** · `composer analyse` → ✅ PHPStan lvl 8 · `composer fix` → ✅ Pint bersih.
- Build frontend: `npm run test:js` → ✅ **57 pass** · `npm run type-check` (vue-tsc) → ✅ · `npm run build` → ✅.

## Sudah selesai
- [x] **Admin CRUD master data (commit `0507d86`):** CRUD penuh `Terminal`/`Gate`/`TransportCompany`/`User`. BE: 12 Action (`Admin/`), 3 repo baru (`Terminal/Company/User` + extend `Gate`) ber-interface + bound di `AppServiceProvider`, 20 controller invokable (`Http/Controllers/Api/V1/Admin/`), 10 FormRequest (otorisasi `*.manage` + route-binding `instanceof`-safe utk PHPStan), 4 Resource. `EntityInUseException` (409 `entity_in_use`) guard hapus saat ada dependen (terminal←gate, gate←slot window, company←user/appointment, user←diri sendiri 422). `UserRepository`: filter role (Spatie `role()`), password hash-on-change, `fresh([...])` reload relasi setelah `syncRoles`. Permission baru `terminal.manage`/`gate.manage`/`company.manage` di `RolePermissionSeeder` (admin `→ *`). Route group `/api/v1/admin/*`. FE: `types/api.ts` (Admin* types), `api/admin.ts` (16 fn), `composables/useAdmin.ts` (`useTerminals`/`useAdminGates`/`useCompanies`/`useUsers`/`useAdminRefs`, invalidasi `['admin-*']`), `pages/AdminPage.vue` (4-tab), route `/admin`, kartu Dashboard "Master Data" gated `terminal.manage`. 34 test Admin (Pest) + verifikasi Vitest. CATATAN Pest: `$this->seed(...)` di closure `function(): void` (global `seed()` cuma di arrow-fn). Detail kode: `docs/CODE-WALKTHROUGH.md §V` (BE) + `docs/FRONTEND.md §4` (FE).
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

- [x] **Slice 9 — Planner kelola window (persona terakhir; PURE FE):** reuse endpoint yg sudah ada (tanpa BE baru): `GET /reports/utilization?gate=&date=` (semua window OPEN+CLOSED + counts + meta.summary), `POST /slots` (buka), `POST /slots/{id}/close`. FE: `api/slots.ts` tambah `fetchUtilization`/`openSlotWindow`/`closeSlotWindow`; `composables/usePlannerWindows` (`useUtilization` query `['utilization',gate,date]` + mutations open/close → invalidasi `['utilization']`+`['slots-availability']`); `pages/PlannerWindowsPage.vue` (gate dropdown+tanggal, form buka window [time→H:i:s], list window dgn status/terisi/no-show + tombol **Tutup** utk OPEN, ringkasan; map 409 `duplicate_slot_window`), route `/planner`, link Dashboard gated `slot.manage`. Types `SlotUtilization`/`UtilizationSummary`/`OpenWindowPayload`. +9 test Vitest. CATATAN test: form submit di jsdom → trigger `submit` pada `<form>`, bukan click tombol submit.

- [x] **Slice 8 — Dashboard gate-officer (persona baru):** BE `GET /api/v1/gate/queue?date=` (otorisasi `gate.process` + wajib `terminal_id`, else 403). `AppointmentRepository::queueForTerminal($terminalId,$date)`: status CONFIRMED/IN_PROGRESS, `whereHas('slotWindow', whereDate)` + `whereRelation('slotWindow.gate','terminal_id',…)` (dot-notation hindari nested-closure PHPStan), eager-load + gateIn/gateOut; **urut diserahkan ke FE** (hindari sortBy kolom relasi yg bikin larastan flip-flop nullsafe). `GateQueueController`+`GateQueueRequest`. 4 test. FE: `api/gate.ts` (fetchGateQueue + gateIn/gateOut kirim Idempotency-Key), `composables/useGateQueue` (query `['gate-queue']` + mutations useGateIn/useGateOut → invalidasi `['gate-queue']`), `pages/GateDashboardPage.vue` (list urut jam; tombol **Gate In** utk CONFIRMED, **Gate Out** utk IN_PROGRESS; map 409 invalid_state), route `/gate`, link Dashboard gated `gate.process`. +6 test Vitest.

- [x] **Slice 7 — Jadwal driver (persona baru):** BE additive: `SlotWindowResource` tambah `gate` (`whenLoaded('gate')` → reuse `GateResource`) supaya driver tahu gate tujuan; tak menambah query di endpoint lain (hanya `me/appointments/today` yang eager-load gate). FE: `fetchTodaySchedule()` + `useTodaySchedule` (query `['me-today']`), `pages/DriverSchedulePage.vue` (list urut jam, nama gate, move_type/kode/kontainer, badge status; state loading/error/empty), route `/today`, link Dashboard gated `appointment.read.self`. Type `SlotWindow.gate?`. +5 test (BE assertion gate.name; FE api today + page sort/empty/error). Endpoint `me/appointments/today` murni FE (sudah ada).

- [x] **Frontend slice 6 — reschedule:** `api/appointments.ts` tambah `rescheduleAppointment(id,slotWindowId,version)`; composable `useRescheduleAppointment` (mutation → invalidasi `['me-appointments']`+`['slots-availability']`). `components/RescheduleDialog.vue`: modal picker window target (reuse `useGates`+`useSlotAvailability`; default ke gate/tanggal window saat ini), pilih window (remaining>0) → submit kirim `slot_window_id`+`version`; map 409 `version_conflict`/`slot_unavailable`. `MyBookingsPage`: tombol "Pindah jadwal" (untuk BOOKED/CONFIRMED) buka dialog; sukses → tutup + list/availability auto-refresh. +5 test Vitest (dialog list/validasi/sukses/409 + page buka dialog). **Manajemen booking transporter lengkap** (list+cancel+reschedule).

- [x] **Frontend slice 5 — "Booking Saya" (list + cancel):** `api/appointments.ts` tambah `fetchMyAppointments(status?)` & `cancelAppointment(id,version)`; composable `useMyAppointments` (query `['me-appointments',status]`) + `useCancelAppointment` (mutation → invalidasi `['me-appointments']`+`['slots-availability']`). `pages/MyBookingsPage.vue`: filter status, list (kode/status/slot/truk/sopir/kontainer), tombol **Batalkan** hanya untuk BOOKED/CONFIRMED → konfirmasi 2-langkah → kirim `version` (optimistic lock); map 409 `version_conflict`/`invalid_state`. Route `/bookings` + link Dashboard (gated `appointment.write`). Types `Appointment`/`Container`. +8 test Vitest. CATATAN: **reschedule belum** (perlu picker window target) — slice berikutnya.

- [x] **Endpoint list booking transporter:** `GET /api/v1/me/appointments?status=` (otorisasi `appointment.read` + wajib `company_id`, else 403; filter status ber-`Rule::enum`). `AppointmentRepository::forCompany($companyId,?$status)` eager-load truck/driver/company/slotWindow.gate/containers, `orderByDesc('id')`. `MyAppointmentsController` + `MyAppointmentsRequest`, reuse `AppointmentResource` (sudah ada `version` untuk reschedule/cancel). 6 test. Unblock halaman "Booking Saya" (frontend berikutnya).

- [x] **Frontend slice 4 — form booking (jalur inti transporter):** `api/fleet.ts`+`api/appointments.ts`, `composables/useFleet.ts` (query `['me-fleet']`) + `useBookAppointment.ts` (mutation → `onSuccess` invalidasi `['slots-availability']`). `components/BookingForm.vue`: modal pilih truk/sopir (dari `GET /me/fleet`), move_type, container_no/iso_type/size; submit kirim Idempotency-Key (crypto.randomUUID) lewat `POST /appointments`; map error 409 `slot_unavailable`/`duplicate_booking` & fallback message; emit `booked`/`cancel`. `SlotAvailabilityPage`: tombol "Booking" per window tersedia (muncul bila `auth.can('appointment.write')`), buka modal, banner sukses tampilkan `booking_code`; mutation otomatis refresh sisa kuota. Types `Truck/Driver/Fleet/MoveType/BookAppointmentPayload/BookedAppointment`. +8 test Vitest (api fleet/appointments, BookingForm render/validasi/sukses/409, page tombol per-izin). CATATAN test: `setValue(number)` di `<select>` v-model.number bekerja; mock composable + `stubs:{BookingForm:true}` di page test.

- [x] **Frontend slice 3 — dropdown gate:** `api/gates.ts` (`fetchGates(terminal?)`) + `composables/useGates.ts` (`useQuery` key `['gates']`, staleTime 5 mnt) → `SlotAvailabilityPage` ganti input angka jadi `<select>` gate (placeholder "Pilih gate", opsi dari `GET /gates`). Type `Gate` di `types/api.ts`. +3 test Vitest (gates.api unwrap/filter + render opsi). Sisa: form booking (Task berikutnya).

- [x] **Read endpoints referensi (master data):** `GET /api/v1/gates` (opsional `?terminal=`, otorisasi `slot.read`, `GateResource`) + `GET /api/v1/me/fleet` (otorisasi `fleet.manage`, respons `{data:{trucks:[],drivers:[]}}` ber-scope `company_id` transporter; sopir = user company ber-role `driver`). Pola data layer konsisten: `GateRepositoryInterface`/`FleetRepositoryInterface` + impl, di-bind di `AppServiceProvider`; controller invokable + FormRequest (`ListGatesRequest`/`FleetRequest`); reuse `TruckResource`/`DriverResource`, baru `GateResource`. 8 test (`tests/Feature/Reference/`). Tujuan: unblock dropdown gate (ganti input angka) & form booking (pilih truk/sopir).

- [x] **Frontend slice 2 — Ketersediaan Slot (TanStack Query pertama):** `api/slots.ts` (`fetchAvailability(gate,date?)` unwrap `data`), `composables/useSlotAvailability.ts` (`useQuery` key `['slots-availability',gate,date]` reaktif, `enabled` saat gate>0), `pages/SlotAvailabilityPage.vue` (input gate angka + date default hari-ini; state prompt/loading/error/empty/list; kartu sisa-kuota + badge Tersedia/Penuh), route `/slots` (requiresAuth), link di Dashboard (muncul bila `auth.can('slot.read')`). Type `SlotWindow`/`SlotAvailabilityResponse` di `types/api.ts`. 6 test Vitest (api unwrap + page states; composable di-mock spt LoginPage). CATATAN: gate masih input angka — belum ada `GET /gates` untuk dropdown (lihat Langkah berikutnya).

- [x] **Frontend foundation + Auth/Login (slice 1):** SPA Vue 3 in-repo di `resources/js` (Vite + laravel-vite-plugin + Tailwind v4 yang sudah ada). Stack: Vue 3 (Composition + `<script setup>`) + TS + Pinia + Vue Router + TanStack Query + Axios. Build: `@vitejs/plugin-vue` di `vite.config.js` (input → `app.ts`, alias `@`→`resources/js`), `tsconfig.json` (extends `@vue/tsconfig`), `vitest.config.ts` (jsdom). Shell `resources/views/app.blade.php` + catch-all `routes/web.php` (`^(?!api).*$`). Kode: `api/client.ts` (axios `/api/v1` + Bearer interceptor + 401 handler, token di localStorage), `stores/auth.ts` (login/logout/fetchMe/restore/can/hasRole), `router/index.ts` (guard requiresAuth/guestOnly + restore sesi), `pages/LoginPage.vue` + `DashboardPage.vue`, `App.vue`, `app.ts`. 7 test Vitest (store + komponen login). CATATAN respons: `/login` flat (`{token,user}`), `/me` terbungkus `{data}` — store menangani keduanya.

- [x] **Hardening: rate limiting (slice keamanan):** named limiter di `AppServiceProvider::configureRateLimiters()` — `login` (anti brute-force, kunci `email|ip`), `api` (batas umum endpoint ber-auth, kunci user id/ip), `booking` (lebih ketat dari `api`, anti bot borong slot). Nilai di `config/tas.php` → `rate_limits` (env `TAS_RL_LOGIN`=5, `TAS_RL_API`=60, `TAS_RL_BOOKING`=10). Pasang di `routes/api.php`: `throttle:login` pada login, group ber-auth `throttle:api`, booking tambah `throttle:booking`. Menutup gap kontrak CLAUDE.md §Hardening (rate limit) yang sebelumnya KOSONG. 3 test (`tests/Feature/Hardening/`: login 429 + keyed-by-email, booking 429 per user). CACHE_STORE=array → limiter ter-reset per test (tak ada bleed antar-test).

## Senior review (2026-06-28) — temuan & keputusan
> Audit menyeluruh actions/repos/middleware/policy/migrasi/auth. Kesimpulan: foundation kuat (race handling, layering, idempotency benar). Temuan & status:
- **[FIXED] Tidak ada rate limiting** (melanggar kontrak) → slice di atas. Login brute-forceable & booking bisa di-borong bot — kini ber-throttle.
- **[DEFERRED — sengaja tidak diimplementasikan] Token abilities Sanctum tak ditegakkan.** Login mencetak token dgn abilities = SELURUH permission role, dan tak ada jalur token ber-scope sempit. Maka `abilities:` middleware tak pernah bisa menolak yang Policy/permission belum tolak → murni redundan + friksi (paksa semua test `actingAs` kirim `['*']`). Tegakkan NANTI saat aplikasi benar-benar menerbitkan token sempit (mis. token mobile read-only). Otorisasi saat ini tetap aman lewat Policy + FormRequest `can()`.
- **[FIXED] `version` optimistic lock tak konsisten:** `cancel` kini terima `version` opsional → bila dikirim, optimistic lock ditegakkan (`OptimisticLockException` 409 `version_conflict`); bila tidak, cancel tetap jalan (backward compatible). `CancelAppointmentRequest` + `CancelAppointmentAction::execute($appointment, ?int $expectedVersion)`. 4 test.
- **[FIXED] `dueForNoShow` muat semua kandidat ke memori:** kini dipindai `chunkById` (size dari `config('tas.no_show_chunk_size')` default 500) → hanya N baris di-hydrate per iterasi; hanya yang lewat grace ditahan. Test lintas-batas chunk (size=2, 5 due).
- **[FIXED] Idempotency lock TTL 10 dtk → 60 dtk** (`config('tas.idempotency.lock_seconds')`, ttl_hours juga) supaya lock tak kedaluwarsa di tengah request berat. Nilai header di-`hash('sha256')` jadi kunci cache (bounded lintas store, anti key-injection). 2 test (replay key panjang + contention 409).
- **[FIXED 2026-07-07 → ADR-0004] `$fillable` memuat `status`/`version`/`company_id`:** dulu aman hanya karena konvensi (Action set eksplisit) — kini kolom state/kuota dikeluarkan dari `$fillable` + `preventSilentlyDiscardingAttributes` aktif di non-prod. Lihat *Senior review ronde 2* di bawah.

- [x] **Backlog hardening (3 item dari senior review):** (1) optimistic `version` opsional di cancel, (2) `dueForNoShow` chunked scan (config `no_show_chunk_size`), (3) idempotency lock TTL 60s + key hashing (config `idempotency.lock_seconds`/`ttl_hours`). Semua via `config/tas.php` (tunable env). +8 test di `tests/Feature/{Appointments,Jobs,Hardening}`. Detail di *Senior review* (status [FIXED]).

## Senior review ronde 2 (2026-07-07) — temuan & keputusan
> Audit ulang seluruh Actions/Repositories/middleware/jobs/routes/seeder. Kesimpulan: fondasi tetap sehat; 2 temuan diperbaiki, 2 dicatat sebagai backlog sadar (bukan lupa).
- **[FIXED] Idempotency key tidak di-scope per endpoint (bug replay lintas operasi).**
  *Kenapa bug:* kunci cache lama = `user + sha256(header)` saja. User yang memakai nilai
  `Idempotency-Key` sama di dua endpoint berbeda (mis. booking lalu gate-in — realistis bila
  klien mem-buffer key atau salah reuse UUID) menerima **replay respons booking di gate-in**,
  dan operasi keduanya tidak pernah dieksekusi. Idempotency semestinya berlaku **per operasi**,
  bukan per nilai header global (bandingkan Stripe: key di-scope per endpoint).
  *Perbaikan:* `method|path` ikut di-hash → `idem:{user}:sha256(METHOD|path|key)`
  (`IdempotencyKey::cacheKey`). +1 test cross-endpoint di `tests/Feature/Hardening/IdempotencyTest.php`.
- **[FIXED → ADR-0004] Mass-assignment trap (temuan #4 ronde 1).** `status`/`version`/`company_id`
  keluar dari `Appointment::$fillable`; `booked_count`/`status` keluar dari `SlotWindow::$fillable`;
  `Model::preventSilentlyDiscardingAttributes(!prod)` diaktifkan supaya pelanggaran meledak di
  dev/test alih-alih dibuang diam-diam. `DemoSeeder` beralih ke `forceFill()` (bypass yang
  disengaja & terlihat). Kenapa-nya lengkap di `docs/adr/0004-guard-state-quota-columns.md`.
  +5 test `tests/Feature/Hardening/MassAssignmentGuardTest.php`.
- **[FIXED 2026-07-07 sesi lanjutan] Booking ke window yang sudah lewat tidak ditolak.**
  Dulu: window kemarin yang masih `OPEN` bisa di-book → `NoShowSweepJob` menandainya
  `NO_SHOW` ≤5 menit kemudian (absurd bagi transporter walau tak merusak data).
  Kini: `SlotWindow::hasEnded()` (basis `date+end_time` — sama dengan deadline no-show)
  ditolak `SlotUnavailableException::expired()` (409) di `BookAppointmentAction` &
  `RescheduleAppointmentAction`. **Keputusan produk:** window yang **sedang berjalan**
  (mulai tapi belum berakhir) tetap boleh di-book — truk masih bisa datang sebelum tutup.
  Ikutan: default `SlotWindowFactory` pindah ke `date=besok` ("valid by default") supaya
  test berjam-acak tidak flaky sore/malam; `dueForNoShow` refactor pakai `endsAt()`. +3 test.
- **[catatan, risiko ~nol] `booking_code` collision salah-lapor.** `booking_code` unik di DB;
  bila 8-char random tabrakan (≈1/2.8×10¹²), `UniqueConstraintViolationException`-nya akan
  tertangkap sebagai `DuplicateBookingException` (pesan "kontainer sudah dibooking" — menyesatkan).
  Tidak layak kode tambahan sekarang; cukup tahu saat debugging kasus aneh.
- **[dikembalikan] `docs/adr/README.md`** sempat ter-rename lokal jadi `README_ADR.md` (isi identik,
  belum di-commit) — dikembalikan: `README.md` adalah konvensi GitHub agar isi folder ter-render
  otomatis sebagai indeks.

## Sedang dikerjakan
- (kosong) — Senior review ronde 2 + guard window-berakhir selesai di checkpoint hijau (161 Pest / 57 Vitest).

## Langkah berikutnya (urut)
**Semua 4 persona UI + admin CRUD master data selesai** (transporter book/list/cancel/reschedule · driver jadwal · gate-officer antrian+gate-in/out · planner kelola window · admin terminal/gate/company/user). Berikutnya:
1. **Wiring realtime (Reverb + Echo)** — paling berdampak: kuota & antrian live. Server sudah ber-event/ber-test (`SlotAvailabilityChanged`, `GateQueueUpdated`). Perlu: `reverb:start` (Docker) + `BROADCAST_CONNECTION=reverb` + daftarkan `Broadcast::routes(['middleware'=>['auth:sanctum']])` + sambung Laravel Echo di SPA → ganti polling/invalidasi manual dgn push (invalidate query saat event masuk). Lihat Jebakan.
2. **Polish UI:** layout/nav bersama (saat ini link di Dashboard saja), loading skeleton, e2e happy-path.
3. **Opsional backend:** laporan utilisasi company-scoped untuk transporter; CRUD truk/sopir (fleet) untuk transporter (master data terminal/gate/company/user admin sudah ada); swap `GateEventGateway` ke TOS riil.
4. **Backlog hardening sisa:** token abilities sempit (ADR-0003, tegakkan saat ada token ber-scope sempit). Temuan `$fillable` [FIXED → ADR-0004]; tolak booking ke window lewat [FIXED 2026-07-07].

## Changelog kontrak / dokumen / seeder
> Catat tiap perubahan yang menyentuh CLAUDE.md, docs/*, atau seeder.
> Format: `tanggal: APA yang berubah → file mana yang ikut diupdate. Alasan.`
- `2026-07-07`: **Guard "window sudah berakhir" (409) + default `SlotWindowFactory` → besok.**
  Kode: `SlotWindow::endsAt()/hasEnded()`, `SlotUnavailableException::expired()`, guard di
  `Book/RescheduleAppointmentAction`, `dueForNoShow` reuse `endsAt()`. Factory default
  `date=besok`: window berjam-acak dengan `date=hari-ini` bisa sudah berakhir saat suite
  jalan sore/malam → flaky; test yang butuh hari-ini/masa-lalu memang sudah set eksplisit.
  Docs: `BUSINESS-FLOW §3.2b/§3.3` (aturan tolak), `CODE-WALKTHROUGH §J` (guard expired),
  hitungan test → **161 Pest / 434 assert**. Alasan: tutup backlog ronde 2 — booking yang
  langsung jadi NO_SHOW ≤5 menit itu jebakan UX; keputusan produk: window berjalan tetap
  boleh di-book. Tidak menyentuh CLAUDE.md/seeder.
- `2026-07-07`: **Senior review ronde 2 → ADR-0004 baru + DemoSeeder pakai `forceFill`.**
  Kode: `IdempotencyKey` (key kini scope `method|path` — fix replay lintas endpoint),
  `Appointment`/`SlotWindow` (`$fillable` diperketat), `AppServiceProvider`
  (`preventSilentlyDiscardingAttributes` non-prod), `DemoSeeder` (`forceFill` = bypass Action
  yang disengaja & eksplisit; perilaku seed TIDAK berubah, `migrate:fresh --seed` diverifikasi).
  Docs: `docs/adr/0004-guard-state-quota-columns.md` baru (+ tabel `docs/adr/README.md`),
  `CODE-WALKTHROUGH` (format key idempotency), hitungan test diselaraskan ke **158 Pest /
  57 Vitest** di README/ONBOARDING/SETUP-GUIDE/HANDOVER. Alasan: menutup temuan #4 ronde 1
  (kontrak §JANGAN kini ditegakkan framework, bukan konvensi) + bug idempotency nyata.
  Tidak menyentuh CLAUDE.md.
- `2026-06-28`: **`docs/ARCHITECTURE.md` + log `docs/adr/` baru (P0 dari senior review arsitektur).**
  ARCHITECTURE.md: pola (Layered = ADR + Action/Command + Repository + Ports&Adapters +
  Event-driven), peta folder aktual, aturan aliran dependensi, trace request booking
  antar-lapisan, ports & adapters, mirror frontend, trade-off. `docs/adr/` (format
  Status·Context·Decision·Consequences·Kapan ditinjau ulang): 0001 package-by-layer (+ trigger
  tinjau ulang: ≥3 sub-folder lintas lapisan & tim bertambah), 0002 repository-interface
  (Ports&Adapters), 0003 defer-token-abilities (angkat keputusan dari Senior review jadi ADR).
  README + ONBOARDING doc-map diupdate. Tujuan: cegah architecture drift di proyek multi-sesi.
  Tidak menyentuh CLAUDE.md. **Sisa rekomendasi senior:** P1 `$guarded` kolom status/version/
  company_id (ranjau mass-assignment) — lewat loop TDD, belum dikerjakan.
- `2026-06-28`: **`docs/ONBOARDING.md` baru** — panduan developer baru/junior (peta mental
  3-lapis + analogi restoran, glosarium domain & teknis, prasyarat skill, rencana minggu
  pertama, tahapan baca + self-check, bedah golden path booking, resep baca slice, loop TDD,
  cheat-sheet jebakan, perintah harian, routing per tugas, latihan, FAQ). `README` diupdate:
  onboarding menunjuk ke ONBOARDING.md + masuk peta dokumentasi. Tidak menyentuh CLAUDE.md.
- `2026-06-28`: **Admin CRUD + dokumentasi diselaraskan** (commit `0507d86` + sesi docs).
  Kode: lihat *Sudah selesai* → Admin CRUD. Docs yang diupdate agar konsisten jadi handbook:
  `PRD §3` (admin master-data CRUD → IN scope), `BUSINESS-FLOW §1` (permission `*.manage`
  admin-only + baris matriks CRUD master data), `SETUP-GUIDE §10d` (tabel 20 endpoint
  `/admin/*` + §13 peta file + status akhir 152/57), `CODE-WALKTHROUGH` (§V baru: admin CRUD,
  `EntityInUseException`, password/role sync, jebakan PHPStan route-binding), `FRONTEND §4`
  (AdminPage 4-tab + `useAdmin`/`useAdminRefs`), `README` (onboarding order + status + 152/57),
  `HANDOVER` (status + langkah berikutnya). Hitungan test diselaraskan ke **152 Pest / 57
  Vitest** (sebelumnya tercatat 118 di HANDOVER). Tidak menyentuh CLAUDE.md (kontrak tetap).
- `2026-06-28`: **Dokumentasi frontend dibuat** → `docs/FRONTEND.md` baru (arsitektur SPA,
  pola TanStack Query, tiap halaman/komponen + *kenapa*, pola test). `CODE-WALKTHROUGH.md`:
  TOC diperbaiki (tambah S/T yang sempat hilang) + §U baru (read endpoints persona:
  `/me/appointments` & `/gate/queue`) + pointer ke FRONTEND. `README.md`: doc-map +
  stack frontend + langkah jalankan SPA. `SETUP-GUIDE.md §9a`: pointer FRONTEND.
  Alasan: SPA (≈30 file) sebelumnya tak terdokumentasi; CODE-WALKTHROUGH eksplisit
  backend-only. Tidak menyentuh CLAUDE.md/PRD/BUSINESS-FLOW/DUMMY-DATA (domain tak berubah).
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
