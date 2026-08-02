# HANDOVER.md — truck-appointment-system

> Status hidup proyek **antar-sesi & antar-perangkat**.
> Update di **akhir tiap sesi** Claude Code, dan **setiap kali** kontrak/dokumen/seeder berubah.
> Sesi atau perangkat baru: baca file ini **setelah** `CLAUDE.md`.
>
> Pembagian peran (jangan dicampur):
> `CLAUDE.md` = konstitusi (aturan tetap) · `docs/*` = detail rujukan · `HANDOVER.md` = jurnal (keadaan + keputusan terbaru).

---

## Status
- Update terakhir: `2026-08-02` · Sesi: **Senior review ronde 4 (audit tasklist) + fix P1 toleransi jendela gate-in (loop TDD).** Audit menemukan 7 janji kontrak yang tak pernah dibangun — yang terberat ditutup sesi ini lewat test-dulu: test merah dengan **200 alih-alih 409**, baru guard-nya dipasang. Sisanya tercatat di *Senior review ronde 4* di bawah, **bukan** dianggap selesai. Semua gerbang dijalankan ulang & hijau (angka di bawah = hasil run nyata, bukan salinan).
- Sesi sebelumnya: `2026-07-27` — **fix bug P1 `driver_invalid_role` (loop TDD) + ADR-0006 "sopir admin-only" + koreksi PRD §3.** Bug dari *Senior review ronde 3* ditutup lewat test-dulu: test merah dengan **201 alih-alih 422**, baru guard-nya dipasang.
- Sesi sebelumnya: `2026-07-26` — **Senior review ronde 3**: audit kode + dokumen, semua gerbang diverifikasi sendiri; 1 bug (P1) + drift dokumen P2/P3 dibereskan. Temuan lengkap: *Senior review ronde 3* di bawah.
- Sesi sebelumnya: `2026-07-25` — **CRUD armada truk transporter (`/me/trucks`) + fix penegakan status `INACTIVE`**. Slice fleet CRUD (yang sebelumnya menggantung uncommitted) ditutup: BE 3 Action + DTO + 2 FormRequest + 4 controller + repo, FE `MyTrucksPage`/`useTrucks`, route `/trucks`. **Bug ditemukan saat review & diperbaiki:** `TruckStatus::INACTIVE` tidak ditegakkan di mana pun — truk nonaktif tetap berhasil di-book (201).
- Branch: `main` (repo di-init + push ke GitHub `caesarovera/truck-appointment-system`).
- Build backend: `composer test` → ✅ **201 pass / 519 assert** · `composer analyse` → ✅ PHPStan lvl 8 (208 file, 0 error) · `composer fix` → ✅ Pint bersih.
- Build frontend: `npm run test:js` → ✅ **87 pass** · `npm run type-check` (vue-tsc) → ✅ · `npm run build` → ✅.
- **CI TERVERIFIKASI hijau** — run [30228335447](https://github.com/caesarovera/truck-appointment-system/actions/runs/30228335447) di commit `f6495a0`: **kedua job + SEMUA step** sukses (backend Pint·PHPStan·Pest, frontend Vitest·vue-tsc·build). Run sebelumnya `5976732` juga sukses. Yang paling berarti: step **Install dependensi** backend hijau — itu `composer install` **tanpa** `--ignore-platform-req`, satu-satunya hal yang mesin dev Windows secara struktural **tak bisa** uji sendiri.
- Paket FE baru: (tak ada sesi ini) · sebelumnya `laravel-echo@^2` + `pusher-js@^8`.

## Sudah selesai
- [x] **Toleransi jendela waktu gate-in (2026-08-02).** Menutup temuan P1 terberat
  *Senior review ronde 4*: `GateInAction` hanya bertanya "status CONFIRMED?" dan **tak pernah
  melihat jam** — appointment minggu depan bisa gate-in hari ini. `config/tas.php` bahkan tak
  punya kunci toleransi sama sekali, padahal `BUSINESS-FLOW §2`/`§3.5` dan `PRD §4` menjanjikan
  "toleransi early/late dari config" sejak awal. **Kelas bug ketiga yang identik** setelah
  `INACTIVE` & `driver_invalid_role`: aturan ada di dokumen, dipagari di tempat lain, tak pernah
  ditegakkan di Action. Dampaknya bukan kosmetik — kuota jam 08:00 bisa dipakai truk yang muncul
  jam 20:00, jadi laporan utilisasi per window melaporkan yang tak pernah terjadi, dan meratakan
  kedatangan (alasan TAS ada) berhenti berlaku. **Fix:** `config/tas.php → gate_in`
  (`early_minutes`/`late_minutes`, default 30/30, env `TAS_GATE_IN_EARLY_MINUTES`/`_LATE_`),
  `SlotWindow::startsAt()` (cermin `endsAt()` — satu sumber kebenaran `date+time` dengan deadline
  no-show & guard booking), `GateInWindowException` (409 `gate_in_too_early`/`gate_in_too_late`,
  **terpisah** dari `invalid_state` karena di sini status-nya sah, jamnya yang salah), guard di
  `GateInAction` **setelah** cek idempoten & state. **Urutan guard dikunci test:** idempoten →
  state → waktu. Truk yang sudah di dalam tetap 200 walau retry-nya telat berjam-jam (double-tap
  petugas gate itu normal); `CANCELLED` yang datang kepagian tetap `invalid_state`. **Kenapa tak
  cukup `NoShowSweepJob`:** sweep itu eventual (5 menit) dan diam total kalau worker mati,
  sementara gate-in tetap melayani → guard harus sinkron. Default `late` sengaja sama dengan
  `no_show_grace_minutes` (tenggat sama dari dua sisi — ubah bersamaan; alasannya di komentar
  config). Ditulis lewat loop TDD: merah dulu dengan bukti **200 alih-alih 409**. **Ikutan:**
  `SlotWindowFactory::ongoing()` — default factory "besok" (dibuat agar valid di-book) justru
  **terlalu awal untuk gate-in**, jadi semua test gate-in lama akan merah; diperbaiki di
  default factory, bukan ditambal per-test (pola sama dengan `UserFactory::driver()`). State-nya
  menjepit jam di hari yang sama supaya tak flaky dekat tengah malam, dan `GateInTest` membekukan
  jam via `travelTo`. +7 test → **201 Pest / 519 assert**; Vitest tetap **87** (FE tak berubah:
  `extractError` di `GateDashboardPage` sudah fallback `data.message`, pola sama `truck_inactive`).
  Detail: `CODE-WALKTHROUGH §X`, `BUSINESS-FLOW §2`/`§3.5`.
- [x] **Penegakan role sopir saat booking + ADR-0006 (2026-07-27).** Menutup bug P1 dari
  *Senior review ronde 3*: `driver_id` menerima user mana pun se-company — termasuk akun
  transporter sendiri — dan booking-nya **berhasil 201**. Kelas bug yang **sama persis**
  dengan `INACTIVE` sesi sebelumnya: repository menyaring, Action tidak. Fix: guard ketiga
  `->role('driver','api')->exists()` di `BookAppointmentAction`, **setelah** cek kepemilikan
  (kalau dibalik, beda pesan error membocorkan keberadaan user company lain) →
  `InvalidDriverException` (422 `driver_invalid_role`). Ditulis lewat loop TDD: test merah
  dulu dengan bukti `201 alih-alih 422`, baru guard-nya dipasang. **Akar kenapa lolos lama:**
  3 helper booking di test membuat "sopir" **tanpa role** — kini semua memakai
  `UserFactory::driver()` (state baru, `Role::findOrCreate` jadi jalan dengan/tanpa seeder).
  `BookingRateLimitTest` sempat ikut merah — itu bukti guard-nya menggigit, bukan gangguan.
  +3 test → **194 Pest / 501 assert**; Vitest tetap **87** (FE tak berubah: pesan server lolos
  lewat fallback `data.message` di `BookingForm`, pola sama dengan `truck_inactive`).
  **Keputusan produk menyertainya:** `ADR-0006` — **CRUD sopir tidak dibangun di MVP**, sopir
  dikelola admin lewat Admin User CRUD; transporter tetap melihat sopirnya via `/me/fleet`.
  `PRD §3` dikoreksi dua sisi (IN dipersempit, OUT ditambah) karena aturan PRD "di luar IN
  tidak dikerjakan tanpa memperbarui PRD" berlaku **dua arah**. Detail: `CODE-WALKTHROUGH §W.5`,
  `BUSINESS-FLOW §3.2`.
- [x] **CI GitHub Actions (2026-07-25) — menutup klaim kontrak yang selama ini tidak benar.**
  `CLAUDE.md` mencantumkan "CI: GitHub Actions" sejak awal, tapi **`.github/` tidak pernah ada**.
  Akibatnya nyata: 191 Pest + 87 Vitest hanya jalan bila ada yang ingat mengetik perintahnya —
  di proyek lintas-sesi/lintas-perangkat seperti ini, gerbang kualitas praktis tak menjaga apa pun
  saat `push`. `.github/workflows/ci.yml` = **2 job paralel**: `backend` (`composer lint`→`analyse`
  →`test`) & `frontend` (`npm run test:js`→`type-check`→`build`). **Prinsip: CI memanggil perintah
  yang SAMA PERSIS dengan lokal** — kalau CI punya rangkaian sendiri, "hijau di CI" dan "hijau di
  laptop" berhenti berarti sama. Konsekuensi yang harus diingat: **menambah gerbang baru di lokal
  tanpa menambahkannya ke workflow = gerbang itu tak berlaku saat push.** Detail keputusan lain:
  `if: !cancelled()` (satu push melaporkan SEMUA gerbang rusak, bukan berhenti di yang pertama);
  **tanpa `--ignore-platform-req`** (flag itu kebutuhan Windows karena Horizon minta `ext-pcntl`/
  `ext-posix`; di Linux ada, jadi CI memverifikasi dependensi apa adanya); **tanpa service container
  DB** (`phpunit.xml` memaksa sqlite `:memory:`); versi dipatok menyamai mesin dev (PHP 8.3, Node 22).
  Cara pakai + tabel gejala→sebab saat CI merah: `docs/SETUP-GUIDE.md §14`. Alasan CI didahulukan
  & **e2e ditunda**: `docs/adr/0005-ci-github-actions.md`.
  **TERVERIFIKASI hijau** (run [30164709068](https://github.com/caesarovera/truck-appointment-system/actions/runs/30164709068),
  commit `f7f4641`): **kedua** job benar-benar jalan — `backend` 32 dtk & `frontend` 30 dtk, paralel
  → **37 dtk** total. Angka itu berguna sebagai basis: kalau suatu saat melonjak jauh, curigai cache
  Composer/npm meleset (key-nya `composer.lock`/`package-lock.json`).
- [x] **Polish UI — skeleton loading (2026-07-25):** `components/SkeletonRows.vue` (props `rows`,
  `label`) menggantikan teks `Memuat…` di **11 titik / 8 halaman + `RescheduleDialog`**. Alasan
  utamanya bukan estetika: placeholder setinggi konten asli menghapus **layout shift** saat data
  masuk. **Aksesibilitas dijaga:** balok abu-abu tak mengabarkan apa pun ke pembaca layar, jadi teks
  lama dipindah ke `<span class="sr-only">` di dalam `role="status" aria-busy="true"` dan balok
  visualnya `aria-hidden`. Efek samping enak: test lama yang meng-assert teks `Memuat laporan`
  **tetap hijau tanpa diubah** — bukti integrasi skeleton↔halaman sekaligus.
  **Sengaja TIDAK diubah:** indikator `isFetching` di `/slots` (refetch latar saat data lama masih
  tampil) — menggantinya dengan skeleton justru menyembunyikan data yang sudah benar. Skeleton hanya
  untuk `isLoading` (belum ada data sama sekali). +3 Vitest → **87 Vitest**. Detail: `docs/FRONTEND.md`
  (§4 → *Loading state*). **Sisa dari task Polish UI: e2e happy-path** — belum, butuh paket baru
  (lihat *Langkah berikutnya* #2).
- [x] **CRUD armada truk transporter + penegakan `INACTIVE` (2026-07-25):** transporter kelola
  armadanya sendiri tanpa lewat admin. **BE:** `Create/Update/DeleteTruckAction`, `TruckData` (Spatie
  Data), `UpsertTruckRequest`/`DeleteTruckRequest`, 4 controller invokable di `Fleet/`, `FleetRepository`
  +`createTruck`/`updateTruck`/`deleteTruck`, `EntityInUseException::truck()`, relasi `Truck::appointments()`,
  4 route `/me/trucks` (GET/POST/PATCH/DELETE). **company_id selalu dari user login, tak pernah dari body** —
  itu beda inti dengan Admin CRUD yang lintas-company. Plat unik **per company** (`Rule::unique(...)->where('company_id')`)
  bukan global: dua perusahaan boleh punya plat sama. Hapus truk ber-riwayat appointment → **409
  `entity_in_use`** (FK `RESTRICT`; hard-delete merusak audit trail). **FE:** `api/trucks.ts`,
  `useTrucks.ts` (mutasi invalidate `me-trucks` **dan** `me-fleet` → dropdown booking ikut segar),
  `MyTrucksPage.vue` (form create/edit dipakai bersama, hapus konfirmasi 2-langkah, map 409/422),
  route `/trucks` + link nav/dashboard gated `fleet.manage`.
  **[BUG DIPERBAIKI] `INACTIVE` cuma label.** Ditemukan saat review slice ini: `BookAppointmentAction`
  hanya cek kepemilikan, `trucksForCompany()` tak menyaring status → truk INACTIVE **muncul di dropdown
  booking dan berhasil di-book (test membuktikan 201)**. Padahal pesan 409 hapus-truk secara eksplisit
  menyuruh "nonaktifkan saja". Fix: `InactiveTruckException` (422 `truck_inactive`) + guard di
  `BookAppointmentAction` **setelah** cek kepemilikan (kalau dibalik, beda pesan error membocorkan
  keberadaan & kondisi truk company lain) + `trucksForCompany($companyId, ?TruckStatus $status = null)`
  → `/me/fleet` ACTIVE saja, `/me/trucks` semua status (biar bisa diaktifkan lagi). Menyaring dropdown
  saja tak cukup: truk bisa dinonaktifkan saat form booking terbuka → Action tetap menolak; pesan server
  lolos ke UI lewat fallback `data.message` di `BookingForm` (tak perlu mapping baru).
  **Test:** 13 `tests/Feature/Fleet/TruckCrudTest.php` + 3 penegakan INACTIVE (2 di `Booking/`, salah satunya
  **mengunci urutan guard**; 1 di `Reference/MyFleetTest`) + 2 file Vitest → **191 Pest / 84 Vitest**.
  Detail kode: `docs/CODE-WALKTHROUGH.md §W`; aturan bisnis: `docs/BUSINESS-FLOW.md §3.2`.
- [x] **Realtime wiring — Reverb + Echo (2026-07-25):** Kuota slot & antrian gate kini live.
  **Slice A (BE, TDD):** `bootstrap/app.php` — `channels:` dipindah dari `withRouting()` ke
  `->withBroadcasting(channels, ['middleware'=>['auth:sanctum']])`. **Kenapa bug:** framework
  mendaftarkan `/broadcasting/auth` dgn guard **`web`** (session cookie) saat channels lewat
  `withRouting(channels:)`; SPA pakai **Bearer token Sanctum** → auth channel privat PASTI gagal.
  **Pindah bukan tambah** (kalau dua-duanya, channels ter-`require` 2x + route ganda). 5 test
  `tests/Feature/Realtime/BroadcastAuthTest.php` (unauth→401; slot.read→200; tanpa izin→403;
  gate-officer terminal sendiri→200, terminal lain→403). **Jebakan test (didokumentasikan di
  file):** `Broadcast::channel()` mendaftar di driver DEFAULT saat boot (`null` di phpunit);
  meng-`config(['broadcasting.default'=>'pusher'])` sesudah boot → driver pusher **kosong channel**
  → semua 403. Fix: `require base_path('routes/channels.php')` ulang di helper `usePusherBroadcaster()`
  (idempoten). channels.php **tidak diubah** — callback RBAC-nya sudah benar.
  **Slice B (FE):** `laravel-echo`+`pusher-js`. `echo.ts` = singleton Echo (init setelah token ada,
  disconnect saat logout, **no-op bila `VITE_REVERB_APP_KEY` kosong** → degradasi mulus: app == polling
  biasa bila Reverb mati). `composables/useRealtime.ts` (`useSlotRealtime`/`useGateQueueRealtime`):
  subscribe reaktif `slot.{gateId}`/`gate.queue.{terminalId}`, event masuk → **invalidateQueries**
  (BUKAN tambal cache — payload broadcast subset; API tetap sumber kebenaran), `leave()` saat param
  ganti/unmount (cegah channel bocor). `.listen('.slot.availability.changed')` — **titik depan wajib**
  (broadcastAs, bukan kelas PHP). Wiring: `SlotAvailabilityPage` (`useSlotRealtime(gate)`),
  `GateDashboardPage` (`useGateQueueRealtime(auth.user.terminal_id)`), siklus connect/disconnect di
  `app.ts` (watch `auth.isAuthenticated`). +7 Vitest `tests/js/useRealtime.test.ts` (Echo di-mock;
  subscribe→invalidate, ganti gate→leave+rejoin, unmount→leave, Echo null→no-op). **Verifikasi live
  (server):** Reverb `reverb:start` bind port di Windows native; dispatch event→driver reverb: dead-port
  9999 → `BroadcastException` cURL 7, live-port 8080 → sukses (Reverb ingest). **BELUM diverifikasi
  end-to-end:** frame WebSocket sampai ke browser (butuh 2 browser + `BROADCAST_CONNECTION=reverb`).
- [x] **FE: nav/layout bersama (2026-07-08):** `components/AppNav.vue` (navbar: brand → `/`, link ber-gate **permission** — bukan nama role, cermin otorisasi server; `/reports` juga cek `company_id`; nama user + tombol Keluar) + `components/AppLayout.vue` (`AppNav` + `RouterView`) sebagai **parent route** semua halaman ber-auth di `router/index.ts`. `requiresAuth` cukup di parent (Vue Router menggabungkan meta parent→child) → halaman baru otomatis terlindungi tanpa mengulang meta. Dashboard disederhanakan (header+logout pindah ke navbar; kartu pintu-masuk dipertahankan); link "← Dashboard" di 6 halaman dihapus (redundan dgn navbar). 4 test `tests/js/AppNav.test.ts` (gating transporter, `/reports` tersembunyi utk planner tanpa company, logout→redirect, nama user) → **67 Vitest**. CATATAN test: stub `RouterLink: true` TIDAK merender slot — pakai `RouterLinkStub` dari `@vue/test-utils` bila perlu assert teks link.
- [x] **FE: halaman Laporan Perusahaan `/reports` (2026-07-08):** UI untuk `GET /me/reports/utilization` (slice BE di bawah). `api/slots.ts` `fetchMyUtilization` (unwrap `data`+`meta.summary`, bentuk sama dgn `fetchUtilization`); `composables/useMyUtilization.ts` (useQuery key `['my-utilization',gate,date]` — **sengaja terpisah** dari `['utilization']` planner: beda scope, tak boleh saling menimpa cache; read-only → tanpa mutation/invalidation); `pages/MyUtilizationPage.vue` (dropdown gate `useGates` + tanggal; state prompt/loading/error/empty; per window "Milik Anda: selesai/no-show/batal/aktif" + konteks gate "terisi X/kapasitas" dipisah visual supaya angka global tak terbaca sebagai milik company; ringkasan `meta.summary`); route `/reports`; link Dashboard "Laporan Perusahaan" gated `can('report.read') && company_id != null` (planner/admin punya `report.read` tapi tanpa company → link tak muncul, mereka pakai `/planner`). +6 Vitest (5 page states + 1 api unwrap) → **63 Vitest**; vue-tsc & build hijau.
- [x] **Utilisasi company-scoped transporter (2026-07-08):** `GET /api/v1/me/reports/utilization?gate=&date=` — menutup janji matriks RBAC §1 ("Laporan utilisasi → transporter: company sendiri") yang selama ini baru desain. `SlotRepository::utilization()` dapat param opsional `?int $companyId` — filter `where('company_id')` diterapkan ke **semua** subquery `withCount` supaya angka company lain tak pernah bocor; `capacity`/`booked_count` tetap global (konteks gate, sudah terbuka via availability). `MyUtilizationReportController` + `MyUtilizationReportRequest` (`can('report.read')`; 403 tanpa `company_id` — pola `/me/appointments`); reuse `SlotUtilizationResource`; `meta.company_id` di respons. Endpoint agregat planner/admin tak berubah. 4 test `tests/Feature/Reports/MyUtilizationReportTest.php` (scoping benar, planner tanpa company 403, driver tanpa `report.read` 403, gate wajib 422). UI-nya: entri FE `/reports` di atas.
- [x] **Hardening: Sanctum token TTL + prune (2026-07-07):** `sanctum.expiration` = env `SANCTUM_EXPIRATION` default **720 menit (12 jam ≈ 1 shift)** — sebelumnya `null` = token bocor valid **selamanya** (logout cuma mencabut satu token). Lewat TTL → 401 → SPA redirect login (handler sudah ada di `api/client.ts`). `sanctum:prune-expired --hours=24` dijadwalkan harian (`routes/console.php`; grace 24 jam supaya token mati masih bisa ditelusuri saat investigasi insiden, dan tabel `personal_access_tokens` tidak tumbuh tanpa batas). 4 test `tests/Feature/Hardening/TokenExpirationTest.php` (expired→401, dalam-TTL→200, prune hapus baris, schedule terdaftar).
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
- [x] **Endpoint pendukung:** `GET /api/v1/me/appointments/today` (driver, `TodayAppointmentsRequest::authorize`→`appointment.read.self`; repo `todayForDriver(driverId,date)` eager-load truck/driver/company/slotWindow.gate/containers; output `AppointmentResource::collection`) + `GET /api/v1/reports/utilization?gate=&date=` (planner/admin only via `UtilizationReportRequest`; repo `SlotRepository::utilization` `withCount` alias completed/no_show/cancelled/active per window; `SlotUtilizationResource` + `meta.summary` total via `->additional()`). 7 test. CATATAN: laporan utilisasi = agregat lintas-company (planner/admin); varian company-scoped transporter menyusul di `GET /me/reports/utilization` (lihat entri 2026-07-08 di atas).
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

## Senior review ronde 3 (2026-07-26) — temuan & keputusan
> Audit kode + **dokumen** menyeluruh, semua gerbang dijalankan sendiri (bukan percaya catatan):
> Pest 191/494 · Vitest 87 (20 file) · PHPStan lvl 8 **0 error** (207 file) · Pint bersih · vue-tsc bersih.
> Angka yang tercatat di README/ONBOARDING/SETUP-GUIDE/HANDOVER **cocok dengan hasil run** — tak ada yang dibesarkan.
- **[FIXED 2026-07-27 — P1] `driver_id` tidak divalidasi harus ber-role `driver`.**
  *Fix:* `InvalidDriverException` (422 `driver_invalid_role`) + guard ketiga di
  `BookAppointmentAction::assertFleetBelongsToCompany()` — `User::query()->whereKey(...)
  ->role('driver','api')->exists()`, **setelah** cek kepemilikan. Query scope (bukan
  `$driver->hasRole()`) karena `hasRole()` lazy-load relasi `roles` sementara
  `preventLazyLoading` aktif di non-prod — sekaligus memakai sumber kebenaran yang sama
  persis dengan `driversForCompany()`, jadi dropdown & penegakan tak bisa beda pendapat.
  +3 test (2 Action incl. urutan guard, 1 endpoint) + `UserFactory::driver()`. Detail di
  bawah ini adalah temuan aslinya, disimpan sebagai catatan kenapa fix-nya ada:
  *Bukti (bukan teori):* probe test booking dengan `driver_id` milik user ber-role **`transporter`**
  → **`201 Created`**. *Kelas bug identik dengan bug `INACTIVE` ronde lalu:* FE menyaring, API tidak.
  `FleetRepository::driversForCompany()` menyaring `role('driver','api')`, tapi `BookAppointmentRequest`
  (`driver_id` → `Rule::exists('users','id')->where('company_id')`) & `BookAppointmentAction::
  assertFleetBelongsToCompany()` hanya cek `exists` + `company_id`. *Akibat:* `AppointmentReminderJob`
  mengirim pengingat ke non-sopir; appointment itu **tak akan pernah muncul** di `/me/appointments/today`
  (butuh `appointment.read.self`) → sopir asli tak lihat jadwalnya. **Bukan** kebocoran lintas-company —
  scoping `company_id` tetap utuh. *Kenapa lolos test:* test booking yang ada memakai `User::factory()`
  **tanpa assignRole**, jadi gap ini memang tak pernah tersentuh. *Fix yang disarankan:* guard role di
  Action **setelah** cek kepemilikan — urutan yang sama seperti fix `INACTIVE` (kalau dibalik, beda pesan
  error membocorkan keberadaan user company lain).
- **[FIXED] Drift dokumen sisa dari audit `CLAUDE.md` (task #3 ditandai SELESAI padahal belum tuntas
  dipropagasi).** Lihat entri changelog `2026-07-26` di bawah: `README` masih mengklaim Redis/Horizon
  sebagai stack terpakai, dan §Perintah `CLAUDE.md` sendiri masih basi di 3 titik.
- **[FIXED 2026-07-27 — P4] Hygiene `.claude/` & dokumen yatim.** Temuan aslinya: (1)
  `.claude/settings.local.json` **tracked di git** (tak masuk `.gitignore`) & menumpuk 74 entri izin
  one-off termasuk perintah `sed` berisi hitungan test lama; (2) 3 dokumen yatim tak dirujuk peta
  README — `docs/VIBE-CODING.md`, `docs/SKILL.md`, `docs/security-reviewer.md`; dua terakhir
  **definisi skill/agent Claude Code** yang diparkir di `docs/` sehingga **tak teregistrasi apa pun**.
  *Fix:* `SKILL.md` → `.claude/skills/slice/SKILL.md` & `security-reviewer.md` →
  `.claude/agents/security-reviewer.md` (keduanya `git mv`, history terjaga); `settings.local.json`
  di-`git rm --cached` + masuk `.gitignore` **per-file, bukan seluruh `.claude/`** — `skills/` &
  `agents/` justru wajib di-commit supaya tersedia lintas perangkat (`tas-claude-code-guide.html §07`);
  `VIBE-CODING.md` dihapus (isinya cuma penunjuk ke guide, tak ada yang merujuknya — tetap terekam
  di git history). Keduanya juga **usang saat dipindah** dan ikut dikoreksi: `SKILL.md` menyuruh
  `composer test --parallel` (flag dobel — `composer test` sudah `pest --parallel`) dan tak menyebut
  gerbang FE; `security-reviewer` menyuruh memeriksa "setiap endpoint punya middleware scope"
  padahal ADR-0003 **sengaja menunda** token abilities → agent itu akan melaporkan false positive
  di **setiap** endpoint. Ditambah item baru: guard armada saat booking (ADR-0004, §W.4/§W.5).
- **[catatan] `DemoSeeder` tak punya truk `INACTIVE`** — keenam truk di-hardcode `'status' => 'ACTIVE'`.
  Aturan bisnis + jalur 422 `truck_inactive` yang baru ditambahkan **tak bisa didemokan dari data demo**,
  dan checklist anti-drift di bawah ("DemoSeeder menyentuh semua status & semua entitas") belum terpenuhi
  untuk enum `TruckStatus`. Sengaja tidak diubah sesi ini: menyentuh seeder menuntut `migrate:fresh --seed`
  yang **menghapus DB dev**.
- **[diverifikasi BENAR, tak ada aksi]** Lock order konsisten (appointment → slot window; id disortir di
  reschedule) → tak ada inversi deadlock. Idempotency scope `method|path` sesuai ADR. Cache explicit-key
  sesuai kontrak. **43 route** cocok penuh dgn tabel endpoint `SETUP-GUIDE §10d`; 10 route SPA cocok dgn
  `FRONTEND.md`; peta folder `ARCHITECTURE.md` cocok dgn `app/` aktual (termasuk sub-folder `Fleet/`).
  CI workflow benar memanggil skrip yang sama dengan lokal. **ERD §4 ternyata SUDAH** memuat
  `containers.slot_window_id` — TODO changelog `2026-06-27` sebenarnya sudah tuntas, hanya tak ditandai.

## Senior review ronde 4 (2026-08-02) — temuan & keputusan
> Audit **tasklist**: bukan "apa yang sudah dikerjakan benar?" (itu ronde 3), tapi **"apa yang
> dijanjikan kontrak tapi tak pernah punya kode?"**. Semua gerbang dijalankan sendiri sebelum
> menilai: Pest 194/501 · PHPStan lvl 8 **0 error** (208 file) · Pint bersih · Vitest 87 (20 file)
> · vue-tsc bersih — angka HANDOVER cocok dengan run nyata.
>
> **Blind spot yang memunculkan temuan ini:** ronde 3 memverifikasi arah *kode → dokumen*
> ("43 route cocok dengan tabel endpoint `SETUP-GUIDE §10d`"). Arah sebaliknya tak pernah dicek —
> janji di `BUSINESS-FLOW`/`PRD` yang **tak punya route sama sekali** tidak akan muncul di
> perbandingan itu. Lima dari tujuh temuan di bawah tak kelihatan dari sudut pandang ronde 3.
- **[FIXED 2026-08-02 — P1] `GateInAction` tidak memvalidasi jendela waktu sama sekali.**
  Hanya `status->canGateIn()`; tak ada cek tanggal/jam, dan `config/tas.php` tak punya kunci
  toleransi — padahal `BUSINESS-FLOW §2`/`§3.5` + `PRD §4` menjanjikannya. Detail fix ada di
  entri *Sudah selesai* teratas.
- **[TERBUKA — P1] Reminder hilang saat reschedule.** `§3.3` menjanjikan "reminder lama
  dibatalkan, reminder baru dijadwalkan". Kenyataan: `ScheduleAppointmentReminder` **hanya**
  mendengarkan `AppointmentBooked`; tak ada listener untuk `AppointmentRescheduled`.
  `AppointmentReminderJob` memang cek status terkini — itu menyelamatkan cancel/no-show, tapi
  **bukan** reschedule: status tetap `CONFIRMED`, jadi reminder meledak pada jadwal window
  **lama**, dan window baru tak pernah dapat reminder sama sekali. Sopir yang jadwalnya dipindah
  = tidak diingatkan. Perbaikan kecil (listener + `ShouldBeUnique` sudah per-appointment).
- **[TERBUKA — P2] `audit.read` diberikan, endpoint audit tidak ada.** Planner & transporter
  dapat `audit.read` di `RolePermissionSeeder`, matriks `§1` punya baris "Lihat audit log"
  (Admin ✅ / Planner sebagian / Transporter company sendiri), `§3.7` menjanjikan "transporter
  hanya lihat log company sendiri", `PRD §3` IN-scope menyebut audit trail. Rute audit: **nol**.
  Log-nya direkam (`Appointment` + `LogsActivity`), tak pernah bisa dibaca siapa pun.
- **[TERBUKA — P2] Activity Log tak punya satu pun test.** `grep -rl activity tests/` = kosong,
  padahal DoD `CLAUDE.md` mensyaratkan "perubahan status tercatat di Activity Log". Kalau trait
  `LogsActivity` atau `logOnly([...])` di `Appointment` hilang besok, **semua gerbang tetap
  hijau**. Ini gerbang yang bolong, bukan sekadar fitur kurang — kerjakan sebelum menumpuk
  fitur di atasnya.
- **[TERBUKA — P2] Gate Officer tak bisa menandai no-show manual.** Matriks `§1` baris "Tandai
  no-show" memberi ✅ ke gate-officer. `MarkNoShowAction` ada tapi **hanya** dipanggil
  `NoShowSweepJob`; tak ada route, tak ada ability di `AppointmentPolicy`. Praktisnya petugas
  gate harus menunggu grace period lewat.
- **[TERBUKA — P3] QR & `dwell_time` disebut berulang, nol implementasi.** `grep -i qr` dan
  `grep -i dwell` di `app/`+`resources/`+`routes/` = **0 hit**. QR muncul di `§3.4`, `§3.5`,
  matriks `§1`, dan `PRD §3` IN-scope; `dwell_time` di `§3.6` ("hitung dwell_time"). Yang ada
  hanya `booking_code`. Butuh **keputusan**, bukan langsung kode: bangun, atau persempit scope
  lewat ADR seperti preseden ADR-0006.
- **[catatan] Vitest berpotensi flaky di mesin terbebani.** Satu run melaporkan `9 file / 37 test
  + 11 errors`; dua run bersih berikutnya `20/20` & `87/87` exit 0 — kontensi resource, bukan
  kegagalan assertion. Tapi `environment 125 dtk` itu tipis: kalau runner CI dikecilkan, ini bisa
  jadi flake beneran.
- **[masih terbuka dari ronde 3]** `DemoSeeder` tak punya truk `INACTIVE` (keenam truk
  `'status' => 'ACTIVE'`) — jalur 422 `truck_inactive` tak bisa didemokan dari data demo.

## Sedang dikerjakan
- (kosong) — checkpoint hijau (201 Pest / 87 Vitest); terakhir: guard toleransi jendela gate-in ditutup (ronde 4 temuan P1). **CI belum diverifikasi untuk commit ini** (belum di-push).

## Langkah berikutnya (urut)
**Semua 4 persona UI + admin CRUD + realtime wiring + CRUD armada truk selesai** (transporter book/list/cancel/reschedule + kelola truk · driver jadwal · gate-officer antrian+gate-in/out · planner kelola window · admin terminal/gate/company/user · **realtime kuota+antrian live**).

> Urutan di bawah **berubah setelah ronde 4**: temuan "janji kontrak tanpa kode" naik ke atas,
> di depan item infrastruktur. Alasannya sama dengan alasan CI didahulukan atas e2e (ADR-0005):
> menambah lapisan baru di atas fondasi yang gerbangnya bolong = urutan terbalik.

1. **Reminder saat reschedule (P1, ronde 4).** Listener untuk `AppointmentRescheduled` yang
   menjadwalkan ulang `AppointmentReminderJob` ke window baru. Slice kecil, TDD: test merah
   dulu dengan bukti reminder tetap di jam window lama.
2. **Test Activity Log (P2, ronde 4).** Menambal gerbang yang bolong — DoD `CLAUDE.md`
   mensyaratkannya tapi nol test menjaganya. Kerjakan **sebelum** endpoint audit, supaya
   endpoint itu dibangun di atas jaminan yang teruji.
3. **Endpoint audit log (P2, ronde 4)** — `audit.read` sudah diberikan ke planner & transporter
   tapi tak ada rute; scoping company transporter lewat Policy (pola `/me/reports/utilization`).
4. **Putuskan: no-show manual, QR, `dwell_time` (P2/P3, ronde 4).** Empat janji kontrak tanpa
   kode. Bangun **atau** persempit scope lewat ADR — ADR-0006 sudah jadi preseden bagus. Yang
   penting kontrak berhenti menjanjikan yang tak dibangun; keduanya jawaban yang sah.
5. **Verifikasi realtime end-to-end di browser** (sisa dari wiring): set `BROADCAST_CONNECTION=reverb` di `.env`, `composer dev` (server+queue:listen+vite) + `php artisan reverb:start` (**Windows native, TANPA Docker**). Buka `/slots` di 2 browser (2 akun transporter, `docs/DUMMY-DATA.md`) → booking di satu → sisa kuota di lain berubah sendiri. Kode klien sudah siap (`echo.ts`/`useRealtime`); yang belum: dilihat mata di browser. Optional: swap `LoggingGateEventGateway` → TOS riil.
6. **Polish UI — sisa: e2e happy-path → DITUNDA SADAR (ADR-0005).** Loading skeleton **DONE**. E2E tidak dikerjakan karena menambah lapisan uji keempat di atas fondasi yang (waktu itu) belum punya penegak otomatis = urutan terbalik; CI didahulukan. **Prasyarat sebelum e2e dipasang** (supaya tak jadi utang baru): (a) `.env.e2e` + DB terpisah — dev pakai file SQLite, `migrate:fresh --seed` untuk e2e akan menghapus data dev; (b) `data-testid` di `LoginPage.vue` & `BookingForm.vue` (keduanya kini **0**, padahal justru jalur happy-path). Pemicu mengerjakannya ada di ADR-0005 → *Kapan ditinjau ulang*.
7. **[SELESAI 2026-07-25]** Audit klaim `CLAUDE.md` vs kenyataan tuntas: baris Docker Compose, baris CI, baris `Queue/Cache/Session`, dan aturan `Cache::tags` di §Hardening — semuanya kini selaras dengan `.env` & kode. **Sisa pekerjaan nyata (bukan dokumen):** benar-benar pindah ke **Redis + Horizon + MySQL** untuk paritas produksi (butuh `docker-compose.yml`) — sesi tersendiri. Saat itu dikerjakan, dua hal ikut berubah: cache boleh direfaktor dari explicit-key `Cache::forget` ke `Cache::tags`, dan §Stack CLAUDE.md naik status dari **target** jadi **keadaan**.
8. **[DIPUTUSKAN 2026-07-27 → ADR-0006] CRUD sopir: TIDAK dibuat di MVP — sopir dikelola admin.** Pertanyaan produk yang menggantung di item ini ("transporter boleh bikin akun user sendiri?") kini dijawab: **tidak**. Sopir = `User` ber-role `driver`, jadi self-service berarti transporter menerbitkan **akun login** — permukaan keamanan baru (undangan/password sementara, dan role/`company_id` wajib dipaksa server) yang tak sepadan dengan nilainya, sementara CRUD truk sudah mendemonstrasikan pola company-scoped CRUD-nya secara lengkap. Jalur resmi: Admin User CRUD (§V) yang sudah ber-guard. Transporter tetap **melihat** sopirnya via `GET /me/fleet` (read-only, sudah ada). `PRD §3` sudah dikoreksi supaya kontrak tak lagi menjanjikan yang tak dibangun. Pemicu tinjau ulang ada di `docs/adr/0006-driver-management-admin-only.md`.
9. **Backlog hardening sisa:** token abilities sempit (ADR-0003, tegakkan saat ada token ber-scope sempit).

## Changelog kontrak / dokumen / seeder
> Catat tiap perubahan yang menyentuh CLAUDE.md, docs/*, atau seeder.
> Format: `tanggal: APA yang berubah → file mana yang ikut diupdate. Alasan.`
- `2026-08-02`: **Toleransi jendela gate-in jadi nyata + audit tasklist ronde 4.** Kode:
  `config/tas.php` (blok `gate_in` baru), `SlotWindow::startsAt()`, `GateInWindowException` (baru),
  guard di `GateInAction`, `SlotWindowFactory::ongoing()` (baru), `GateInTest` (+7 test, jam
  dibekukan `travelTo`). Docs: `BUSINESS-FLOW §2` (aturan transisi `CONFIRMED → ARRIVED` naik dari
  "boleh ada toleransi" jadi aturan pasti + kode error) & `§3.5` (langkah 4 + 2 blockquote: urutan
  guard, dan kenapa sweep tak cukup), `CODE-WALKTHROUGH §X` (baru), `SETUP-GUIDE §10d` (baris
  gate-in dapat kode error), `HANDOVER` §Status/§Sudah selesai/§Senior review ronde 4 (baru)/
  §Langkah berikutnya (**diurut ulang** — temuan kontrak-tanpa-kode naik di atas item infra).
  Seeder: tak ada. **Alasan:** `PRD §4` & `BUSINESS-FLOW §2`/`§3.5` menjanjikan toleransi
  early/late dari config sejak awal, tapi `GateInAction` tak pernah melihat jam dan `config/tas.php`
  tak punya kuncinya — dokumen menjanjikan penegakan yang tak ada. Ronde 4 juga mencatat 6 temuan
  lain yang **belum** dikerjakan supaya tak hilang lagi (reminder-reschedule, endpoint audit, test
  Activity Log, no-show manual, QR/`dwell_time`). **Belum disentuh:** `.env.example` masih tak
  memuat satu pun `TAS_*` (berlaku untuk kedelapan knob, bukan cuma yang baru) — utang lama,
  sengaja tidak dilebarkan di slice ini.
- `2026-07-27`: **Catat CI terverifikasi hijau + resep cek CI tanpa `gh`.** Kode: tak ada. Docs:
  `HANDOVER` §Status (run [30228335447](https://github.com/caesarovera/truck-appointment-system/actions/runs/30228335447)
  @ `f6495a0` — kedua job + semua step sukses) + §Jebakan (baris baru), `SETUP-GUIDE §14c`
  (sub-bagian *cek status dari terminal tanpa `gh`* + peringatan **1 run per PUSH, bukan per
  COMMIT**). Alasan: sesi ini sempat menyimpulkan "status CI tak bisa diverifikasi" hanya karena
  `gh: command not found` — padahal repo publik, dan REST API GitHub terbaca tanpa token sama
  sekali. Kesimpulan yang berhenti di rintangan pertama itu persis yang bikin klaim usang
  bertahan lama; resepnya ditulis (dan **diuji jalan** di mesin dev, tanpa `jq` yang memang tak
  ada) supaya sesi berikutnya tak mengulang kebuntuan yang sama. Nilai konkretnya: step
  *Install dependensi* backend = `composer install` **tanpa** `--ignore-platform-req`, satu-satunya
  gerbang yang mesin Windows tak bisa uji sendiri — kini terbukti hijau.
- `2026-07-27`: **P4 hygiene — skill & agent Claude Code diregistrasi; `settings.local.json` di-untrack.**
  Kode: **tak ada**. Struktur: `docs/SKILL.md` → `.claude/skills/slice/SKILL.md`,
  `docs/security-reviewer.md` → `.claude/agents/security-reviewer.md` (`git mv`),
  `docs/VIBE-CODING.md` **dihapus** (penunjuk yatim; tak ada satu pun dokumen merujuknya, dan isinya
  menyatakan sendiri boleh dihapus). `.gitignore` +`.claude/settings.local.json` **(per-file)** dan
  file itu di-`git rm --cached`. Docs: `README` peta dokumentasi +baris `.claude/skills` & `agents`,
  `ONBOARDING` baris routing "Backend fitur baru" kini menunjuk skill `/slice` yang **benar-benar
  ada**. Alasan: dua file itu punya frontmatter `name:`/`tools:` — mereka **definisi** skill/agent,
  bukan bacaan; diparkir di `docs/` artinya nol efek. Sebaliknya `settings.local.json` adalah
  override personal per-mesin (akhiran `.local`) yang tiap sesi bertambah entri izin one-off —
  ia satu-satunya isi `.claude/` yang **tidak** boleh di-commit, sementara guide justru menyuruh
  commit `.claude/` demi skill/agent lintas perangkat. Selama ini terbalik: yang personal ikut,
  yang seharusnya dibagi malah tak ada. **Ikutan (isi keduanya juga usang):** `SKILL.md`
  memerintahkan `composer test --parallel` — flag dobel, sebab `composer test` **sudah**
  `pest --parallel` (kelas bug yang sama dengan §Perintah `CLAUDE.md` kemarin) + gerbang FE
  ditambahkan; `security-reviewer` menyuruh audit "middleware scope tiap endpoint" padahal ADR-0003
  **sengaja menunda** token abilities → agent akan melaporkan false positive di setiap endpoint,
  kini diberi larangan eksplisit + rujukan ADR, ditambah item mass-assignment (ADR-0004) dan guard
  armada booking (§W.4/§W.5). Tidak menyentuh `CLAUDE.md`/seeder/BE/FE/test.
- `2026-07-27`: **Fix bug P1 `driver_invalid_role` + ADR-0006 (sopir admin-only) + KONTRAK PRD BERUBAH.**
  *Kode BE:* `InvalidDriverException` **baru** (422 `driver_invalid_role`), guard ketiga di
  `BookAppointmentAction::assertFleetBelongsToCompany()` — `->role('driver','api')->exists()`,
  dipasang **setelah** cek kepemilikan (urutan sama dengan fix `INACTIVE`: kalau dibalik, beda
  pesan error membocorkan keberadaan user company lain). Query scope dipilih alih-alih
  `$driver->hasRole()` karena `hasRole()` lazy-load relasi `roles` sementara `preventLazyLoading`
  aktif di non-prod — sekaligus memakai sumber kebenaran yang **sama persis** dengan
  `FleetRepository::driversForCompany()`, jadi dropdown & penegakan mustahil beda pendapat.
  *Test:* +3 (2 `Booking/BookAppointmentActionTest` incl. **urutan guard**, 1
  `Booking/BookAppointmentEndpointTest` 422) → **194 Pest / 501 assert**. Vitest tetap **87**
  (tak ada perubahan FE — pesan server lolos ke UI lewat fallback `data.message` di `BookingForm`,
  pola yang sama dengan `truck_inactive`). *Infrastruktur test:* `UserFactory::driver()` **baru**
  (assign role via `Role::findOrCreate('driver','api')` → jalan baik dengan maupun tanpa
  `RolePermissionSeeder`); **3 helper booking lama membuat "sopir" tanpa role** — persis sebab
  gap ini lolos sekian lama — kini memakai state itu (`bookingScenario`,
  `transporterBookingContext`, `bookingRateLimitContext`; yang terakhir sempat merah dan itu
  **bukti guard-nya menggigit**). *Docs:* `BUSINESS-FLOW §3.2` (langkah 4 + blok aturan baru) &
  `§1` (baris matriks transporter), `CODE-WALKTHROUGH §W.5` **baru** (+§W.6 Test, penomoran
  digeser), `SETUP-GUIDE` (tabel endpoint booking), hitungan test di README/ONBOARDING/
  SETUP-GUIDE/HANDOVER → **194/501**. *Keputusan produk:* `adr/0006-driver-management-admin-only.md`
  **baru** (+baris tabel `adr/README`) — **CRUD sopir tidak dibangun di MVP**, sopir dibuat admin
  lewat Admin User CRUD; transporter tetap **melihat** sopirnya via `/me/fleet`. **`PRD §3`
  dikoreksi** (baris IN transporter dipersempit ke "kelola truk + lihat sopir"; baris OUT baru
  untuk CRUD sopir) — aturan PRD *"apa pun di luar IN tidak dikerjakan tanpa memperbarui PRD"*
  berlaku dua arah: **mencoret** dari IN juga menuntut PRD diperbarui, bukan dibiarkan jadi janji
  yang membusuk. Alasan keputusan: sopir = `User`, jadi self-service = **penerbitan akun login**
  (permukaan privilege-escalation baru) sementara pola company-scoped CRUD-nya sudah tuntas
  didemonstrasikan slice truk — risiko baru tanpa pola baru. Tidak menyentuh `CLAUDE.md`/seeder/
  migrasi/FE.
- `2026-07-26`: **KONTRAK BERUBAH — §Perintah `CLAUDE.md` dikoreksi + propagasi klaim Redis ke README.**
  Ditemukan saat *Senior review ronde 3*: audit klaim `CLAUDE.md` (task #3) ditandai SELESAI padahal
  koreksinya **berhenti di `CLAUDE.md` saja**. (1) `README §Stack` masih menulis `· Redis/Horizon ·`
  polos sebagai stack terpakai — bertentangan dgn `CLAUDE.md §Stack` yang sudah dipisah target-vs-dev
  di commit `4c652b7`. README adalah dokumen **#1 di urutan onboarding**, jadi pembaca baru justru kena
  klaim yang salah lebih dulu. Kini README menyebut driver `database`/SQLite + menandai Redis/Horizon
  sebagai *target* & menunjuk `CLAUDE.md §Stack`. (2) **§Perintah `CLAUDE.md` basi di 3 titik dan tak
  pernah ikut terkoreksi:** `npm run dev | test | build` → script **`test` tidak ada** di `package.json`
  (adanya `test:js`), jadi perintah di kontrak **gagal kalau dijalankan**; `npm run type-check` absen
  padahal ia **gerbang CI** (§Stack kontrak ini sendiri mewajibkan gerbang baru ikut masuk workflow);
  `php artisan horizon # queue worker (dev)` menyesatkan — Horizon butuh `ext-pcntl` yang tak ada di
  mesin dev Windows (sudah dicatat di *Jebakan*), sementara perintah dev sebenarnya `composer dev`
  justru tak tercantum. (3) **`CLAUDE.md` merujuk "skill `laravel-tdd`" yang tidak ada** — bukan di repo
  (`.claude/` cuma berisi `settings.local.json`) maupun di environment; pointer menggantung di file yang
  di-load **tiap sesi**. Rujukannya dihapus (langkah loop TDD-nya sudah ditulis lengkap tepat di bawahnya,
  jadi tak ada informasi yang hilang). (4) `HANDOVER` rujukan silang basi: entri CRUD truk menunjuk
  *Langkah berikutnya* `#3` untuk CRUD sopir, padahal kini item **`#4`** (`#3` = Redis/Docker) →
  dibetulkan. Alasan sama dengan dua ronde sebelumnya: **kontrak yang memerintahkan perintah tak jalan
  lebih berbahaya daripada status basi** — ia menghasilkan langkah salah, bukan sekadar info usang.
  **Tidak menyentuh kode/seeder/test/migrasi** (murni dokumen). `tas-claude-code-guide.html` **sengaja
  TIDAK diubah**: tabel skill-nya rekomendasi "aktifkan yang mana", bukan klaim isi repo — bukan drift.
- `2026-07-25`: **KONTRAK BERUBAH — `CLAUDE.md` baris Redis ditandai TARGET + aturan cache diperbaiki.**
  (1) §Stack: `Queue/Cache/Session: Redis 7 + Horizon` → dipisah jadi **target produksi** vs **dev
  sekarang** (driver `database`, DB `sqlite`, `BROADCAST_CONNECTION=log`). Redis tetap tercatat sebagai
  arah — penting, karena keputusan cache di bawah ini bersandar padanya.
  (2) §Hardening → Cache: aturan lama menyuruh `Cache::tags(...)`, padahal `SlotRepository` justru
  memakai **explicit-key `Cache::flexible` + `Cache::forget`** sejak `2026-06-27` karena store
  `database` **tak mendukung tagging**. Jadi kontrak selama ini memerintahkan pola yang **akan rusak**
  kalau benar-benar diikuti — lebih berbahaya daripada klaim status yang basi, sebab ini instruksi
  yang menghasilkan kode salah. Kini aturannya berbunyi explicit-key, dengan izin refaktor ke
  `Cache::tags` **setelah** pindah ke Redis. Alasan: penyimpangan itu sudah tercatat di changelog
  `2026-06-27`, tapi tak pernah naik ke kontrak — persis pola "dokumen tahu, kontrak tidak" yang
  membuat klaim CI & Docker luput. Tidak menyentuh kode/seeder/test (kode sudah benar sejak awal).
- `2026-07-25`: **KONTRAK BERUBAH — `CLAUDE.md` §Stack baris infra dikoreksi.** Sebelumnya
  `* Infra: Docker Compose · CI: GitHub Actions` — **dua-duanya tidak ada** saat itu. Kini dipecah
  jadi dua baris jujur: (1) *Infra dev: **native, tanpa Docker** (PHP + SQLite); `docker-compose.yml`
  **belum ada** — rencana, untuk paritas produksi (Redis/MySQL/Horizon)*, plus larangan eksplisit
  menulis "sudah pakai Docker" sebelum file-nya benar-benar ada; (2) *CI: GitHub Actions* + pointer
  ke `SETUP-GUIDE §14` + pengingat bahwa gerbang baru wajib ikut masuk workflow. §Urutan build
  langkah 1 juga dihapus "Docker Compose"-nya. Ikutan: `SETUP-GUIDE` tabel prasyarat — baris Docker
  masih menyebut Reverb butuh Docker, padahal 4 baris di bawahnya file yang sama sudah mengoreksinya
  (Reverb jalan native); kini konsisten. Alasan: kontrak yang menyatakan sesuatu ada padahal tidak
  adalah persis penyakit yang membuat CI luput selama ini — dokumen yang tak bisa dipercaya lebih
  buruk daripada dokumen yang mengaku belum lengkap. **Masih tersisa & belum disentuh:** baris
  `Queue/Cache/Session: Redis 7 + Horizon` (lihat *Langkah berikutnya* #3) — butuh keputusan pemilik
  repo, bukan diputuskan sepihak. Tidak menyentuh seeder/kode.
- `2026-07-25`: **CI GitHub Actions dipasang + ADR-0005 baru.** Kode: `.github/workflows/ci.yml`
  **baru** (2 job paralel; perintahnya = skrip composer/npm yang sama dengan lokal). Docs:
  `adr/0005-ci-github-actions.md` **baru** (+baris di `adr/README`), `SETUP-GUIDE §14` **baru**
  (untuk apa · apa yang dijalankan · cara pakai + tabel gejala→sebab saat merah) + entri Daftar Isi,
  `README` (badge CI + paragraf CI + doc-map SETUP-GUIDE menunjuk §14), `ONBOARDING` (baris routing
  "CI merah setelah push" + catatan di perintah harian + peta dokumen). **CLAUDE.md sengaja TIDAK
  diubah:** klaimnya "CI: GitHub Actions" justru baru sekarang jadi benar, jadi tak ada yang perlu
  dikoreksi; menambahkan "CI hijau" ke Definition of Done = perubahan kontrak → butuh persetujuan
  pemilik repo dulu. Alasan: gerbang kualitas yang hanya jalan kalau diingat manusia tidak menjaga
  apa pun; klaim kontrak yang tidak benar adalah penyakitnya sendiri. Tidak menyentuh seeder/BE/FE.
- `2026-07-25`: **Skeleton loading (polish UI bagian 1).** Kode: `components/SkeletonRows.vue`
  **baru**; 11 titik loading di `AdminPage`(4)/`SlotAvailabilityPage`/`MyBookingsPage`/`MyTrucksPage`/
  `DriverSchedulePage`/`GateDashboardPage`/`PlannerWindowsPage`/`MyUtilizationPage` +
  `RescheduleDialog` beralih dari `<p>Memuat…</p>` ke komponen itu. Test: +3 Vitest
  (`tests/js/SkeletonRows.test.ts`) → **87 Vitest**. Docs: `FRONTEND` (baris komponen di tabel §4 +
  sub-bagian *Loading state* berisi alasan sr-only & kenapa `isFetching` dikecualikan),
  `README`/`ONBOARDING`/`SETUP-GUIDE` (hitungan Vitest). Alasan: teks "Memuat…" bikin tinggi konten
  melompat saat data masuk; label dipindah ke `sr-only` supaya pembaca layar tak kehilangan info
  (dan test lama tetap hijau). Tidak menyentuh CLAUDE.md/BE/seeder.
- `2026-07-25`: **CRUD armada truk (`/me/trucks`) + penegakan status `INACTIVE`.**
  Kode BE: `Actions/Fleet/*` (3), `DataTransferObjects/Fleet/TruckData`, `Http/Requests/V1/Fleet/*` (2),
  `Http/Controllers/Api/V1/Fleet/*` (4), `FleetRepository`+`FleetRepositoryInterface` (3 method CRUD +
  param `?TruckStatus` di `trucksForCompany`), `EntityInUseException::truck()`, `Truck::appointments()`,
  `routes/api.php` (4 route). Fix bug: `InactiveTruckException` **baru** (422 `truck_inactive`),
  guard di `BookAppointmentAction`, `MyFleetController` → ACTIVE saja. Kode FE: `api/trucks.ts`,
  `composables/useTrucks.ts`, `pages/MyTrucksPage.vue`, route `/trucks`, link `AppNav`+`DashboardPage`,
  types `TruckStatus`/`TruckPayload`. Test: +13 Fleet, +3 penegakan INACTIVE, +2 file Vitest →
  **191 Pest / 494 assert · 84 Vitest**. Docs: `BUSINESS-FLOW §3.2` (aturan status truk = kontrol
  penjadwalan + urutan guard), `CODE-WALKTHROUGH §W` **baru** (+TOC), `SETUP-GUIDE` (5 baris tabel
  endpoint + catatan 422 di booking + §13 peta file + hitungan test), `FRONTEND` (baris route
  `/trucks`, catatan dropdown ACTIVE-saja, daftar rute, tabel nav), `ARCHITECTURE` (peta folder:
  sub-folder `Fleet/` di 4 lapisan), `adr/0001` (catatan: kini **2** area fitur ber-sub-folder —
  masih di bawah ambang tinjau-ulang ≥3, syarat "tim bertambah" juga belum), `README`/`ONBOARDING`
  (hitungan test + baris routing "CRUD ber-scope company sendiri" → §W).
  Alasan: menutup task #3 "CRUD truk/sopir" (bagian truk) yang menggantung uncommitted; status truk
  yang tak ditegakkan itu **bug nyata** — endpoint hapus menyuruh pakai INACTIVE padahal INACTIVE
  tak menghentikan apa pun. Tidak menyentuh CLAUDE.md/seeder/migrasi. **Sopir belum** (lihat
  *Langkah berikutnya* #4 — keputusan produk soal pembuatan akun user).
- `2026-07-25`: **Audit staleness dokumen + kode (pasca realtime).**
  *Docs:* selaraskan hitungan test current-state ke **174 Pest / 74 Vitest** (README, ONBOARDING,
  SETUP-GUIDE — entri changelog historis TIDAK diubah, itu snapshot masa lalu); hapus klaim usang
  "Git belum di-init" (repo sudah push) & "Realtime Echo masih belum disambung" (FRONTEND §4);
  `CODE-WALKTHROUGH §P.5` baru (guard `/broadcasting/auth`). *Kode:* `.env.example` +REVERB_*/
  VITE_REVERB_* (placeholder kosong — bukan secret; fresh clone tahu var realtime ada); **hapus
  scaffold mati** `resources/js/app.js` + `bootstrap.js` + `resources/views/welcome.blade.php`
  (catch-all `web.php` selalu render `app.blade.php`→`app.ts`; welcome tak ter-route & satu-satunya
  pemuat app.js→bootstrap.js). Efek samping bagus: CSS produksi turun ~41→~21 kB (Tailwind tak lagi
  pindai kelas di welcome). *Temuan penting:* `->withExceptions(fn)` kosong di `bootstrap/app.php`
  **BUKAN dead code** — menghapusnya bikin `BindingResolutionException` (registrasi handler exception
  di sana); dikembalikan + diberi komentar kenapa wajib tetap. Tidak menyentuh CLAUDE.md/seeder/BE-logic.
- `2026-07-25`: **Wiring realtime (Reverb + Echo) + koreksi klaim "Reverb butuh Docker".**
  Kode: `bootstrap/app.php` (guard `/broadcasting/auth` → `auth:sanctum` via `withBroadcasting`),
  FE baru `echo.ts`/`composables/useRealtime.ts` + wiring 2 halaman + `app.ts`, paket `laravel-echo`+
  `pusher-js`. Test: +5 Pest (`Realtime/BroadcastAuthTest`), +7 Vitest (`useRealtime.test`) →
  **174 Pest / 74 Vitest**. Docs: `HANDOVER` (status, Sudah selesai, Jebakan **dikoreksi** — Reverb
  jalan Windows native, hanya Horizon butuh pcntl), `SETUP-GUIDE`/`ONBOARDING` (koreksi Reverb≠Docker),
  `FRONTEND.md` (§ realtime: echo.ts + useRealtime). Alasan: menutup tasklist realtime; klaim lama
  "Reverb butuh Docker" salah (composer.json Reverb tak minta ekstensi) & menghambat dev tak perlu.
  Tidak menyentuh CLAUDE.md/seeder. channels.php TIDAK diubah (callback RBAC sudah benar).
- `2026-07-08`: **History git ditulis ulang (24 commit) + `docs/GIT-HISTORY-REWRITE.md` baru.**
  Trailer `Co-Authored-By: Claude ...` → `Co-Authored-By: Overa Caesar` di seluruh history
  (`git filter-branch --msg-filter` + force-push; SHA semua commit berubah — clone lain
  harus `git fetch && git reset --hard origin/main`, JANGAN pull). Baris `Claude-Session:`
  sengaja dipertahankan (jejak audit). Prosedur lengkap + jebakan nyata (unstaged changes,
  SHA lama masih resolvable, lease `--force-with-lease` basi pasca filter-branch)
  didokumentasikan sebagai panduan manual di `docs/GIT-HISTORY-REWRITE.md`; README doc-map
  ikut diupdate. Alasan: atribusi commit milik pemilik repo; panduan ditulis supaya bisa
  diulang tanpa AI. Commit BERIKUTNYA wajib pakai trailer baru.
- `2026-07-08`: **Nav/layout bersama FE (`AppNav`+`AppLayout` parent route).** Kode:
  2 komponen baru, `router/index.ts` restrukturisasi nested (meta `requiresAuth` di parent),
  Dashboard disederhanakan, link "← Dashboard" dihapus dari 6 halaman. Docs: `FRONTEND.md`
  (§2 router+layout, catatan layout di §5), hitungan Vitest → **67**. Alasan: navigasi
  lintas-halaman tanpa bolak-balik Dashboard; satu sumber daftar link (anti-drift saat
  nambah halaman); meta di parent = halaman baru otomatis terlindungi guard. Tidak
  menyentuh CLAUDE.md/BE.
- `2026-07-08`: **Halaman FE `/reports` (Laporan Perusahaan, transporter).** Kode:
  `fetchMyUtilization` (api/slots.ts), `useMyUtilization` (key terpisah `['my-utilization']`),
  `MyUtilizationPage.vue`, route `/reports`, link Dashboard gated `report.read` + `company_id`.
  Docs: `FRONTEND.md` (tabel route), hitungan Vitest → **63**. Alasan: melengkapi slice BE
  utilisasi company-scoped jadi fitur utuh; gating link pakai company_id (bukan role) supaya
  konsisten dgn aturan 403 server; query key dipisah dari planner agar cache dua scope tak
  saling menimpa. Tidak menyentuh CLAUDE.md/seeder/BE.
- `2026-07-08`: **Endpoint `GET /me/reports/utilization` (transporter, company-scoped).**
  Kode: `SlotRepositoryInterface`/`SlotRepository::utilization(+?int $companyId)`,
  `MyUtilizationReportController`/`Request` baru, route. Docs: `BUSINESS-FLOW §3.7`
  (baris transporter), `SETUP-GUIDE` (tabel endpoint), `CODE-WALKTHROUGH §Q.2` (closure
  `$scoped`), hitungan test → **169 Pest / 452 assert**. Alasan: matriks RBAC §1 sudah
  lama menjanjikan "company sendiri" untuk transporter tapi endpoint-nya belum ada;
  scoping di level subquery (bukan filter di controller) supaya kebocoran angka company
  lain mustahil secara struktural. Tidak menyentuh CLAUDE.md/seeder.
- `2026-07-07`: **Sanctum token TTL (12 jam) + prune harian.** Kode: `config/sanctum.php`
  (`expiration` env-tunable, sebelumnya `null`), `routes/console.php` (+`sanctum:prune-expired
  --hours=24` daily). Docs: `CODE-WALKTHROUGH §K` (bullet TTL), hitungan test → **165 Pest /
  439 assert**. Alasan: token abadi = blast-radius kebocoran tak terbatas; 12 jam ≈ 1 shift
  kerja gate/planner jadi re-login harian wajar; TTL juga jadi jaring pengaman selagi token
  abilities ditunda (ADR-0003). Grace prune 24 jam untuk jejak investigasi. Tidak menyentuh
  CLAUDE.md/seeder.
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
- **Git:** repo sudah di-init & push ke GitHub `caesarovera/truck-appointment-system` (branch `main`).
  Konvensi commit: 1 slice = 1 commit; atribusi `Co-Authored-By: Overa Caesar` (bukan Claude) —
  lihat `docs/GIT-HISTORY-REWRITE.md`.
- **KOREKSI (2026-07-25): hanya HORIZON butuh `ext-pcntl`/`ext-posix`, BUKAN Reverb.** Dulu dicatat
  "Horizon/Reverb butuh pcntl → Docker" — salah untuk Reverb. `laravel/reverb` composer.json cuma minta
  `php ^8.2` + paket PHP murni (react/socket, ratchet, pusher-php-server). **Terbukti: `php artisan
  reverb:start` bind port 8080 di Windows native.** Untuk dev, queue cukup `queue:listen` (tanpa pcntl);
  Horizon hanya dashboard pemantau. Jadi **realtime TIDAK butuh Docker**. Docker tetap relevan nanti
  utk paritas produksi (Redis `Cache::tags`, Horizon, MySQL) — sesi tersendiri. `composer install` di
  Windows tetap perlu `--ignore-platform-req` selama Horizon terpasang.
- **Realtime: server + klien sudah tersambung (2026-07-25); tinggal verifikasi mata di browser.**
  `ShouldBroadcast` event + listener + channel auth (`auth:sanctum`) + klien Echo (`echo.ts`/`useRealtime`)
  semua jadi & ber-test. Untuk menyalakan: (1) `BROADCAST_CONNECTION=reverb` di `.env` (default masih
  `log` supaya dev Windows tak butuh worker); (2) `php artisan reverb:start` (native) + `composer dev`
  (queue:listen jalan — **`ShouldBroadcast` ke queue dulu, tanpa worker event DIAM tanpa error**);
  (3) buka SPA. Push TOS masih `LoggingGateEventGateway` (placeholder) — swap binding saat ada TOS riil.
- **Cek status CI tanpa `gh` — `gh` TIDAK terpasang di mesin dev ini** (sudah dicek: tak ada di
  PATH PowerShell maupun 3 lokasi instalasi umum). Itu **bukan** alasan untuk bilang "status CI tak
  bisa diverifikasi": repo ini **publik**, jadi REST API GitHub terbaca tanpa token/autentikasi.
  `curl` ada bawaan Git for Windows, `jq` **tidak** ada → pakai resep berbasis PHP di
  `docs/SETUP-GUIDE.md §14c` (terverifikasi jalan 2026-07-27). Dicatat karena sesi ini sempat
  berhenti di rintangan pertama (`gh: command not found`) dan menyimpulkan terlalu cepat —
  padahal pintu lain terbuka. **Ingat juga:** GitHub bikin **1 run per PUSH, bukan per COMMIT**
  (push 3 commit sekaligus → 1 run di commit terakhir), jadi commit `3025eb4` tak punya run
  sendiri walau isinya tetap teruji lewat keturunannya.
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
