# docs/PRD.md — truck-appointment-system

PRD-lite. Menjawab **kenapa** & **sampai mana** (scope). Untuk *apa* lihat `BUSINESS-FLOW.md`; untuk *bagaimana* lihat `CLAUDE.md`. Dokumen ini jarang berubah — perubahan scope dicatat di `HANDOVER.md`.

---

## 1. Masalah & konteks

Terminal peti kemas menerima truk yang datang tanpa jadwal. Pada jam sibuk terjadi penumpukan di gate: antrian panjang, truk idle berjam-jam, kemacetan di jalan sekitar pelabuhan, dan beban gate yang tidak bisa diprediksi. Tidak ada mekanisme yang meratakan kedatangan sepanjang hari.

TAS mengatasinya dengan **slot booking berkuota**: perusahaan angkutan memesan jendela waktu kedatangan yang kapasitasnya dibatasi per gate per jam, sehingga arus truk merata dan terprediksi.

> Konteks proyek: dibangun sebagai latihan backend tingkat lanjut (konkurensi, idempotency, RBAC, realtime) dengan domain nyata pelabuhan. Karena itu kualitas arsitektur = bagian dari definisi sukses.

## 2. Goal & metrik sukses

Tujuan produk:
- Meratakan kedatangan truk → menurunkan puncak antrian di gate.
- Membuat beban gate terprediksi (tidak melebihi kuota).
- Memberi transparansi: transporter tahu sisa slot, terminal tahu utilisasi.

Metrik:
- Rata-rata waktu tunggu truk di gate turun.
- Truk per jam per gate ≤ kuota (tidak ada over-booking).
- Tingkat no-show terkontrol (di bawah ambang yang ditetapkan).
- Utilisasi slot (terpakai ÷ kuota) terukur per gate/hari.

Sukses teknis (karena ini juga proyek skill): race condition pada booking tidak pernah meng-over-book; booking idempoten; RBAC & isolasi antar-company tidak bocor; sisa kuota & antrian tampil realtime.

## 3. Scope MVP — IN vs OUT

**IN (dibangun di MVP):**
- Auth Sanctum token + 5 role & RBAC (lihat `BUSINESS-FLOW.md §1`).
- Admin: CRUD master data (terminal, gate, perusahaan angkutan, user/role) dengan guard hapus saat masih ada dependen (409).
- Planner: buka/tutup slot window + atur kuota; intervensi `appointment.override` (teraudit).
- Transporter: lihat ketersediaan, booking, reschedule, cancel; kelola truk & sopir (company sendiri).
- Driver: lihat jadwal hari ini + kode booking/QR + status gate.
- Gate Officer: gate-in, gate-out, tandai no-show.
- Slot berkuota dengan anti-race (`lockForUpdate`), Idempotency-Key, optimistic lock (`version`).
- Job: reminder (H-2 jam) + no-show sweep (kembalikan kuota).
- Realtime: sisa kuota & antrian (Reverb + Echo).
- Report utilisasi dasar + audit trail (Activity Log).
- **1 terminal**, multi-gate. Data master di-seed (lihat `DUMMY-DATA.md`).

**OUT (di luar MVP, ditunda):**
- Billing / pembayaran slot, dynamic pricing kuota.
- Multi-terminal lintas pelabuhan, tenant enterprise/SSO.
- Integrasi penuh ke TOS produksi (MVP cukup job idempoten yang siap di-wire).
- App native (MVP = SPA responsif).
- Notifikasi SMS/WhatsApp (MVP = email/push/in-app).
- Prediksi beban berbasis ML, kuota dinamis.
- Manajemen yard/posisi kontainer mendalam (di luar domain TAS).
- Multi-bahasa.

> Aturan: apa pun di luar daftar **IN** tidak dikerjakan tanpa memperbarui PRD ini lebih dulu (lalu propagasi sesuai `tas-claude-code-guide.html §11`).

## 4. Asumsi & constraint

- Arsitektur **API-first decoupled** (Sanctum token + Vue SPA terpisah) — **bukan** Inertia/monolith. Ini mengikat: fitur tidak boleh mengasumsikan session-based auth atau server-rendered view.
- Satu terminal untuk MVP; kuota slot per jam bersifat **tetap** (bukan dinamis).
- Toleransi datang awal/terlambat dan grace period no-show diambil dari **config**, bukan hardcode.
- Stack & aturan teknis non-negotiable: `CLAUDE.md`.
- Sumber kebenaran domain (role, alur, state machine, ERD): `BUSINESS-FLOW.md`.

---

### Keterhubungan dokumen
`PRD.md` (kenapa & scope) → `BUSINESS-FLOW.md` (apa) → `CLAUDE.md` (bagaimana) → `DUMMY-DATA.md` (data uji) → `HANDOVER.md` (status sesi). Panduan eksekusi Claude Code: `tas-claude-code-guide.html`.
