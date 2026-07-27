# ADR-0006 — Sopir dikelola admin; transporter tidak dapat CRUD sopir di MVP

**Status:** Accepted · 2026-07-27

## Context

`PRD §3` (IN scope) menjanjikan transporter *"kelola truk **& sopir** (company sendiri)"*.
Bagian **truk** selesai 2026-07-25 (`/me/trucks`, lihat `CODE-WALKTHROUGH §W`). Bagian
**sopir** menggantung dengan satu pertanyaan produk yang belum dijawab: transporter boleh
menerbitkan akun sendiri (undangan? password sementara?) atau cukup admin yang membuat?

Fakta yang relevan:

- **Sopir bukan entitas tersendiri.** Sopir = `User` ber-role `driver` (`BUSINESS-FLOW §1`,
  §4). Jadi "CRUD sopir untuk transporter" **bukan** CRUD data master seperti truk — itu
  **penerbitan akun login**: email, password, assign role, `company_id`.
- **Kemampuannya sudah ada.** Admin User CRUD (`/api/v1/admin/users`, `ADR §V`) sudah bisa
  membuat user ber-role `driver` + `company_id`, lengkap dengan guard hapus (409/422) dan
  password hash-on-change.
- **Sisi baca transporter sudah lengkap.** `GET /me/fleet` mengembalikan sopir company
  sendiri (`driversForCompany()` menyaring `->role('driver','api')`). Yang tak ada hanya
  create/update/delete.
- **Polanya tidak baru.** CRUD truk sudah mendemonstrasikan company-scoped CRUD secara utuh:
  `company_id` selalu dari token, unik per company, 409 `entity_in_use`. CRUD sopir akan
  mengulang pola yang sama persis.
- **Risikonya baru.** Transporter yang bisa membuat baris `User` = permukaan privilege
  escalation. Role **wajib** dipaksa `driver` dan `company_id` **wajib** dari token; satu
  kelalaian (mis. menerima `role` dari body seperti Admin User CRUD melakukannya) langsung
  jadi lubang lintas-tenant. Ditambah keputusan yang belum ada jawabannya: alur undangan,
  password sementara, reset password, dan siapa yang menonaktifkan akun sopir yang keluar.

## Decision

**CRUD sopir untuk transporter tidak dibangun di MVP.** Sopir dibuat & dikelola **admin**
lewat Admin User CRUD yang sudah ada. Transporter tetap dapat **melihat** sopirnya
(`GET /me/fleet`) untuk mengisi form booking.

`PRD §3` dikoreksi agar tidak lagi menjanjikan yang tidak dibangun — sesuai aturan PRD itu
sendiri: *"apa pun di luar daftar IN tidak dikerjakan tanpa memperbarui PRD ini lebih dulu"*.
Aturan itu berlaku dua arah; **mencoret** sesuatu dari IN juga menuntut PRD diperbarui, bukan
dibiarkan jadi janji yang membusuk.

> Ini **keputusan**, bukan utang diam-diam — pola pencatatan yang sama dengan
> [ADR-0003](0003-defer-token-abilities.md) & [ADR-0005](0005-ci-github-actions.md).

## Consequences

**Untung:**
- Tidak menambah permukaan penerbitan akun sebelum ada alur undangan/reset password yang
  dipikirkan matang.
- Tidak ada pola arsitektur baru yang hilang: company-scoped CRUD sudah terbukti di truk.
- Kontrak (`PRD`) kembali jujur — dokumen yang menjanjikan fitur tak ada adalah penyakit
  yang sama dengan klaim CI/Docker yang dibersihkan 2026-07-25.

**Rugi / risiko:**
- **Bottleneck operasional:** tiap sopir baru butuh admin. Untuk 1 terminal + segelintir
  perusahaan angkutan (batas MVP, `PRD §3`) masih wajar; tidak lagi wajar begitu onboarding
  perusahaan angkutan jadi rutin.
- Transporter tak bisa menonaktifkan akun sopir yang berhenti kerja — harus lewat admin.
  Mitigasi jangka pendek: appointment tetap terisolasi per `company_id`, dan sejak
  2026-07-27 `driver_id` wajib ber-role `driver` (422 `driver_invalid_role`), jadi akun yang
  role-nya sudah dicabut admin **langsung** tak bisa dijadwalkan lagi.

## Kapan ditinjau ulang

Bangun CRUD sopir untuk transporter saat **salah satu** terjadi:

1. **Onboarding jadi rutin** — permintaan "tambah sopir" ke admin muncul cukup sering
   sampai terasa sebagai antrian, bukan kejadian sesekali.
2. **Sudah ada mekanisme akun yang matang** — undangan via email atau reset password
   mandiri. Selama belum ada, self-service hanya memindahkan masalah password ke pihak
   yang tak siap memegangnya.
3. **Batas MVP "1 terminal" dilewati** (`PRD §3` → OUT: multi-terminal), karena skala itu
   membuat admin terpusat tak lagi realistis.

Saat dibangun, syarat minimum yang **tidak boleh** dinegosiasikan: `role` dipaksa `driver`
di server (jangan pernah dari body), `company_id` dari token, dan test lintas-company
untuk create/update/delete — pola `UpsertTruckRequest::authorize()` sudah jadi contohnya.
