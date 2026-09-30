# AGENTS.md — peran & pemilihan model AI

Pelengkap `CLAUDE.md` (kontrak arsitektur — **kontrak yang menang**). Hanya menjawab: *peran apa, model apa, kapan naik/turun*. Aturan teknis (layer, Hardening, DoD, JANGAN) ada di `CLAUDE.md` — jangan diulang di sini.

## Model

| Model | ID | Pakai untuk |
|---|---|---|
| **Opus 5.5** | `claude-opus-5-5` | Tertinggi. ADR/keputusan arsitektur, audit, area "Wajib Opus", desain skema, bug yang gagal 2× di model lebih kecil |
| **Sonnet 5.5** | `claude-sonnet-5-5` | **Default harian**: 1 slice (Action + test + wiring), refactor, Pest/Vitest biasa, CI, Dockerfile dev, review diff biasa |
| **Haiku 4.5** | `claude-haiku-4-5-20251001` | Grep/eksplorasi luas, ringkas log/diff, Pint, update docs/HANDOVER |

**Naik:** test merah setelah 2 percobaan → satu tingkat (Haiku→Sonnet→Opus); area "Wajib Opus" → mulai di Opus; ragu desain → tanya user, bukan naik model diam-diam.
**Turun:** pekerjaan yang hasilnya cukup diverifikasi test tidak butuh Opus.

## Sesi utama (di sinilah token terbanyak habis)

Model sesi **tidak berganti otomatis** — file ini hanya anjuran; `model:` frontmatter hanya mengunci subagent.
- **Rekomendasi:** `/model opusplan` (Opus saat plan mode, Sonnet saat eksekusi) — cocok dengan `/plan-slice` → `/slice`. Bila tak tersedia: Sonnet default, `/model opus` hanya untuk area "Wajib Opus".
- **Tugas kecil (grep 1–2 file, edit docs, jalankan Pint) → kerjakan langsung di sesi.** Subagent mulai dari nol dan memuat ulang `CLAUDE.md` (~15 KB); untuk tugas kecil itu lebih mahal daripada mengerjakannya sendiri. Subagent Haiku/`Explore` hanya untuk pencarian yang benar-benar luas.

## Peran

| Peran | Lingkup | Model |
|---|---|---|
| **Developer** | Action, Controller/FormRequest/Resource/DTO, Repository, Listener, Vue (`/slice`) | Sonnet; Opus bila menyentuh booking/reschedule/gate/no-show |
| **QA** | TDD, edge case, mutasi, e2e (`/review-slice`) | Sonnet harian; Opus untuk `tests/Concurrency/` & mutasi area Opus; Haiku merangkum hasil suite |
| **Arsitek** | Batas layer, ADR, kontrak API, backlog B1–B6 (`/plan-slice`); mode plan, tanpa kode fitur | Opus |
| **DBA** | Migrasi baru, indeks, constraint, lock, `booked_count`, EXPLAIN, seeder | Opus untuk skema/lock/migrasi data; Sonnet migrasi & factory sederhana |
| **DevOps** | compose, Dockerfile, Horizon/scheduler/Reverb, `ci.yml`, B6 | Sonnet; Opus untuk image produksi & keamanan deploy; Haiku baca log CI |

Aturan wajib tiap peran = `CLAUDE.md` (§Aturan layer, §Hardening, §Stack 🔒, §JANGAN). Contoh yang paling sering terlupa: jangan hapus test (2) `ConcurrentBookingTest`; jangan cabut dua pengaman Docker; gerbang baru wajib masuk `ci.yml`.

## Wajib Opus (mulai di sini)

Lock/`booked_count`/`version` · idempotency + `Cache::lock` · state machine `AppointmentStatus` · auth/Sanctum/Spatie/isolasi tenant · migrasi yang mengubah data existing · image produksi (B6) & integrasi TOS (B3).

## Skill & subagent

| Nama | Fase | Model | Kapan |
|---|---|---|---|
| `/plan-slice` | Plan | Opus bila area Opus, selain itu Sonnet | Sebelum tiap slice; tunggu OK |
| `/slice` | Kerja | sesuai peran | Setelah plan disetujui |
| `/review-slice` | Review | sesi utama | Sebelum commit/push; **bertingkat sesuai risiko** (lihat skill) |
| `qa-reviewer` | Review | `sonnet` (frontmatter); skill boleh menaikkan ke Opus | Diff biasa; Opus bila area Opus |
| `dba-reviewer` | Review | `opus` | Hanya bila diff menyentuh `database/`, Repository, lock, kuota |
| `security-reviewer` | Review | `opus` | Hanya bila diff menyentuh route, auth, Policy, isolasi tenant |
| `Explore` (bawaan) | Plan | Haiku | Pencarian luas |

## Alur satu slice

1. `/plan-slice` → plan → **tunggu OK**.
2. DBA (bila skema/lock berubah) → migrasi + indeks.
3. Test dulu → implement sampai hijau (`/slice`).
4. DevOps (bila gerbang/infra berubah) → `ci.yml`, compose.
5. `/review-slice` → commit kecil → catat di `HANDOVER.md` (Haiku/Sonnet cukup).
