# ADR-0003 — Tunda penegakan token abilities Sanctum

**Status:** Accepted · 2026-06-28

## Context

Sanctum mendukung **token abilities** (scope per token) + middleware `abilities:` untuk
menolak request yang token-nya tak punya scope tertentu. Saat login, TAS sudah mencetak
token dengan abilities = **seluruh permission role** pengguna
(`createToken($name, $abilities)`).

Pertanyaannya: apakah memasang middleware `abilities:` di route?

Fakta yang relevan:
- Otorisasi sudah ditegakkan **dua lapis**: **permission** via `FormRequest::authorize()`
  (`can(...)`) + **Policy** (akses per record: `company_id`/`driver_id`/`terminal_id`).
- Tidak ada jalur yang menerbitkan token **ber-scope sempit** (mis. token mobile read-only).
  Token selalu = full-scope role.
- Di test, `Sanctum::actingAs($user)` default abilities-nya `[]` (bukan `['*']`).

## Decision

**Sengaja tidak** menegakkan token abilities lewat middleware untuk sekarang. Otorisasi
tetap lewat Policy + FormRequest `can()`. Ditegakkan **nanti**, saat aplikasi benar-benar
menerbitkan token ber-scope sempit.

> Ini **keputusan**, bukan utang diam-diam. Dicatat agar tidak dikira "lupa".

## Consequences

**Untung:**
- Menghindari lapisan **redundan**: selama token = full-scope role, `abilities:` tak pernah
  bisa menolak sesuatu yang Policy/permission belum tolak.
- Menghindari friksi test: memasangnya memaksa ~19 test `actingAs` mengirim `['*']` demi
  nol manfaat MVP.

**Rugi / risiko:**
- Bila kelak ada token sempit (mis. integrasi pihak ketiga, token perangkat terbatas) dan
  seseorang lupa ADR ini, scoping tak otomatis tertegak → **harus** diaktifkan saat itu.

## Kapan ditinjau ulang

Aktifkan penegakan abilities saat **salah satu** terjadi:
1. Aplikasi menerbitkan token ber-scope < full role (mobile read-only, token integrasi, dll.).
2. Ada kebutuhan membatasi blast-radius token yang bocor lebih ketat dari role.

Saat diaktifkan, buat ADR baru yang men-*supersede* ini + sesuaikan helper test. Lihat juga
`HANDOVER.md` → *Senior review (2026-06-28)* dan `CODE-WALKTHROUGH.md §S.4`.
