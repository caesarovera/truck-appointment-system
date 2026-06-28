# docs/ARCHITECTURE.md — Arsitektur Direktori & Aliran Lapisan

> **Apa ini:** peta arsitektur *real* TAS — pola yang dipakai, struktur folder aktual,
> aturan aliran dependensi, dan trace 1 request menembus semua lapisan.
> **Bedanya dengan dokumen lain:** `CLAUDE.md` = **aturan** ("apa yang harus"); dokumen
> ini = **gambaran besar + alasan struktural** ("bagaimana semua terhubung"); `docs/adr/`
> = **keputusan beralasan** ("kenapa dulu dipilih begini"). `CODE-WALKTHROUGH.md` membedah
> tiap file; ini menjelaskan *bentuk*-nya.

---

## 1. Pola yang dipakai (ringkas)

TAS = **Layered Architecture** yang diorganisir **package-by-layer** (dikelompokkan per
*peran teknis*, bukan per fitur), diwujudkan dengan kombinasi:

| Lapisan | Pola | Peran |
|---------|------|-------|
| HTTP | **ADR** (Action-Domain-Responder) | terima request, validasi+izin, bentuk output |
| Business | **Action/Command** + **Event-driven** | logika 1-tugas + efek samping via event |
| Data & Integrasi | **Repository** + **Ports & Adapters** (Hexagonal) | akses data + seam ke sistem eksternal |

Ini **bukan** MVC klasik (controller gemuk + Eloquent langsung). Keputusan & alasannya
direkam di **[`docs/adr/`](adr/)**.

---

## 2. Struktur folder aktual

```
app/
├── Http/                          ◄── LAPISAN HTTP (ADR)
│   ├── Controllers/Api/V1/        controller INVOKABLE (1 file = 1 endpoint)
│   │   ├── Admin/                 CRUD master data
│   │   └── Auth/                  login / logout / me
│   ├── Requests/V1/               Form Request — gerbang input (validasi + authorize)
│   │   └── Admin/
│   ├── Resources/V1/              API Resource — "Responder" (bentuk JSON keluar)
│   └── Middleware/                IdempotencyKey
│
├── Actions/                       ◄── LAPISAN BUSINESS (Command pattern)
│   ├── BookAppointmentAction ...  Action domain — nama kata kerja, 1 tanggung jawab
│   └── Admin/                     Action CRUD
├── DataTransferObjects/           DTO (Spatie Data) — input bertipe, lepas dari HTTP
│   └── Admin/
├── Events/                        domain event (model penuh)
│   └── Broadcasting/              event WebSocket (payload datar, kontrak FE)
├── Listeners/                     reaksi atas event: cache, broadcast, job, TOS, reminder
├── Enums/                         5 enum; AppointmentStatus = state machine
│
├── Contracts/                     ◄── LAPISAN DATA — PORTS (interface)
│   ├── *RepositoryInterface       kontrak akses data
│   ├── GateEventGateway           port ke sistem eksternal (TOS)
│   └── AffectsSlotAvailability,   marker interface → satu listener tangkap banyak event
│       RecordsGateEvent
├── Repositories/                  impl Eloquent  ── ADAPTER dari repo port
├── Services/                      LoggingGateEventGateway ── ADAPTER dari GateEventGateway port
├── Models/                        Eloquent model + relasi + cast
│
├── Jobs/                          NoShowSweep, Reminder, ProcessGateEvent (queue)
├── Notifications/                 AppointmentReminderNotification
├── Policies/                      AppointmentPolicy (boleh-tidaknya akses record)
├── Exceptions/                    exception domain ber-render() → HTTP status
└── Providers/                     AppServiceProvider (binding port→adapter, rate limiter)
```

> Versioning **dibakar ke folder**: `Controllers/Api/V1`, `Requests/V1`, `Resources/V1`.
> Versi baru = folder `V2` baru; V1 tak dimutasi.

---

## 3. Aturan aliran dependensi (ditegakkan)

```
HTTP ──boleh panggil──► Business ──boleh panggil──► Data
  ▲                                                   │
  └──────────  TIDAK boleh terbalik  ◄────────────────┘
```

- **Controller tidak boleh query DB** — harus lewat Action → Repository.
- **Action tidak tahu HTTP** — menerima **DTO**, bukan `Request`; bisa dipanggil dari job/
  command/test.
- **Repository tidak tahu Action/Controller** — murni akses data.
- **Efek samping keluar lewat Event**, bukan dipanggil di tengah Action/`DB::transaction`.

Penegak: `Model::preventLazyLoading()` (dev/test) + **PHPStan level 8** + review kontrak
`CLAUDE.md`. Pelanggaran layer biasanya muncul sebagai error tipe/lazy-load saat ngoding.

---

## 4. Trace 1 request: `POST /api/v1/appointments` (booking)

Bagaimana satu request menembus semua lapisan (file nyata):

```
routes/api.php
  └─ middleware: auth:sanctum, throttle:booking, idempotency
       │
HTTP   ├─ Http/Requests/V1/BookAppointmentRequest   authorize() + rules() → toData() (DTO)
       ├─ Http/Controllers/Api/V1/BookAppointmentController   __invoke(): panggil Action, balikan Resource
       │
BUS    ├─ Actions/BookAppointmentAction   ◄── INTI
       │     DB::transaction(attempts: 3) {
       │        SlotRepository->lockForUpdate(...)   ← LOCK baris slot
       │        cek penuh/tutup → throw Exceptions\SlotUnavailableException (409)
       │        AppointmentRepository->createConfirmed(...)
       │        SlotRepository->incrementBooked(...)
       │     }
       │     Events\AppointmentBooked::dispatch()   ← SETELAH commit
       │
DATA   ├─ Repositories/SlotRepository, AppointmentRepository   (impl dari Contracts/*)
       │
HTTP   └─ Http/Resources/V1/AppointmentResource   bentuk JSON (201)

(async) Events\AppointmentBooked
   ├─ Listeners/InvalidateSlotAvailabilityCache   buang cache ketersediaan
   └─ Listeners/ScheduleAppointmentReminder       jadwalkan Jobs/AppointmentReminderJob
```

Pola ini **berulang** di tiap slice (gate-in/out, cancel, reschedule, admin CRUD). Kuasai
satu, paham semua — lihat `docs/ONBOARDING.md §6` & `CODE-WALKTHROUGH.md §J`.

---

## 5. Ports & Adapters (bagian paling "arsitektural")

`Contracts/` = **port** (interface) · `Repositories/` + `Services/` = **adapter** (impl).
Disuntik lewat binding di `AppServiceProvider` (**Dependency Inversion**):

```
Contracts/SlotRepositoryInterface  ──bind──►  Repositories/SlotRepository       (Eloquent)
Contracts/GateEventGateway         ──bind──►  Services/LoggingGateEventGateway   (placeholder TOS)
```

- **Repo port** → bisa di-mock saat test; query lepas dari controller.
- **`GateEventGateway` port** → seam ke Terminal Operating System. Adapter sekarang hanya
  nge-log; saat TOS riil tiba, **cukup ganti binding** — Action/Job tak berubah.
- **Marker interface** (`AffectsSlotAvailability`, `RecordsGateEvent`): beberapa event meng-
  implement-nya; satu listener menangkap *semua* event ber-interface itu via type-hint →
  tambah event baru otomatis ikut listener cache/broadcast tanpa wiring ulang.

Alasan repo-di-belakang-interface direkam di **[ADR-0002](adr/0002-repository-interface.md)**.

---

## 6. Frontend mirror (`resources/js/`)

SPA Vue mengikuti package-by-layer yang sejajar semangat backend:

```
types/        kontrak tipe (bentuk respons API)
api/          fungsi fetch tipis (axios) — buka bungkus `data`
composables/  state server via TanStack Query (useQuery/useMutation)
pages/ + components/   render + panggil composable (tanpa axios langsung)
stores/       Pinia (khusus state auth: token+user)
router/       Vue Router + guard
```

Detail: `docs/FRONTEND.md`.

---

## 7. Trade-off (sadar dipilih)

| Untung | Rugi |
|--------|------|
| Tiap peran punya tempat jelas → mudah dicari, dites terisolasi | Satu fitur tersebar di banyak folder |
| Aliran satu arah → kebocoran layer ketahuan (PHPStan lvl 8) | Lebih banyak file/boilerplate dari MVC |
| Port & adapter → integrasi eksternal swappable | Kurva belajar lebih curam untuk junior |
| Action ber-lock terpusat → race-handling teruji | — |

**Alternatif yang sengaja tidak dipakai:** *package-by-feature* (folder `app/Booking/`
berisi semua lapisan fitur itu). Untuk proyek yang menekankan disiplin layer & konkurensi,
package-by-layer membuat aturan arsitektur terlihat dari struktur folder. Alasan & **kapan
ini perlu ditinjau ulang** ada di **[ADR-0001](adr/0001-package-by-layer.md)**.

---

## 8. Bacaan terkait
- **Aturan** non-negotiable: `CLAUDE.md` (*Aturan layer*).
- **Keputusan** beralasan: [`docs/adr/`](adr/).
- **Detail kode** per file: `docs/CODE-WALKTHROUGH.md` (backend) · `docs/FRONTEND.md` (SPA).
- **Jalur belajar** developer baru: `docs/ONBOARDING.md`.
