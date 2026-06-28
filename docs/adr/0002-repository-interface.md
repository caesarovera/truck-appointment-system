# ADR-0002 — Akses data lewat Repository ber-interface (Ports & Adapters)

**Status:** Accepted · 2026-06-28

## Context

Action dan controller butuh membaca/menulis data. Opsi:

- **A. Eloquent langsung** di Action/Controller (`SlotWindow::where(...)->...`).
- **B. Repository konkret** (class tanpa interface).
- **C. Repository ber-interface** (`Contracts/*RepositoryInterface` + impl Eloquent),
  di-bind di `AppServiceProvider`.

Gaya tarik-menarik:
- `CLAUDE.md` melarang **query/logika di controller** dan mewajibkan akses data terpusat.
- Inti proyek = konkurensi (booking ber-`lockForUpdate`, transaksi). Logika ini perlu
  **diuji terisolasi** tanpa menyentuh HTTP.
- Ada efek eksternal (push ke Terminal Operating System) yang **belum** ada implementasi
  riilnya — perlu seam yang bisa di-swap.

## Decision

Semua akses data lewat **Repository ber-interface**. Interface hidup di `app/Contracts/`,
implementasi Eloquent di `app/Repositories/`, di-bind (port → adapter) di
`AppServiceProvider`. Pola yang sama dipakai untuk **seam eksternal**: `GateEventGateway`
(port) → `LoggingGateEventGateway` (adapter, di `app/Services/`).

Contoh kontrak: `SlotRepositoryInterface`, `AppointmentRepositoryInterface`,
`Gate/Fleet/Terminal/Company/UserRepositoryInterface`.

## Consequences

**Untung:**
- Action bergantung pada **kontrak**, bukan implementasi → mudah di-mock saat test.
- Query lepas dari controller (memenuhi `CLAUDE.md`).
- Seam eksternal (TOS) bisa di-ganti tanpa menyentuh Action/Job — cukup ganti binding.
- Konsisten: satu cara mengakses data di seluruh basis kode.

**Rugi:**
- Lebih banyak file (interface + impl + binding) untuk operasi yang sederhana sekalipun.
- Untuk read murni (mis. `GateRepository::all()`) terasa "berlebihan" dibanding query
  langsung — diterima demi konsistensi & kemampuan mock.

**Catatan:** ini *bukan* CQRS penuh. Read & write sama-sama lewat repo; yang membedakan,
write yang menyentuh kuota/status dibungkus Action ber-lock + transaksi, read tidak.

## Kapan ditinjau ulang

Bila beban boilerplate untuk read sederhana terbukti menghambat tanpa memberi nilai (mis.
banyak repo read yang tak pernah di-mock), pertimbangkan pengecualian terbatas untuk
*query object* read-only. Tidak mengubah aturan untuk write ber-lock.
