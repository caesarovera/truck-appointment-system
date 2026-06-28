---
name: slice
description: >
  Bangun satu fitur truck-appointment-system lengkap lintas layer sesuai CLAUDE.md,
  dengan test. Gunakan: /slice <nama-fitur>
  Contoh: /slice gate-in  /slice reschedule-appointment
---

Bangun fitur "$ARGUMENTS" secara berurutan dan uji tiap langkah.

## Urutan wajib

1. **Pest test DULU** — tulis test sebelum implementasi:
   - Happy path
   - Edge cases: kuota penuh → 409, Idempotency-Key double submit,
     optimistic lock version basi → 409, akses lintas-company → 403

2. **Action** — 1 tugas, nama kata kerja (`VerbNounAction`).
   Gunakan `lockForUpdate()` + `DB::transaction()` bila menyentuh
   `booked_count` atau status appointment.

3. **Event + Listener** — semua side-effect (notif, cache invalidate,
   broadcast Reverb) keluar dari Action via Event. Jangan dispatch
   job di dalam `DB::transaction()`.

4. **Form Request + DTO** (Spatie Laravel Data) — validasi &
   transformasi input, bukan array mentah.

5. **Controller** (invokable) — hanya panggil Action →
   kembalikan Resource. Tanpa query, tanpa logika.

6. **API Resource** — output lewat Resource, relasi pakai
   `whenLoaded()` / `whenCounted()`.

7. **Policy** — tegakkan `company_id` (transporter), `driver_id`
   (driver), `terminal_id` (gate-officer), `appointment.override`
   (planner intervensi + Activity Log).

8. **Route** — di bawah `/api/v1/`, scope middleware sesuai
   `docs/BUSINESS-FLOW.md §1`.

## Setelah selesai

```bash
composer test --parallel   # semua hijau
composer analyse           # PHPStan 8, bersih
composer fix               # Pint
```

## Tidak boleh

- Tambah package tanpa konfirmasi
- Ubah migrasi yang sudah jalan
- Skip test
- Akses relasi Eloquent langsung di Resource (pakai `whenLoaded`)
