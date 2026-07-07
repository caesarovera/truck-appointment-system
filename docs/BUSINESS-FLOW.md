# docs/BUSINESS-FLOW.md — truck-appointment-system

Dokumen ini detail dari `CLAUDE.md` dan menjawab **apa**-nya TAS. Konteks & batas scope (kenapa) ada di `docs/PRD.md`. Struktur: **§1** aktor & hak akses (RBAC) · **§2** state machine appointment · **§3** alur bisnis tiap proses · **§4** entitas inti (ERD).

---

## 1. Aktor & Akun (5 role)

| Role | Akun demo | Cakupan data | Inti tugas |
|------|-----------|--------------|------------|
| **System Admin** | `admin@tas.test` | Semua | Kelola user, master data, lihat semua audit |
| **Slot Planner** | `planner@tas.test` | Semua slot/terminal | Buka/tutup jendela slot, atur kuota, monitor utilisasi |
| **Gate Officer** | `gate@tas.test` | Terminal yang ditugaskan | Gate-in, gate-out, tandai no-show, lihat antrian hari ini |
| **Transporter Admin** | `dispatcher@majulog.test` · `dispatcher@sinarkargo.test` | **Hanya company sendiri** | Booking, reschedule, cancel, kelola truk & sopir |
| **Driver** | `budi@majulog.test` · `andi@sinarkargo.test` | **Hanya appointment yang di-assign ke dirinya** | Lihat jadwal hari ini, kode booking/QR, status gate |

Password semua akun demo: `password`.

### Sanctum scope per role
> Ini sumber kebenaran RBAC. `RolePermissionSeeder` & Policy **wajib** mencerminkan daftar ini persis.
```
admin        → * (semua permission, termasuk master data di bawah)
planner      → slot.manage, slot.read, appointment.read, appointment.override, report.read, audit.read
gate-officer → gate.process, appointment.read, slot.read
transporter  → appointment.write, appointment.read, fleet.manage, slot.read, report.read, audit.read
driver       → appointment.read.self
```
> **Permission master data (admin-only).** CRUD master data ditegakkan oleh permission
> khusus: `user.manage`, `terminal.manage`, `gate.manage`, `company.manage`. Keempatnya
> hanya dimiliki `admin` (lewat `admin → *`). Endpoint `/api/v1/admin/*` mengeceknya di
> FormRequest. Hapus entitas ditolak **409** bila masih ada dependen (terminal→gate,
> gate→slot window, company→user/appointment, user→diri sendiri).
> Catatan: untuk **transporter**, `appointment.read`/`report.read`/`audit.read` dibatasi ke company sendiri lewat **Policy**, bukan lewat scope.
> **Self-service vs override:** `appointment.write` (transporter) = booking/reschedule/cancel atas nama company sendiri. `appointment.override` (planner/admin) = intervensi administratif reschedule/cancel — mis. saat window ditutup mendadak — **selalu dicatat Activity Log** dan boleh lintas-company. Keduanya memakai Action yang sama (`RescheduleAppointmentAction`/`CancelAppointmentAction`); yang membedakan hanya otorisasi di Policy.

### Matriks akses (ringkas)

| Kemampuan | Admin | Planner | Gate | Transporter | Driver |
|-----------|:--:|:--:|:--:|:--:|:--:|
| Kelola user & role | ✅ | — | — | — | — |
| CRUD master data (terminal/gate/company) | ✅ | — | — | — | — |
| Buka/tutup slot window & set kuota | ✅ | ✅ | — | — | — |
| Lihat ketersediaan slot | ✅ | ✅ | ✅ | ✅ (read) | — |
| Booking appointment | ✅ | — | — | ✅ (company sendiri) | — |
| Reschedule / cancel appointment | ✅ | ⚠️ override¹ | — | ✅ (company sendiri) | — |
| Assign sopir & truk ke appointment | ✅ | — | — | ✅ | — |
| Gate-in / gate-out | ✅ | — | ✅ | — | — |
| Tandai no-show | ✅ (auto job) | — | ✅ | — | — |
| Lihat jadwal pribadi + QR | — | — | — | — | ✅ |
| Lihat audit log | ✅ | sebagian | — | company sendiri | — |
| Laporan utilisasi | ✅ | ✅ | — | company sendiri | — |

Aturan kritikal yang harus ditegakkan Policy/Gate Laravel:
- Transporter **tidak boleh** membaca/mengubah appointment milik company lain (`AppointmentPolicy` cek `company_id`).
- Driver hanya melihat appointment di mana `driver_id = auth()->id()`.
- Gate Officer hanya boleh proses appointment di terminal tempat ia ditugaskan.
- ¹ Planner **tidak** punya `appointment.write` (booking self-service). Reschedule/cancel oleh planner hanya lewat `appointment.override` — administratif, boleh lintas-company, **wajib teraudit**. Bukan untuk operasional harian.

---

## 2. State Machine Appointment

```
                 book
   (none) ─────────────────▶ BOOKED
                               │  confirm (opsional, auto bila kuota & dok valid)
                               ▼
                           CONFIRMED
            ┌──────────────────┼──────────────────┐
   reschedule│            gate-in│           cancel │ / no-show (job)
            ▼                  ▼                    ▼
        CONFIRMED           ARRIVED            CANCELLED / NO_SHOW
        (version++)            │  start handling
                               ▼
                          IN_PROGRESS
                               │  gate-out
                               ▼
                           COMPLETED
```

Status final (tidak bisa transisi lagi): `COMPLETED`, `CANCELLED`, `NO_SHOW`.

Aturan transisi (tegakkan di Action, bukan di Controller):
- `BOOKED/CONFIRMED → CANCELLED`: hanya sebelum gate-in. **Kuota dikembalikan** (`booked_count--` dalam transaksi).
- `CONFIRMED → ARRIVED`: hanya Gate Officer, hanya pada hari & window yang sesuai (boleh ada toleransi early/late dari config).
- `CONFIRMED → NO_SHOW`: otomatis oleh `NoShowSweepJob` setelah `window.end + grace_period`. **Kuota dikembalikan.**
- Reschedule = pindah ke slot window lain: lepas kuota window lama, ambil kuota window baru, `version++`. Keduanya dalam **satu** transaksi ber-lock.

---

## 3. Alur Bisnis per Proses

### 3.1 Planner membuka jendela slot
1. Planner pilih terminal + gate + tanggal, set `capacity` per jam (mis. 20 truk/jam, 06:00–22:00).
2. `OpenSlotWindowAction` membuat baris `slot_windows` (`booked_count = 0`).
3. Event `SlotWindowOpened` → invalidate `Cache::tags(['slot', "gate:{$gateId}"])`.
4. Window langsung muncul di endpoint ketersediaan & broadcast ke channel `slot.{gateId}`.
> Edge: planner menutup window → `CloseSlotWindowAction` set `status = CLOSED`, BUKAN delete. Appointment existing tetap valid, booking baru ditolak. Bila window batal total (mis. RTG down), planner pakai `appointment.override` untuk reschedule/cancel massal appointment terdampak — setiap aksi tercatat di Activity Log.

### 3.2 Transporter booking appointment (alur INTI race condition)
1. Transporter Admin lihat `GET /api/v1/slots/availability?gate=&date=` (data dari `Cache::flexible`).
2. Pilih window, isi: move type (`DELIVERY`/`RECEIVAL`), nomor kontainer, truk, sopir.
3. `POST /api/v1/appointments` dengan header `Idempotency-Key`.
4. `BookAppointmentAction` dalam `DB::transaction(attempts:3)`:
   a. `SlotWindow::where(id)->lockForUpdate()->first()`.
   b. Jika `booked_count >= capacity`, `status != OPEN`, atau window **sudah berakhir** (`date+end_time` lewat; window berjalan masih boleh) → tolak `409 Conflict`.
   c. Buat appointment `status = BOOKED`, `booked_count++`.
   d. (Validasi dokumen lolos → langsung `CONFIRMED`.)
5. Commit → Event `AppointmentBooked` → listener: kirim notifikasi ke sopir, jadwalkan `AppointmentReminderJob` (H-2 jam), invalidate cache, broadcast sisa kuota.
> Dua transporter berebut slot terakhir: hanya satu lolos karena `lockForUpdate`; yang lain dapat `409`. Unique constraint `(slot_window_id, container_no)` aktif sebagai jaring terakhir.

### 3.3 Transporter reschedule / cancel
- **Reschedule:** kirim `version` terakhir. `RescheduleAppointmentAction` lock kedua window (lama & baru), cek `version` cocok (kalau tidak → `409` optimistic lock gagal), window tujuan harus belum berakhir (guard yang sama dengan booking), pindahkan kuota, `version++`. Event `AppointmentRescheduled` → reminder lama dibatalkan, reminder baru dijadwalkan.
- **Cancel:** hanya sebelum `ARRIVED`. Kuota window dikembalikan dalam transaksi ber-lock. Event `AppointmentCancelled`.

### 3.4 Driver di hari-H
1. Driver buka app → `GET /api/v1/me/appointments/today` (scope `appointment.read.self`).
2. Lihat kode booking + QR (berisi appointment id ter-sign), window, gate, status realtime via Echo `gate.queue.{terminalId}`.
3. Tiba di gate, tunjukkan QR.

### 3.5 Gate Officer: gate-in
1. Scan QR / input kode booking → `GET /api/v1/appointments/{id}` (cek terminal cocok).
2. Verifikasi truk & kontainer fisik.
3. `POST /api/v1/appointments/{id}/gate-in` + `Idempotency-Key`.
4. `GateInAction`: cek state `CONFIRMED` & window valid (toleransi early/late) → buat `gate_transactions` (`type=IN`), status → `ARRIVED`. Saat proses bongkar/muat dimulai di dalam terminal, status → `IN_PROGRESS` (sesuai §2). Untuk MVP keduanya boleh terjadi berurutan dalam satu aksi.
5. Event `TruckGatedIn` → broadcast antrian, `ProcessGateEventJob` push ke TOS terminal (idempoten, cek state).
> No-show: kalau driver tak datang sampai `window.end + grace`, `NoShowSweepJob` set `NO_SHOW` & balikin kuota — gate-in setelah itu ditolak.

### 3.6 Gate Officer: gate-out
1. Setelah bongkar/muat selesai → `POST /api/v1/appointments/{id}/gate-out`.
2. `GateOutAction`: cek state `IN_PROGRESS` → `gate_transactions` (`type=OUT`), status → `COMPLETED`, hitung `dwell_time`.
3. Event `TruckGatedOut` → broadcast, update metrik utilisasi.

### 3.7 Monitoring & audit
- Planner: `GET /api/v1/reports/utilization?gate=&date=` → kuota vs terpakai vs no-show.
- Semua perubahan status & gate event tercatat lewat **Spatie Activity Log** (sumber kebenaran audit trail). Transporter hanya lihat log company sendiri.

---

## 4. Entitas inti (untuk ERD di Claude Code)

- `users` (Spatie roles) — gate officer punya `terminal_id`; driver punya `company_id`.
- `transport_companies` (perusahaan angkutan)
- `trucks` (`company_id`, `plate_no`, `status`)
- `drivers` → bisa jadi `users` ber-role driver, FK `company_id`
- `terminals`, `gates` (`terminal_id`)
- `slot_windows` (`gate_id`, `date`, `start_time`, `end_time`, `capacity`, `booked_count`, `status`)
- `appointments` (`company_id`, `truck_id`, `driver_id`, `slot_window_id`, `move_type`, `status`, `version`, `booking_code`)
- `containers` (`appointment_id`, `slot_window_id` *(nullable, denormalisasi)*, `container_no`, `iso_type`, `size`)
  - Unik `(slot_window_id, container_no)` = pertahanan terakhir anti double-booking kontainer di satu window. Saat cancel/no-show, `slot_window_id` di-NULL-kan untuk melepas slot (NULL ganda diizinkan). Lihat hardening Idempotency di `CLAUDE.md`.
- `gate_transactions` (`appointment_id`, `type` IN/OUT, `processed_by`, `processed_at`)
- `activity_log` (Spatie)
