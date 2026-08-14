# docs/FRONTEND.md — Penjelasan Detail Frontend (Vue SPA)

> Pendamping `docs/CODE-WALKTHROUGH.md` (yang fokus backend). Dokumen ini menjelaskan
> **setiap potongan kode frontend** + **alasan (kenapa)**-nya. SPA Vue 3 hidup di
> `resources/js`, decoupled penuh dari Laravel (REST `/api/v1` + token Sanctum), bukan
> Inertia. Stack & pin versi: lihat `HANDOVER.md`.
>
> Cara menjalankan: `php artisan serve` (port 8000, shell + API) **dan** `npm run dev`
> (Vite HMR). App dibuka di `http://localhost:8000`, bukan port Vite. Detail di
> `SETUP-GUIDE.md §9a`.

---

## Daftar Isi
1. [Arsitektur & lapisan](#1-arsitektur--lapisan)
2. [Fondasi (client, store auth, router, bootstrap)](#2-fondasi)
3. [Pola data: TanStack Query & mutation](#3-pola-data-tanstack-query--mutation)
4. [Halaman per persona + komponen](#4-halaman-per-persona--komponen)
5. [Pola test (Vitest)](#5-pola-test-vitest)
6. [Peta rute & navigasi](#6-peta-rute--navigasi)

---

## 1. Arsitektur & lapisan

Frontend mengikuti **pemisahan lapisan** yang sejajar semangat backend (HTTP → bisnis →
data). Tiap fitur mengalir lewat empat lapis, dari bawah ke atas:

```
types/api.ts      → Kontrak tipe (bentuk respons API). Satu sumber kebenaran TS.
api/*.ts          → Fungsi fetch tipis: panggil axios, buka bungkus `data`, balikan tipe.
composables/*.ts  → State server via TanStack Query (useQuery/useMutation) + cache key.
pages/ + components/ → Komponen Vue: hanya render + panggil composable. Tanpa axios langsung.
```

> **Kenapa berlapis begini?**
> - **Testable**: komponen di-test dengan *me-mock composable* (bukan jaringan); fungsi
>   `api/*` di-test dengan *me-mock axios*. Tak ada test yang menyentuh server.
> - **Anti-duplikasi**: mis. `RescheduleDialog` me-reuse `useGates` + `useSlotAvailability`
>   yang sama dengan halaman ketersediaan — logika fetch tak ditulis dua kali.
> - **Ganti implementasi aman**: bentuk respons berubah → cukup ubah `types/` + `api/`.

Pinia store (`stores/auth.ts`) khusus **state auth** (token + user), karena ini state
klien yang persist (localStorage), bukan cache server. Sisanya cache server → TanStack
Query, **bukan** Pinia (hindari menduplikasi server state ke store).

---

## 2. Fondasi

### `api/client.ts` — axios + token + 401
Satu instance axios (`baseURL: '/api/v1'`). Token disimpan di **level modul** (bukan
impor store) supaya interceptor tak bikin *circular import* (store impor client, client
tak boleh impor store). Interceptor request menempel `Authorization: Bearer`. Interceptor
response menangkap **401** → hapus token + panggil hook `onUnauthorized` (di-set di
`app.ts` → redirect ke login). Token dipersist di `localStorage('tas_token')`.

> **Kenapa hook, bukan langsung router?** Sama alasan: hindari client mengimpor router/
> store. `app.ts` yang menyuntik perilakunya → client tetap bebas dependensi.

### `stores/auth.ts` — Pinia
`login` (POST /login → simpan token+user), `logout` (POST /logout lalu `clearSession`),
`fetchMe`/`restore` (pulihkan sesi saat reload bila ada token tapi user kosong), serta
helper **`can(permission)`** & **`hasRole(role)`** yang membaca dari `user.permissions/
roles`. Bentuk respons berbeda ditangani: `/login` **datar** (`{token,user}`),
`/me` **terbungkus** (`{data}`).

> **Kenapa `can()`/`hasRole()` di store?** Navigasi & tombol aksi ditampilkan per-izin
> (mis. tombol "Booking" hanya untuk `appointment.write`). UI gating ini **bukan**
> pengganti otorisasi server — server tetap menegakkan; UI hanya menyembunyikan yang
> pasti ditolak (UX).

### `router/index.ts` — guard + layout bersama
`beforeEach`: bila ada token tapi user belum dimuat → `auth.restore()` (ambil /me);
rute `requiresAuth` tanpa auth → lempar ke `/login?redirect=`; rute `guestOnly` saat
sudah login → ke dashboard. Komponen halaman di-`import()` lazy (code-split per rute).

Semua halaman ber-auth adalah **children** dari satu parent route `/` ber-komponen
`components/AppLayout.vue` (= `AppNav` + `RouterView`). `requiresAuth` cukup ditaruh
di parent — Vue Router **menggabungkan meta parent ke child**, jadi tiap halaman baru
otomatis terlindungi tanpa mengulang meta. `AppNav` = satu-satunya sumber daftar link
(gating per **permission**, bukan nama role — cermin otorisasi server). Beberapa link
butuh guard identitas tambahan karena admin punya SEMUA permission tapi tak punya
`company_id`/`terminal_id` — endpoint di baliknya 403 tanpa itu: `/reports`/`/bookings`/
`/trucks` cek `company_id`, `/gate` cek `terminal_id`. `/today` beda lagi — backend-nya
tak 403 admin (list kosong, bukan error), jadi satu-satunya link yang gating tambahannya
`hasRole('driver')`, bukan cek identitas (2026-08-13, ditemukan lewat laporan user
langsung: admin bisa buka 4 menu operasional itu dan semuanya gagal/membingungkan).
Halaman tidak lagi punya link "← Dashboard" sendiri.

### `app.ts` — bootstrap
Pasang Pinia, Router, **VueQueryPlugin**, lalu wiring hook 401 (`setUnauthorizedHandler`
→ clearSession + push login). Shell HTML: `resources/views/app.blade.php` + catch-all
`routes/web.php` (`^(?!api).*$`) → semua path non-API mengembalikan SPA. **Siklus Echo**:
`watch(auth.isAuthenticated)` → `connectEcho()` saat login/restore, `disconnectEcho()` saat
logout/401 (token untuk `/broadcasting/auth` sudah ada saat connect).

### `echo.ts` + `composables/useRealtime.ts` — realtime (Reverb)
- **`echo.ts`** — singleton Laravel Echo (transport pusher-js, broadcaster `reverb`). **Lazy &
  opsional**: `connectEcho()` no-op bila `VITE_REVERB_APP_KEY` kosong → **degradasi mulus** (tanpa
  Reverb app == polling/invalidasi biasa, bukan error). Auth channel privat via `/broadcasting/auth`
  dgn header `Authorization: Bearer <token>` (SPA tak pakai cookie sesi → guard server WAJIB
  `auth:sanctum`, lihat `bootstrap/app.php`).
- **`useRealtime.ts`** — `useSlotRealtime(gate)` & `useGateQueueRealtime(terminalId)`: subscribe
  reaktif ke `slot.{gateId}` / `gate.queue.{terminalId}`, event masuk → **`invalidateQueries`**
  (BUKAN menambal cache dari payload — payload broadcast subset; API tetap sumber kebenaran).
  `leave()` saat param ganti / unmount (cegah channel bocor). **`.listen('.slot.availability.changed')`
  — titik depan wajib** (nama `broadcastAs`, bukan kelas PHP). Dipakai di `SlotAvailabilityPage`
  (`gate`) & `GateDashboardPage` (`auth.user.terminal_id`). Test: `tests/js/useRealtime.test.ts`
  (Echo di-mock via `vi.mock('@/echo')`); page test mem-`vi.mock('@/composables/useRealtime')` jadi no-op.

---

## 3. Pola data: TanStack Query & mutation

**Query (baca):** tiap data domain punya composable `useXxx` yang membungkus `useQuery`:

```ts
// contoh useSlotAvailability — kunci ikut gate+date reaktif → ganti input auto-refetch
const query = useQuery({
    queryKey: ['slots-availability', gate, date],
    queryFn: () => fetchAvailability(gate.value as number, date.value),
    enabled: computed(() => typeof gate.value === 'number' && gate.value > 0),
});
```
- **`queryKey` reaktif** (memuat ref `gate`/`date`) → saat input berubah, TanStack
  refetch otomatis & meng-cache per kombinasi. Tak perlu watcher manual.
- **`enabled`** → query non-aktif sampai prasyarat terpenuhi (mis. gate dipilih) →
  tak menembak API dengan parameter kosong.
- **`staleTime`** lebih panjang untuk data jarang berubah (`useGates`, `useFleet` = 5 mnt)
  → kurangi refetch.

**Mutation (tulis):** `useMutation` + **invalidasi cache** di `onSuccess` → UI auto-segar
tanpa refetch manual. Contoh kunci konsistensi:

| Mutation | Invalidasi | Kenapa |
|----------|-----------|--------|
| book / cancel / reschedule | `['me-appointments']` + `['slots-availability']` | sisa kuota & daftar booking ikut berubah |
| gate-in / gate-out | `['gate-queue']` | baris pindah/keluar antrian |
| open / close window | `['utilization']` + `['slots-availability']` | window baru muncul / tertutup hilang |

> **Kenapa invalidasi, bukan update manual cache?** Server adalah sumber kebenaran
> (kuota dihitung di DB dengan lock). Invalidasi = refetch nilai otoritatif → tak ada
> risiko UI menebak angka kuota yang salah.

---

## 4. Halaman per persona + komponen

| Rute | File | Persona / izin | Fungsi & catatan "kenapa" |
|------|------|----------------|----------------------------|
| `/login` | `pages/LoginPage.vue` | publik | form login; map error server ke pesan |
| `/` | `pages/DashboardPage.vue` | semua | kartu profil + **nav per-izin** (`auth.can(...)`) |
| `/slots` | `pages/SlotAvailabilityPage.vue` | `slot.read` | dropdown gate (`useGates`) + tanggal → list window + sisa kuota; tombol **Booking** (bila `appointment.write`) buka `BookingForm` |
| — | `components/BookingForm.vue` | `appointment.write` | modal: truk/sopir dari `useFleet` (**hanya truk ACTIVE** — server yang menyaring), move_type/kontainer; kirim **Idempotency-Key** (`crypto.randomUUID`); map 409 `slot_unavailable`/`duplicate_booking`. Truk dinonaktifkan saat form terbuka → submit kena 422 `truck_inactive`; pesan server lolos lewat fallback `data.message` (tak perlu mapping khusus) |
| `/bookings` | `pages/MyBookingsPage.vue` | `appointment.write` | `GET /me/appointments` (filter status); **Batalkan** (konfirmasi 2-langkah) & **Pindah jadwal**; kirim `version` (optimistic lock). Tampilkan **Gate in/Gate out** begitu ada (2026-08-13 — endpoint ikut eager-load `gateIn`/`gateOut`, bukan halaman terpisah) |
| `/trucks` | `pages/MyTrucksPage.vue` | `fleet.manage` | CRUD armada truk company sendiri (`/me/trucks`): form create/edit dipakai bersama (`editingId != null` = mode edit), hapus konfirmasi 2-langkah; map 409 `entity_in_use` & 422. Menampilkan truk **ACTIVE + INACTIVE** (kebalikan dropdown booking) supaya truk nonaktif bisa diaktifkan lagi |
| — | `components/RescheduleDialog.vue` | — | modal pilih window tujuan (**reuse** `useGates`+`useSlotAvailability`); default ke gate/tanggal window saat ini; kirim `slot_window_id`+`version` |
| `/today` | `pages/DriverSchedulePage.vue` | `appointment.read.self` | jadwal hari-H sopir, urut jam, nama gate; per kartu render **QR gate-in** (`AppointmentQrCode`) bila `qr_token` ada |
| — | `components/AppointmentQrCode.vue` | — | render QR **di client** (canvas, lib `qrcode`) dari `qr_token` — backend tak pernah dikirimi/mengirim gambar utk jalur ini (nol beban storage, lihat `CODE-WALKTHROUGH §AA`). Prop `qrToken`; fallback pesan error kalau render gagal |
| `/gate` | `pages/GateDashboardPage.vue` | `gate.process` | antrian (`GET /gate/queue`); **Gate In** (CONFIRMED) / **Gate Out** (IN_PROGRESS) |
| `/planner` | `pages/PlannerWindowsPage.vue` | `slot.manage` | utilisasi window (`GET /reports/utilization`); form **buka window** + tombol **Tutup** |
| `/planner/gate-history` | `pages/GateHistoryPage.vue` | `slot.manage` (sama actor dgn backend `hasAnyRole(admin,planner)`) | riwayat gate-in/out per gate+tanggal (`GET /reports/gate-history`), **termasuk yang COMPLETED** (beda dari antrian `/gate`); urut jam gate-in **di klien** (repo sengaja tak sort kolom relasi) |
| `/reports` | `pages/MyUtilizationPage.vue` | `report.read` **+ punya company** | laporan company sendiri (`GET /me/reports/utilization`): selesai/no-show/batal/aktif per window + ringkasan; read-only (`useMyUtilization`, key `['my-utilization']` sengaja terpisah dari `['utilization']` planner — beda scope, tak boleh saling menimpa cache) |
| `/admin` | `pages/AdminPage.vue` | `terminal.manage` | **5-tab** master data (terminal/gate/company/user/**role**); form inline create/edit + hapus dgn konfirmasi. Tab **Role & Izin** (2026-08-13): checkbox grid permission per role (`GET/PUT /admin/roles`), role `admin` read-only — **bukan** CRUD role (lihat `CODE-WALKTHROUGH §V.6` kenapa) |
| — | `components/SkeletonRows.vue` | — | placeholder loading bersama (`rows`, `label`) — dipakai **12 titik** di 8 halaman + `RescheduleDialog` (`AdminPage` sendiri 4, satu per tab). Lihat catatan di bawah |

### Loading state — `SkeletonRows`

Semua state "sedang memuat" list memakai satu komponen, bukan teks `Memuat…`.
Alasannya bukan estetika: placeholder setinggi konten aslinya membuat halaman tidak
melompat saat data masuk (**layout shift**), dan bentuk yang seragam bikin loading
terasa sama di semua persona.

```vue
<SkeletonRows v-if="isLoading" :rows="4" label="Memuat antrian…" />
```

Dua hal yang sengaja dipertahankan:

* **Label tetap ada untuk pembaca layar.** Balok abu-abu tidak mengabarkan apa pun ke
  screen reader, jadi teks lama dipindah ke `<span class="sr-only">` di dalam
  `role="status" aria-busy="true"`; balok visualnya `aria-hidden`. Efek samping enak:
  test lama yang meng-assert teks `Memuat laporan` **tetap hijau** tanpa diubah.
* **`isFetching` di `/slots` TIDAK ikut jadi skeleton.** Itu indikator refetch latar
  saat data lama masih tampil — menggantinya dengan skeleton justru menyembunyikan
  data yang sudah benar. Skeleton hanya untuk `isLoading` (belum ada data sama sekali).

### Aksi mutasi — `ConfirmButton` + `Spinner` (2026-08-14)

Semua tombol yang mengubah/menghapus data (bukan cuma yang sudah lama punya pola manual)
memakai dua komponen bersama, bukan `confirm()` native atau teks polos "Memproses…":

```vue
<ConfirmButton
    :open="deletingId === item.id"
    :pending="remove.isPending.value"
    variant="danger"
    label="Hapus"
    confirm-label="Hapus item ini?"
    testid="delete-item"
    @update:open="(v) => (deletingId = v ? item.id : null)"
    @confirm="onDelete(item.id)"
/>
```

* **`open` dikontrol parent (`v-model`), bukan state internal komponen.** Supaya parent
  bisa menutup panel konfirmasi sendiri setelah mutasi sukses/gagal (`finally { x = null }`)
  — pola yang sama persis dengan yang sudah lama manual di `MyTrucksPage`/`MyBookingsPage`
  sebelum komponen ini ada, cuma digeneralisasi.
* **`data-testid`:** `testid` = tombol idle, `${testid}-confirm`/`${testid}-cancel` = dua
  tombol setelah dibuka. Test yang klik aksi 2-langkah (Gate In/Out, Tutup window, hapus
  master data) perlu **dua** `trigger('click')` berurutan.
* **`Spinner.vue`** dipakai berdiri sendiri (bukan lewat `ConfirmButton`) di tombol submit
  form yang tak butuh konfirmasi (booking, reschedule, simpan master data) — `aria-hidden`,
  status loading tetap diumumkan lewat teks tombol.
* Dipasang setelah audit menemukan 3 gap: delete di `AdminPage` (4 tab) yang tadinya
  `confirm()` native + tanpa loading state sama sekali, Gate In/Out di `GateDashboardPage`
  yang langsung eksekusi tanpa konfirmasi, dan Tutup window di `PlannerWindowsPage` yang
  juga langsung eksekusi. Detail: `HANDOVER.md` §Status `2026-08-14` (3).

### Admin master data — `useAdmin` + `AdminPage` (5 tab)

`AdminPage.vue` adalah satu halaman dengan **5 tab** (terminal · gate · company · user ·
**role**), 4 pertama CRUD lengkap inline (tanpa modal terpisah). Logikanya di
`composables/useAdmin.ts`: `useTerminals`, `useAdminGates`, `useCompanies`, `useUsers`
— tiap composable membungkus `useQuery` + tiga `useMutation` (create/update/remove).

Tab **Role & Izin** (2026-08-13) beda pola — bukan CRUD, cuma **edit permission** dari
5 role yang sudah ada (`useRoles`: `useQuery` + 1 `useMutation` `updatePermissions`,
sync bukan tambah). State centang lokal (`editing`, keyed per nama role) disinkronkan
ulang dari data server tiap query berubah lewat `watch(roleData, ...)` — bukan
`v-model` langsung ke data server, supaya centang yang belum di-"Simpan" tak
langsung memanggil API tiap klik. Role `admin` tampil tapi checkbox-nya `disabled`
dan tanpa tombol Simpan (server juga menolak 422 kalau dipaksa lewat API langsung —
lihat `CODE-WALKTHROUGH §V.6`).

```ts
// pola tiap entitas — query + mutation yang invalidasi key-nya sendiri
export function useTerminals() {
    const query = useQuery({ queryKey: ['admin-terminals'], queryFn: fetchTerminals, staleTime: 0 })
    const client = useQueryClient()
    const create = useMutation({
        mutationFn: createTerminal,
        onSuccess: () => client.invalidateQueries({ queryKey: ['admin-terminals'] }),
    })
    // update, remove serupa
    return { ...query, create, update, remove }
}
```
- **`staleTime: 0`** → master data selalu re-fetch saat dibuka (jarang berubah tapi harus
  akurat setelah edit); kontras dengan `useGates`/`useFleet` (5 mnt).
- **`useAdminRefs`** mengumpulkan terminal+company (`staleTime: 30_000`) untuk dropdown di
  form user, plus helper `roleNeedsTerminal`/`roleNeedsCompany` (gate-officer butuh terminal;
  transporter/driver butuh company) → form menampilkan field kondisional sesuai role.
- **Map error 409 `entity_in_use`** ke pesan "masih dipakai, hapus dependennya dulu".
- **Map 422 self-delete** (`/admin/users/{id}` diri sendiri) ke pesan larangan.

Catatan lintas-halaman:
- **Idempotency-Key** dikirim pada aksi mutasi rawan double-tap (booking, gate-in/out) —
  cocok dengan middleware idempoten di backend. Aksi gate juga *idempoten di level
  Action* (guard status), jadi aman walau key di-generate per klik.
- **Optimistic lock**: cancel & reschedule mengirim `version` dari `AppointmentResource`.
  Bila usang → 409 `version_conflict` → dipetakan ke pesan "muat ulang".
- **Urutan kronologis** (jadwal driver, antrian gate) dilakukan **di klien** by
  `start_time` — sengaja, karena repo backend tak mengurut kolom relasi (lihat
  CODE-WALKTHROUGH §U.2).
- **`SlotWindow.gate?`** opsional di tipe: hanya hadir saat backend eager-load gate
  (mis. jadwal driver, antrian gate) — `whenLoaded` di `SlotWindowResource`.

---

## 5. Pola test (Vitest)

Test di `tests/js/**/*.test.ts` (jsdom). Dua lapis:

**Fungsi `api/*`** — mock axios, verifikasi *kontrak request* + *unwrap*:
```ts
vi.mock('@/api/client', () => ({ api: { get: vi.fn(), post: vi.fn() } }));
// assert URL/params/headers + bentuk hasil
```

**Komponen/halaman** — **mock composable** jadi ref/spy terkontrol (bukan QueryClient/
jaringan), persis seperti `LoginPage` me-mock store:
```ts
const state = { windows: ref([]), isLoading: ref(false), enabled: ref(true), ... };
vi.mock('@/composables/useSlotAvailability', () => ({ useSlotAvailability: () => state }));
// mutation di-mock: { mutateAsync: vi.fn(), isPending: ref(false) }
```

Jebakan yang sudah ditemukan & dicatat:
- **Stub komponen anak** lewat `global.stubs` (`{ RouterLink: true, BookingForm: true,
  RescheduleDialog: true }`) → uji halaman tanpa memuat anaknya. Stub muncul sebagai
  `<nama-komponen-stub>`.
- **`setValue(number)` pada `<select>` ber-`v-model.number`** bekerja (memilih opsi
  ber-`:value` angka).
- **Submit form**: klik `<button type="submit">` **tidak** memicu `@submit` di jsdom →
  trigger `wrapper.find('form').trigger('submit.prevent')`.
- **`isAxiosError`** asli dipakai (rejected value cukup punya `isAxiosError: true`).
- **jsdom tak punya canvas 2D nyata** — `AppointmentQrCode.test.ts` mock modul `qrcode`
  (`vi.mock('qrcode', ...)`) dan cuma assert `toCanvas` dipanggil dengan token yang benar,
  bukan mencoba merender piksel sungguhan. Halaman yang memakainya (`DriverSchedulePage`)
  ikut mock `qrcode` supaya tak meledak walau tak menguji isi QR-nya.

---

## 6. Peta rute & navigasi

Rute (semua `requiresAuth` kecuali `/login`): `/login`, `/` (dashboard), `/slots`,
`/bookings`, `/trucks`, `/today`, `/gate`, `/planner`, `/planner/gate-history`, `/reports`,
`/admin`.

Navigasi lewat **dua tempat sekaligus**: kartu link di Dashboard (pintu masuk besar)
**dan** `AppNav` (navbar lintas-halaman, §4). Keduanya harus gating identik — 2026-08-13
ketahuan (dilaporkan user, dua kali di sesi yang sama) keduanya sempat **tak identik**:
`AppNav` sudah dibetulkan duluan lalu `DashboardPage` menyusul dengan pola yang sama.
Gating **bukan cuma** `auth.can(perm)` — admin punya SEMUA permission (`RolePermissionSeeder`)
tapi tak punya `company_id`/`terminal_id`/role `driver`, jadi 4 link butuh guard identitas
tambahan supaya tak tampil untuk admin walau endpoint di baliknya pasti 403/kosong:

| Link | Izin | Guard tambahan | Persona |
|------|------|-----------------|---------|
| Ketersediaan Slot | `slot.read` | — | transporter/planner/gate-officer |
| Booking Saya | `appointment.write` | `company_id != null` | transporter |
| Armada Truk | `fleet.manage` | `company_id != null` | transporter |
| Laporan Perusahaan | `report.read` | `company_id != null` | transporter |
| Jadwal Hari Ini | `appointment.read.self` | `hasRole('driver')` | driver |
| Dashboard Gate | `gate.process` | `terminal_id != null` | gate-officer |
| Kelola Slot | `slot.manage` | — | planner |
| Riwayat Gate | `slot.manage` | — | planner |
| Master Data | `terminal.manage` | — | admin |

> **Layout/nav bersama sudah ada** (2026-07-08): `AppLayout` + `AppNav` sebagai parent
> route — lihat §2 router. Kartu-kartu Dashboard tetap dipertahankan sebagai pintu masuk
> besar; navbar menjadi navigasi lintas-halaman. **Realtime (Laravel Echo) SUDAH disambung**
> (2026-07-25, lihat §2 `echo.ts`/`useRealtime`): event broadcast dari server memicu
> invalidasi query yang sama secara *live*. Invalidasi manual lewat mutation tetap jalan
> sebagai jaring pengaman bila Reverb mati (degradasi mulus).
