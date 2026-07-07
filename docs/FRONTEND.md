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

### `router/index.ts` — guard
`beforeEach`: bila ada token tapi user belum dimuat → `auth.restore()` (ambil /me);
rute `requiresAuth` tanpa auth → lempar ke `/login?redirect=`; rute `guestOnly` saat
sudah login → ke dashboard. Komponen halaman di-`import()` lazy (code-split per rute).

### `app.ts` — bootstrap
Pasang Pinia, Router, **VueQueryPlugin**, lalu wiring hook 401 (`setUnauthorizedHandler`
→ clearSession + push login). Shell HTML: `resources/views/app.blade.php` + catch-all
`routes/web.php` (`^(?!api).*$`) → semua path non-API mengembalikan SPA.

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
| — | `components/BookingForm.vue` | `appointment.write` | modal: truk/sopir dari `useFleet`, move_type/kontainer; kirim **Idempotency-Key** (`crypto.randomUUID`); map 409 `slot_unavailable`/`duplicate_booking` |
| `/bookings` | `pages/MyBookingsPage.vue` | `appointment.write` | `GET /me/appointments` (filter status); **Batalkan** (konfirmasi 2-langkah) & **Pindah jadwal**; kirim `version` (optimistic lock) |
| — | `components/RescheduleDialog.vue` | — | modal pilih window tujuan (**reuse** `useGates`+`useSlotAvailability`); default ke gate/tanggal window saat ini; kirim `slot_window_id`+`version` |
| `/today` | `pages/DriverSchedulePage.vue` | `appointment.read.self` | jadwal hari-H sopir, urut jam, nama gate |
| `/gate` | `pages/GateDashboardPage.vue` | `gate.process` | antrian (`GET /gate/queue`); **Gate In** (CONFIRMED) / **Gate Out** (IN_PROGRESS) |
| `/planner` | `pages/PlannerWindowsPage.vue` | `slot.manage` | utilisasi window (`GET /reports/utilization`); form **buka window** + tombol **Tutup** |
| `/reports` | `pages/MyUtilizationPage.vue` | `report.read` **+ punya company** | laporan company sendiri (`GET /me/reports/utilization`): selesai/no-show/batal/aktif per window + ringkasan; read-only (`useMyUtilization`, key `['my-utilization']` sengaja terpisah dari `['utilization']` planner — beda scope, tak boleh saling menimpa cache) |
| `/admin` | `pages/AdminPage.vue` | `terminal.manage` | **4-tab** master data (terminal/gate/company/user); form inline create/edit + hapus dgn konfirmasi |

### Admin master data — `useAdmin` + `AdminPage` (4 tab)

`AdminPage.vue` adalah satu halaman dengan **4 tab** (terminal · gate · company · user),
masing-masing CRUD lengkap inline (tanpa modal terpisah). Logikanya di
`composables/useAdmin.ts`: `useTerminals`, `useAdminGates`, `useCompanies`, `useUsers`
— tiap composable membungkus `useQuery` + tiga `useMutation` (create/update/remove).

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

---

## 6. Peta rute & navigasi

Rute (semua `requiresAuth` kecuali `/login`): `/login`, `/` (dashboard), `/slots`,
`/bookings`, `/today`, `/gate`, `/planner`, `/admin`.

Navigasi saat ini **hanya** lewat kartu link di Dashboard, masing-masing di-gate
`auth.can(perm)`:

| Link | Izin | Persona |
|------|------|---------|
| Ketersediaan Slot | `slot.read` | transporter/planner/gate-officer |
| Booking Saya | `appointment.write` | transporter |
| Jadwal Hari Ini | `appointment.read.self` | driver |
| Dashboard Gate | `gate.process` | gate-officer |
| Kelola Slot | `slot.manage` | planner |
| Master Data | `terminal.manage` | admin |

> **Belum ada layout/nav bersama** (sidebar) — kandidat polish berikutnya. Realtime
> (Laravel Echo) juga belum disambung: query masih di-invalidate manual lewat mutation;
> saat Reverb di-wire, event broadcast akan memicu invalidasi yang sama secara *live*
> (lihat `HANDOVER.md` → Jebakan & Langkah berikutnya).
