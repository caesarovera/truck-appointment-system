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
docker compose exec app composer test      # pest --parallel (flag sudah di script — jangan tambah)
docker compose exec app composer analyse   # PHPStan level 8, bersih
docker compose exec app composer fix       # Pint
```

(Jalur native Windows `composer test` juga sah — lihat `CLAUDE.md` §Stack.)

Bukti, bukan sekadar hijau:
- **Test penjaga harus menggigit** — rusak kode yang dijaganya, pastikan test yang tepat merah, pulihkan.
- Test yang merender view `@vite` harus lulus **tanpa** `public/build/` (job Backend CI tak build frontend).
- Setelah push: `gh run watch <run-id> --exit-status` sampai hijau.

Kalau slice-nya menyentuh SPA, gerbang frontend ikut wajib — CI menjalankan
ketiganya juga (`.github/workflows/ci.yml`):

```bash
npm run test:js      # Vitest
npm run type-check   # vue-tsc --noEmit
npm run build        # error yang cuma muncul saat bundling
```

## Tidak boleh

- Tambah package tanpa konfirmasi
- Ubah migrasi yang sudah jalan
- Skip test
- Akses relasi Eloquent langsung di Resource (pakai `whenLoaded`)
