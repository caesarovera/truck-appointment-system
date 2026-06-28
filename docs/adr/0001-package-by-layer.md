# ADR-0001 — Organisasi folder package-by-layer (bukan package-by-feature)

**Status:** Accepted · 2026-06-28

## Context

Kode aplikasi (`app/`) bisa diorganisir dengan dua filosofi besar:

- **Package-by-layer** (dikelompokkan per peran teknis): `Actions/`, `Controllers/`,
  `Repositories/`, `Resources/`, dst. Satu fitur tersebar di banyak folder.
- **Package-by-feature** (dikelompokkan per domain/fitur): `Booking/`, `Gate/`, `Planner/`,
  masing-masing berisi semua lapisan fitur itu.

Gaya tarik-menarik:
- TAS adalah **proyek skill** yang sengaja menekankan **disiplin layer** & penanganan
  **konkurensi** (lihat `PRD.md`). Aturan layer di `CLAUDE.md` adalah inti pembelajarannya.
- Tim kecil, basis kode masih moderat (~130 file di `app/`), satu domain (1 terminal).
- Dibangun lintas sesi (vibe-coding) → struktur harus **menuntun** developer mengikuti
  aturan, bukan sekadar mengizinkan.

## Decision

Pakai **package-by-layer**. Tiap peran teknis punya folder sendiri di bawah `app/`
(`Actions`, `Contracts`, `Repositories`, `Http/{Controllers,Requests,Resources}`, `Events`,
`Listeners`, dll.). Pengelompokan per-fitur **hanya** sebagai sub-folder di dalam lapisan
saat satu area benar-benar besar — saat ini hanya `Admin/` (mis. `Actions/Admin/`,
`Http/Controllers/Api/V1/Admin/`).

## Consequences

**Untung:**
- Aturan arsitektur `CLAUDE.md` (HTTP → Business → Data) **terlihat dari struktur folder**.
- Tiap peran mudah ditemukan & diuji terisolasi; pola seragam antar-slice.
- Cocok dengan PHPStan lvl 8 + konvensi Laravel (auto-discovery Policy, dll.).

**Rugi:**
- Satu fitur tersebar di banyak folder (booking ada di `Actions`, `Http/*`, `Contracts`,
  `Repositories`). Navigasi per-fitur lebih lambat.
- Lebih banyak boilerplate dibanding MVC biasa.

**Risiko & mitigasi:** kemunculan sub-folder `Admin/` di beberapa lapisan adalah **sinyal
awal** bahwa struktur "menemukan fitur". Ini wajar dan belum perlu diapa-apakan.

## Kapan ditinjau ulang

Pertimbangkan ekstraksi ke modul (package-by-feature / pendekatan ala *spatie/laravel-domain*
atau `Modules/`) **hanya bila kedua syarat terpenuhi**:
1. Satu area fitur sudah punya **≥3 sub-folder lintas lapisan**, **dan**
2. Tim/kontributor bertambah (navigasi per-fitur jadi hambatan nyata).

Memodularisasi sebelum sinyal ini = over-engineering; sesudahnya = utang teknis. Saat
ditinjau, buat ADR baru yang men-*supersede* ADR ini.
