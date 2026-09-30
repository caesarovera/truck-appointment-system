---
name: plan-slice
description: >
  Susun rencana satu vertical slice truck-appointment-system SEBELUM menulis kode:
  file per layer, test yang ditulis dulu, hardening relevan, peran & model (AGENTS.md),
  risiko. Berhenti & tunggu OK user. Gunakan: /plan-slice <fitur atau masalah>
  Contoh: /plan-slice batasi CORS ke domain SPA   /plan-slice e2e no-show
---

Susun rencana untuk "$ARGUMENTS". **Jangan menulis/mengubah kode apa pun di skill ini** — output-nya hanya plan. Setelah user OK, eksekusi lewat `/slice`.

## Sebelum menyusun

1. Baca hanya bagian yang relevan (Grep dulu, jangan dump file penuh):
   - `docs/BUSINESS-FLOW.md` §1 (RBAC) / §2 (state machine) bila menyentuh akses/status.
   - `docs/PRD.md` bila ragu fitur ini masuk scope MVP.
   - `HANDOVER.md` §Backlog bila ini salah satu B1–B6 — catatan desain di sana wajib dihormati.
   - Kode yang ada: cari Action/Repository/test serupa untuk ditiru, jangan bikin pola baru.
2. Bila permintaan terlalu besar untuk 1 slice (1 Action + test + wiring), **pecah** jadi beberapa slice berurutan dan rencanakan slice pertama saja.
3. Bila ada keputusan yang hanya bisa diambil user (scope, trade-off biaya), kumpulkan sebagai pertanyaan — jangan asumsikan.

## Format output (wajib, ringkas)

```
## Plan: <nama slice>

**Tujuan:** 1–2 kalimat. **Di luar scope:** apa yang sengaja tidak disentuh.

**Peran & model (AGENTS.md):** <peran> — <model>. Area "Wajib Opus": ya/tidak (sebut yang mana).

**Status & akses:** transisi state machine yang terlibat · permission/Policy · isolasi tenant.

**File** (per layer, tandai [baru]/[ubah]):
- HTTP: Controller · FormRequest · Resource
- Business: Action · DTO · Event/Listener
- Data: Repository (+ interface di Contracts/) · Model · Migrasi [baru, tak pernah ubah yang lama] · Job
- Frontend: view/store/composable (bila ada)
- Docs: bagian yang harus ikut diperbarui

**Test ditulis DULU** (nama test → yang dibuktikan):
- happy path
- edge: kuota penuh 409 · double-submit + Idempotency-Key · version basi 409 · 403 lintas tenant · transisi ilegal · (lainnya sesuai slice)
- test penjaga yang akan dibuktikan menggigit lewat mutasi (sebut kode yang akan dirusak)

**Hardening relevan** (hanya yang berlaku, dari CLAUDE.md §Hardening): lock/transaksi · idempotency · N+1 · cache forget · queue unique · rate limit · Activity Log.

**Stop & tanya** (bila ada): paket baru · ubah migrasi berjalan · area JANGAN · melebar dari permintaan.

**Risiko & pertanyaan terbuka:** …

**Verifikasi:** gerbang (`composer test/analyse/lint`, + Vitest/type-check/build bila SPA) + bukti live yang akan dijalankan (mis. request nyata ke container, cek data mendarat di DB/Redis yang benar).

**Commit:** 1 slice = 1 commit — pesan yang direncanakan.
```

## Aturan

- Opsi dijelaskan dengan bahasa sederhana + **satu rekomendasi**, bukan survei semua kemungkinan.
- Klaim tentang kode yang ada harus sudah diverifikasi (Grep/Read), bukan diingat.
- Akhiri dengan: **"Tunggu OK sebelum eksekusi."** Jangan lanjut ke implementasi di giliran yang sama.
