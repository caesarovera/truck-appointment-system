# ADR-0004 — Guard kolom state & kuota dari mass-assignment

**Status:** Accepted · 2026-07-07

## Context

Kontrak `CLAUDE.md §JANGAN` sudah lama berbunyi: *status appointment & kuota slot hanya
boleh berubah lewat Action ber-lock* (state machine `BUSINESS-FLOW §2`, race handling
§Hardening). Tapi penegakan kontrak itu selama ini **hanya konvensi**: kolom `status`,
`version`, `company_id` (Appointment) dan `booked_count`, `status` (SlotWindow) tetap ada
di `$fillable`.

Senior review 2026-06-28 menandai ini sebagai temuan #4 ("ranjau laten"): aman **hari ini**
karena semua Action/Repository menulis via property assignment eksplisit — tapi satu saja
kode baru yang memanggil `update($request->validated())` pada model ini akan membuka:
- klien mengubah `status` bebas → state machine bocor tanpa lock & tanpa Activity Log yang benar;
- klien menaikkan `version` → optimistic lock reschedule/cancel bisa dilangkahi;
- klien menulis `company_id` → appointment pindah tenant (bocor isolasi antar-company);
- `booked_count` ditulis langsung → kuota tak lagi konsisten dengan jumlah appointment.

Tarik-menarik: `$fillable` yang longgar itu nyaman (seeder & kode demo bisa `create([...])`
langsung), tapi kontrak yang hanya dijaga kedisiplinan reviewer akan kalah oleh waktu di
proyek multi-sesi.

## Decision

1. **Keluarkan kolom yang hanya boleh diubah Action dari `$fillable`:**
   - `Appointment`: `status`, `version`, `company_id` (company selalu diturunkan dari actor,
     bukan input klien).
   - `SlotWindow`: `booked_count`, `status`.
2. **Aktifkan `Model::preventSilentlyDiscardingAttributes()` di non-production**
   (`AppServiceProvider::boot()`, sejajar `preventLazyLoading`). Tanpa ini, mass-assign kolom
   guarded hanya *dibuang diam-diam* — bug-nya tersembunyi. Dengan ini, pelanggaran meledak
   jadi `MassAssignmentException` di dev/test, tapi produksi tidak pernah crash karenanya.
3. **Jalur tepercaya yang sengaja melewati Action memakai `forceFill()` eksplisit** —
   factory Eloquent memang unguarded by design (tak terpengaruh), `DemoSeeder` diubah ke
   `forceFill()` karena tugasnya menata *kondisi awal* lintas status, bukan menjalankan alur
   bisnis. `forceFill` membuat "bypass yang disengaja" terlihat jelas saat code review.

Regresi dijaga test `tests/Feature/Hardening/MassAssignmentGuardTest.php`.

## Consequences

**Untung:**
- Kontrak `JANGAN` kini ditegakkan **framework**, bukan cuma konvensi: `update($data)` /
  `create($data)` apa pun yang menyentuh kolom state/kuota langsung gagal keras di dev/test.
- `forceFill` menjadi penanda visual satu-satunya jalur bypass → mudah di-grep & di-review.

**Rugi / risiko:**
- Kode seeder/demo baru tidak bisa lagi `create()` polos untuk kolom ini — harus `forceFill`
  (friksi kecil, disengaja).
- `preventSilentlyDiscardingAttributes` bersifat global: kode lama yang diam-diam mengirim
  atribut liar ke model mana pun kini ketahuan (ini fitur, tapi bisa memunculkan error baru
  saat menulis fitur berikutnya — perbaiki dengan menyaring input, bukan mematikan guard).

## Kapan ditinjau ulang

Bila muncul kebutuhan admin/ops menulis kolom ini langsung (mis. koreksi data manual),
**jangan** kembalikan ke `$fillable` — buat Action khusus ber-lock + Activity Log, sesuai
kontrak yang sama.
