---
name: review-slice
description: >
  Review satu slice truck-appointment-system sebelum commit/push: jalankan gerbang,
  panggil reviewer bertingkat sesuai risiko diff (qa/dba/security), buktikan test menggigit,
  rangkum temuan terverifikasi. Gunakan: /review-slice [range git, default: perubahan lokal]
  Contoh: /review-slice   /review-slice HEAD~1   /review-slice origin/main..HEAD
---

Review "$ARGUMENTS" (kosong = perubahan belum di-commit + commit yang belum di-push). **Jangan memperbaiki kode** kecuali user minta setelah melihat laporan.

## 1. Tentukan cakupan

```bash
git status --short
git diff --name-status <range>        # kosong → git diff HEAD + git log origin/main..HEAD
```

Catat daftar file yang berubah — itu yang dikirim ke reviewer.

## 2. Gerbang (jalankan, jangan diasumsikan)

```bash
docker compose exec app composer test      # Pest (MySQL + Redis)
docker compose exec app composer analyse   # PHPStan level 8
docker compose exec app composer lint      # Pint --test (cek saja, bukan fix)
```

Bila `resources/js/` berubah: `npm run test:js`, `npm run type-check`, `npm run build`.
Bila gerbang/infra baru ditambah: cek sudah masuk `.github/workflows/ci.yml`.
Gerbang merah → laporkan output apa adanya, tetap lanjut review.

## 3. Reviewer — bertingkat sesuai risiko (subagent mahal: tiap kali memuat ulang `CLAUDE.md` ±15 KB)

| Diff | Tindakan |
|---|---|
| Hanya docs/`*.md`/komentar, atau ≤ ~40 baris di luar area di bawah | **Tanpa subagent** — review sendiri di sesi utama memakai daftar periksa `.claude/agents/qa-reviewer.md` |
| Kode biasa | `qa-reviewer` (default Sonnet) |
| Menyentuh area **Wajib Opus** (`AGENTS.md`) | `qa-reviewer` dengan `model: "opus"` pada panggilan Agent + reviewer di bawah yang relevan |

Reviewer spesialis — **hanya bila** diff menyentuh areanya (paralel, satu pesan):

| Reviewer (Opus) | Area |
|---|---|
| `dba-reviewer` | `database/` · `app/Repositories` · `Contracts/` · `lockForUpdate` · `booked_count` · `version` · query di Model |
| `security-reviewer` | `routes/` · `channels.php` · Controller · FormRequest · Policy · Middleware · auth/Sanctum/Spatie · `config/cors.php` · `.env*` |

Prompt tiap reviewer: range git + daftar file + 1–2 kalimat tujuan slice. Jangan kirim isi file — mereka membaca sendiri.

## 4. Mutasi (wajib bila menyentuh area "Wajib Opus" di `AGENTS.md`)

Untuk tiap mutasi yang disarankan reviewer (maks. 3 paling penting):
1. **Salin file ke scratchpad dulu** sebagai cadangan.
2. Rusak kode yang dijaga (Edit), jalankan test penjaganya saja (`--filter`), pastikan **merah**.
3. **Pulihkan dari cadangan** (tulis balik isi salinan). **Jangan `git checkout`** — pada file belum di-commit itu mengembalikan versi LAMA dan membuang kerja slice (pernah terjadi, `HANDOVER.md` audit 2026-09-16).
4. `git diff` file itu = sama seperti sebelum mutasi.

Test tetap hijau saat kode dirusak → temuan **KRITIS** (penjaga tidak menggigit).

## 5. Verifikasi temuan

Jangan meneruskan temuan reviewer mentah-mentah. Untuk tiap temuan: baca kodenya sendiri, konfirmasi skenario gagalnya, buang yang keliru atau yang sudah jadi keputusan (ADR-0003, B5, backlog B1–B6). Gabungkan temuan duplikat antar reviewer.

## 6. Laporan (ringkas)

```
## Review: <slice / range>

| Gerbang | Hasil |
|---|---|
| Pest | ✅ N passed / ❌ … |
| PHPStan 8 | … |
| Pint | … |
| Vitest · vue-tsc · build | … / n.a. |
| Mutasi | ✅ k/k menggigit / ❌ … |

**Temuan** (terverifikasi, paling parah dulu):
1. [KRITIS] file:baris — isu · skenario gagal · saran

**Dibuang:** temuan reviewer yang tidak lolos verifikasi + alasan singkat.

**Verdict:** Siap commit / Perbaiki dulu (sebut nomor temuan).
```
