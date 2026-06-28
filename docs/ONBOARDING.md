# docs/ONBOARDING.md — Panduan Developer Baru (Junior-friendly)

> **Untuk siapa:** developer yang **baru pertama** masuk proyek TAS, khususnya junior.
> **Tujuan:** dari nol → paham arsitektur → bisa menjalankan project → bisa menambah
> fitur kecil dengan benar, **tanpa tersesat** di 8 dokumen.
>
> **Cara pakai:** ikuti **berurutan dari atas**. Tiap tahap ada *tujuan*, *baca apa*,
> *lakukan apa*, dan **✅ self-check** (kalau bisa menjawabnya, lanjut; kalau belum,
> ulangi tahap itu). Jangan loncat ke koding sebelum Tahap 1–2 selesai.

---

## Daftar Isi
1. [Inti yang harus dipahami lebih dulu (peta mental)](#1-inti-yang-harus-dipahami-lebih-dulu-peta-mental)
2. [Glosarium — istilah yang akan terus muncul](#2-glosarium--istilah-yang-akan-terus-muncul)
3. [Prasyarat skill (jujur ke diri sendiri)](#3-prasyarat-skill-jujur-ke-diri-sendiri)
4. [Rencana minggu pertama (5 hari)](#4-rencana-minggu-pertama-5-hari)
5. [Tahapan baca dokumen + self-check](#5-tahapan-baca-dokumen--self-check)
6. [Golden path: bedah 1 request booking](#6-golden-path-bedah-1-request-booking)
7. [Resep umum: cara membaca slice APA PUN](#7-resep-umum-cara-membaca-slice-apa-pun)
8. [Loop kerja (TDD) — langkah konkret](#8-loop-kerja-tdd--langkah-konkret)
9. [Cheat-sheet jebakan (hemat berjam-jam)](#9-cheat-sheet-jebakan-hemat-berjam-jam)
10. [Perintah harian](#10-perintah-harian)
11. [Routing per tugas (lompat langsung)](#11-routing-per-tugas-lompat-langsung)
12. [Latihan pertama](#12-latihan-pertama)
13. [FAQ junior](#13-faq-junior)
14. [Peta dokumen lengkap](#14-peta-dokumen-lengkap)

---

## 1. Inti yang harus dipahami lebih dulu (peta mental)

Junior tersesat **bukan** karena kurang dokumen, tapi karena belum punya *peta mental*.
Kuasai satu hal ini, sisanya jadi mudah karena **semua fitur mengulang pola yang sama**.

### Setiap request mengalir lewat 3 lapis searah

```
┌─ HTTP layer ─────────────────────────────────────────────┐
│  Controller (invokable, tipis)  Form Request (validasi+izin)  Resource (output) │
└──────────────────────────────────────────────────────────┘
                              │  (DTO turun)
┌─ Business layer ─────────────────────────────────────────┐
│  Action (1 tugas, ber-lock)   Event/Listener (efek samping)   DTO  │
└──────────────────────────────────────────────────────────┘
                              │
┌─ Data layer ─────────────────────────────────────────────┐
│  Repository (interface + impl)   Model   Job   Notification  │
└──────────────────────────────────────────────────────────┘
```

### Analogi restoran (biar nempel)

| Komponen TAS | Di restoran | Tugasnya |
|--------------|-------------|----------|
| **Controller** | Pelayan | Terima pesanan, antar piring. **Tidak memasak.** |
| **Form Request** | Cek pesanan di pintu | "Menu ini ada? Kamu boleh pesan ini?" (validasi + izin) |
| **DTO** | Kertas order | Pesanan tertulis rapi & bertipe, dibawa ke dapur |
| **Action** | Koki | **Di sinilah masakan (logika) terjadi.** 1 koki = 1 masakan |
| **Repository** | Gudang bahan | Ambil/simpan data. Satu-satunya yang sentuh "kulkas" (DB) |
| **Resource** | Plating | Cara menyajikan hasil ke pelanggan (bentuk JSON) |
| **Event/Listener** | "Nanti suruh tukang cuci piring" | Efek samping (email, cache, broadcast) **setelah** masakan jadi |

### 3 aturan emas (hafalkan)

1. **Logika hanya di Action.** Controller cuma memanggil Action lalu balikan Resource —
   **tanpa query, tanpa `if` bisnis**.
2. **Input lewat Form Request + DTO, output lewat Resource.** Dilarang keras
   `Model::create($request->all())`.
3. **Ubah kuota/status hanya lewat Action ber-lock** (`DB::transaction` + `lockForUpdate`),
   **bukan** `update()` bebas. Ini jantung anti race-condition proyek.

> Frontend punya cermin 4-lapis yang sama semangatnya:
> `types/ (kontrak) → api/ (fetch) → composables/ (TanStack Query) → pages/ (render)`.

---

## 2. Glosarium — istilah yang akan terus muncul

**Domain (bisnis):**

| Istilah | Arti sederhana |
|---------|----------------|
| **Slot Window** | Jendela waktu di satu gate dengan **kuota** terbatas (mis. GATE-A, 08:00–09:00, kapasitas 5) |
| **Appointment** | 1 booking truk pada satu slot window |
| **Gate-in / Gate-out** | Saat truk masuk / keluar terminal (dicatat petugas gate) |
| **No-show** | Truk tidak datang sampai *grace period* habis → kuota dikembalikan otomatis |
| **Move type** | `DELIVERY` (ambil kontainer impor) atau `RECEIVAL` (drop kontainer ekspor) |
| **Booking code** | Kode unik tiap appointment (untuk QR/sopir) |
| **Quota / booked_count** | `capacity` = kuota max; `booked_count` = sudah terisi. Inti perebutan slot |

**Teknis (yang mungkin asing buat junior):**

| Istilah | Arti sederhana | Kenapa dipakai di TAS |
|---------|----------------|------------------------|
| **Race condition** | 2 request bersamaan memperebutkan 1 sumber daya → bisa salah hitung | 2 transporter rebut slot terakhir → **tak boleh over-book** |
| **`lockForUpdate`** | Kunci baris DB sampai transaksi selesai; yang lain menunggu | Serialisasi perebut slot → anti over-book |
| **Idempotency** | Aksi yang diulang menghasilkan efek **sama** (tidak dobel) | Mobile sopir sering double-tap → 1 booking saja |
| **Optimistic lock (`version`)** | Tolak update bila data sudah berubah sejak dibaca | Transporter & planner edit appointment bareng |
| **DTO** | Objek data bertipe (bukan array mentah) | Action tak bergantung ke HTTP; tipe jelas |
| **Repository** | Lapisan akses data di belakang *interface* | Bisa di-mock saat test; query lepas dari controller |
| **Policy** | Aturan "boleh-tidaknya akses **record ini**" | Transporter tak boleh lihat appointment company lain |
| **Permission** | Aturan "boleh-tidaknya **aksi ini**" (mis. `appointment.write`) | Gating menu & endpoint per role |

> Permission ≠ Policy. **Permission** = boleh *melakukan aksi* (booking?). **Policy** =
> boleh *menyentuh data tertentu* (appointment **milik siapa**?). Keduanya dipakai bareng.

---

## 3. Prasyarat skill (jujur ke diri sendiri)

Kamu **tidak** harus ahli, tapi minimal kenal ini. Kalau ada yang asing, pelajari sebentar
dulu — akan jauh lebih lancar:

- **PHP & Laravel dasar:** routing, Eloquent (Model/relasi), migration, middleware.
- **Konsep OOP:** interface, class `final`, dependency injection (constructor).
- **HTTP/REST:** status code (200/201/401/403/409/422/429), header, JSON.
- **Git dasar:** branch, commit kecil, push.
- **Vue 3 (kalau pegang frontend):** `<script setup>`, Composition API, reactive ref.

Tidak perlu tahu di awal (akan dijelaskan dokumen): Sanctum, Spatie Permission, TanStack
Query, PHPStan, Pest. Cukup tahu *namanya* untuk apa.

---

## 4. Rencana minggu pertama (5 hari)

Target realistis. Setiap hari diakhiri dengan satu hasil nyata.

| Hari | Fokus | Hasil akhir hari itu |
|------|-------|----------------------|
| **1** | Orientasi + kontrak (Tahap 0–1) | Paham peta mental & 3 aturan emas; bisa sebut larangan utama |
| **2** | Jalankan project (Tahap 2) | `composer test` **152 hijau** + `npm run test:js` **57 hijau** di mesinmu; bisa login SPA |
| **3** | Domain (Tahap 3) | Bisa gambar ulang state machine appointment & jelaskan 5 role |
| **4** | Golden path (Tahap 4a) | Berhasil booking via curl + uji idempotency; paham `BookAppointmentAction` |
| **5** | Latihan pertama | Selesaikan 1 latihan kecil (§12) lewat loop TDD; semua gerbang hijau |

---

## 5. Tahapan baca dokumen + self-check

Baca **persis** yang ditunjuk (jangan baca semua sekaligus).

| Tahap | Baca persis | Lakukan | ✅ Self-check |
|------|-------------|---------|---------------|
| **0. Orientasi** | `README.md` | — | "TAS menyelesaikan masalah apa dalam 1 kalimat?" |
| **1. Kontrak** ⚠️ | `CLAUDE.md` → *Aturan layer*, *Hardening*, *JANGAN* | Tulis ulang 3 larangan utama | "Boleh `Model::create($request->all())`? Kenapa tidak?" |
| **2. Hidupkan** | `docs/SETUP-GUIDE.md` §1–§9 (+ §11 Troubleshooting) | `migrate:fresh --seed` → `composer test` → `npm run test:js` | "Kenapa test pakai SQLite `:memory:`?" (SETUP §8c) |
| **3. Domain** | `docs/BUSINESS-FLOW.md` §1 RBAC + §2 state machine | Login `admin` lalu `dispatcher@majulog.test`, bandingkan menu | "Kenapa planner **tidak** punya `appointment.write`?" (§1 ¹) |
| **4a. Golden path** ⭐ | `docs/CODE-WALKTHROUGH.md` §A → **§J** | Booking via curl (SETUP §10d), ulangi dgn `Idempotency-Key` sama | "Apa yang mencegah 2 transporter rebut slot terakhir?" |
| **4b. Melebar** | §K (auth), §L (policy) → sisanya sesuai tugas | — | "Beda *permission* vs *Policy*?" (§L) |
| **5. Tiap sesi** | `HANDOVER.md` → *Sudah selesai*, *Langkah berikutnya*, *Jebakan* | Ikuti loop TDD (§8) | "Apa langkah berikutnya proyek sekarang?" |

> Rujukan saat butuh (tak wajib berurutan): `PRD.md` (kenapa & scope), `DUMMY-DATA.md`
> (akun demo), `FRONTEND.md` (kalau pegang Vue).

---

## 6. Golden path: bedah 1 request booking

Booking adalah **jantung proyek** dan **cetak biru semua slice lain**. Pahami satu ini
sampai tuntas; slice lain (gate-in/out, cancel, reschedule) hanya variasinya.

### Alur lengkap `POST /api/v1/appointments`

```
 1. middleware idempotency      → anti double-tap (Cache::lock)         IdempotencyKey.php
 2. BookAppointmentRequest      → validasi + can('appointment.write')   → bangun DTO
 3. BookAppointmentController   → tipis: panggil Action → balikan Resource
 4. BookAppointmentAction  ⭐   → DB::transaction(attempts: 3) {
        $window = lockForUpdate(slot)   ← KUNCI baris slot (serialisasi perebut)
        if penuh / tutup        → throw → 409
        create appointment (CONFIRMED)
        booked_count++                   ← di transaksi yang SAMA = konsisten
    }
    AppointmentBooked::dispatch(...)      ← efek samping SETELAH commit (cache, dll.)
 5. AppointmentResource         → bentuk JSON keluar
```

### Kenapa tiap langkah ada (jangan dihafal, dipahami)

- **`lockForUpdate`** membuat SQL `SELECT ... FOR UPDATE` → baris slot dikunci. Transporter
  kedua **menunggu** sampai yang pertama commit, lalu baca `booked_count` terbaru → mustahil
  over-book.
- **`booked_count++` di transaksi yang sama** dengan create → keduanya sukses atau keduanya
  gagal (atomic). Tidak ada "appointment dibuat tapi kuota tak naik".
- **Event di-dispatch SETELAH commit**, bukan di tengah transaksi — aturan keras CLAUDE.md
  (jangan kirim job/HTTP di tengah `DB::transaction`).

### 3 lapis idempotensi (pola yang berulang)

Saat sopir double-tap, ada **tiga jaring**:
1. **Middleware `Idempotency-Key`** (HTTP) → request kembar diputar ulang responsnya.
2. **Cek state di Action** → mis. `if ($appointment->isGatedIn()) return;` (di gate-in).
3. **Unique constraint DB** → `(slot_window_id, container_no)` jaring terakhir.

✅ **Kalau paham ini, kamu paham:** *lock*, *transaction*, *DTO masuk / Resource keluar*,
*event pasca-commit*, *idempotensi berlapis*. Itu fondasi seluruh backend TAS.

> Baca §J sambil **buka file aslinya**: `app/Actions/BookAppointmentAction.php`,
> `app/Http/Requests/V1/BookAppointmentRequest.php`,
> `app/Http/Controllers/Api/V1/BookAppointmentController.php`.

---

## 7. Resep umum: cara membaca slice APA PUN

Setiap fitur backend punya berkas yang sama. Saat ketemu slice baru (mis. "gate-in"),
baca filenya **dalam urutan ini** — kamu akan paham cepat:

```
1. routes/api.php          → cari endpointnya. Middleware apa? (auth, can, idempotency)
2. Form Request            → siapa boleh? (authorize) Input apa? (rules) → DTO apa?
3. Controller (invokable)  → Action mana yang dipanggil? (harusnya cuma 1 baris logika)
4. Action  ⭐              → INTI. Baca pelan: transaction? lock? state machine? event?
5. Repository              → query/penulisan data sebenarnya
6. Resource                → bentuk output JSON
7. Event/Listener          → efek samping (cache, broadcast, job, notifikasi)
8. Test (tests/Feature/..) → contoh pemakaian + edge case (paling cepat paham dari sini!)
```

> **Tips junior:** kalau bingung suatu fitur, **baca test-nya dulu**. Test menunjukkan
> input → output yang diharapkan, termasuk kasus gagal (409/422/403). Lebih cepat paham
> daripada menebak dari kode.

### Daftar Action yang ada (semua mengikuti pola di atas)

`BookAppointmentAction` · `RescheduleAppointmentAction` · `CancelAppointmentAction` ·
`GateInAction` · `GateOutAction` · `MarkNoShowAction` · `OpenSlotWindowAction` ·
`CloseSlotWindowAction` · `Admin/` (Create/Update/Delete × Terminal/Gate/Company/User).

---

## 8. Loop kerja (TDD) — langkah konkret

Aturan CLAUDE.md: **test ditulis lebih dulu**. Saat menambah/mengubah Action:

```
1. Tulis Pest test DULU (tests/Feature/...):
   - Happy path (sukses).
   - Edge: kuota penuh → 409 · double-submit + Idempotency-Key → 1x · version basi → 409 ·
     akses lintas-company → 403.
2. Implement Action sampai test HIJAU. Hormati layer (§1) & state machine (BUSINESS-FLOW §2).
3. Jalankan gerbang kualitas — ketiganya harus bersih:
       composer fix       # rapikan format (Pint)
       composer analyse   # PHPStan level 8
       composer test      # Pest
4. Commit kecil (1 slice = 1 commit), pesan jelas.
```

**Definition of Done** (sebelum anggap selesai): Pest hijau · PHPStan lvl 8 lolos · Pint
bersih · input lewat Form Request+DTO, output lewat Resource · hardening relevan terpasang ·
perubahan status tercatat di Activity Log.

> **WAJIB stop & tanya dulu** sebelum: menambah package, mengubah migrasi yang sudah jalan,
> atau menyentuh apa pun di daftar **JANGAN** (CLAUDE.md).

---

## 9. Cheat-sheet jebakan (hemat berjam-jam)

Junior hampir pasti kena ini; semua sudah pernah terjadi & dicatat:

| Gejala | Sebab & solusi | Rujukan |
|--------|----------------|---------|
| `Call to undefined function seed()` di test | Di closure `function(){}` pakai **`$this->seed(...)`**; global `seed()` hanya di arrow-fn `fn()=>` | CODE-WALKTHROUGH §V.5 |
| PHPStan `property.notFound` pada `$this->route('x')->id` | Bertipe `object\|string` → `$m instanceof X ? $m->id : null` | §V.4 |
| PHPStan "Strict comparison always false" (enum) | Tambah `@property AppointmentStatus $status` di model | SETUP §11 |
| PHPStan `method.childReturnType` di factory `definition()` | Hapus docblock `@return array<...>` (warisi tipe induk) | SETUP §11 |
| `Cache::tags() does not support tagging` | Store dev `database` tak dukung tag → key eksplisit + `Cache::forget` | HANDOVER changelog |
| Horizon/Reverb gagal di Windows | Butuh `ext-pcntl` → jalankan di **Docker (Linux)** | HANDOVER *Jebakan* |
| Test "menghapus" data dev | Aktifkan `:memory:` di `phpunit.xml` | SETUP §8c |
| "could not find driver" saat migrate | Aktifkan `pdo_sqlite` + `sqlite3` di php.ini | SETUP §1 |
| `Declaration ...::data() must be compatible` | Form Request sudah punya `data()`/`date()` → ganti nama (`toData()`) | SETUP §11 |

---

## 10. Perintah harian

```bash
# Setup / reset data
php artisan migrate:fresh --seed     # skema baru + data demo

# Gerbang kualitas (backend) — urutan: fix → analyse → test
composer fix                         # Pint (format)
composer analyse                     # PHPStan level 8
composer test                        # Pest (152 hijau)
./vendor/bin/pest --filter="Book"    # jalankan sebagian (cocokkan nama)

# Menjalankan app
php artisan serve                    # shell + API (buka http://localhost:8000)
npm run dev                          # Vite HMR (terminal lain)

# Frontend
npm run test:js                      # Vitest (57 hijau)
npm run type-check                   # vue-tsc
npm run build                        # bundel produksi

# Hanya di Docker/Linux (butuh ext-pcntl):
php artisan horizon                  # queue worker
php artisan reverb:start             # websocket
```

Buka app di **`http://localhost:8000`** (Laravel), **bukan** port 5173 (itu Vite HMR saja).

---

## 11. Routing per tugas (lompat langsung)

Jangan baca semua dokumen tiap kali. Sesuaikan tugas:

| Tugasmu | Baca ini |
|---------|----------|
| Backend fitur baru | CLAUDE.md (loop TDD) + CODE-WALKTHROUGH slice termirip + skill `/slice` |
| Menyentuh **status/akses** | BUSINESS-FLOW §1 (RBAC) & §2 (state machine) — Policy & seeder wajib cocok |
| Menyentuh **skema DB** | BUSINESS-FLOW §4 (ERD) + CLAUDE.md "JANGAN ubah migrasi yang sudah jalan" |
| **Frontend** Vue | FRONTEND.md (4 lapisan + pola TanStack Query + jebakan Vitest) |
| **Admin CRUD** | CODE-WALKTHROUGH §V + FRONTEND §4 |
| Cari status proyek | HANDOVER.md |
| Butuh akun/data uji | DUMMY-DATA.md |

---

## 12. Latihan pertama

Kerjakan **satu** ini lewat loop TDD (§8) untuk membuktikan kamu paham alurnya. Mulai dari
yang paling ringan:

1. **Baca-only (pemanasan):** jalankan booking via curl (SETUP §10d), lalu ulangi **persis**
   dengan `Idempotency-Key` sama → buktikan **tidak** ada appointment baru
   (`Appointment::count()` tetap). Tulis 2 kalimat: apa yang terjadi & kenapa.
2. **Tambah test (mudah):** tulis 1 Pest test baru untuk kasus yang belum ada di
   `BookAppointmentEndpointTest` (mis. booking ke window **CLOSED** → 409). Pastikan hijau.
3. **Slice kecil (menantang):** tambah field read-only baru di sebuah Resource (mis.
   tampilkan `remaining` di output yang relevan) — lewat Resource + test, **tanpa** menyentuh
   Action. Latih disiplin "output hanya lewat Resource".

> Setelah selesai: `composer fix && composer analyse && composer test` harus bersih, lalu
> minta review. **Jangan** push langsung ke `main` tanpa diskusi bila lingkupnya besar.

---

## 13. FAQ junior

**Q: Kenapa logika tidak boleh di Controller? Kan lebih cepat?**
Cepat sekarang, susah nanti. Logika di Action bisa dipakai ulang (job, command, test) tanpa
HTTP, dan mudah diuji. Controller "tipis" membuat alur gampang dibaca.

**Q: Kenapa pakai Repository, bukan Eloquent langsung di Action?**
Supaya Action bergantung pada *kontrak* (interface), bukan implementasi. Saat test, repo
bisa di-mock; saat butuh ganti sumber data, cukup ganti binding. Kontrak juga melarang query
di controller — repo menjaga konsistensi.

**Q: Apa itu "race condition" dan kenapa proyek ini ribut soal itu?**
Dua transporter klik booking di detik yang sama untuk slot terakhir. Tanpa `lockForUpdate`,
keduanya bisa membaca "`booked_count` = 4 (masih ada sisa)" lalu **dua-duanya** booking →
over-book. Lock membuat mereka antre → aman. Ini *inti* proyek.

**Q: PHPStan/Pint/Pest itu apa? Wajib?**
Pint = perapih format. PHPStan level 8 = analisis statis ketat (cegah bug tipe/null). Pest =
framework test. **Ketiganya wajib bersih sebelum commit** (Definition of Done).

**Q: Bingung suatu fitur. Mulai dari mana?**
**Baca test-nya** (`tests/Feature/...`). Test = contoh pemakaian + kasus gagal. Lebih cepat
paham daripada menebak dari kode.

**Q: Boleh menambah library?**
**Tidak tanpa konfirmasi.** Cek `composer.json` dulu, lalu tanya. Sama untuk mengubah migrasi
yang sudah jalan. Lihat daftar **JANGAN** di CLAUDE.md.

**Q: Akun demo apa saja?**
Semua password `password`: `admin@tas.test`, `planner@tas.test`, `gate@tas.test`,
`dispatcher@majulog.test`, `budi@majulog.test` (driver). Lengkap: DUMMY-DATA.md.

---

## 14. Peta dokumen lengkap

| Dokumen | Isi | Baca saat | Frekuensi |
|---------|-----|-----------|-----------|
| **`docs/ONBOARDING.md`** (ini) | Jalur belajar developer baru | hari pertama | sekali |
| `README.md` | Gambaran + cara jalankan + peta dokumen | orientasi | sekali |
| `CLAUDE.md` | **Kontrak arsitektur** (aturan layer, hardening, JANGAN) | sebelum menulis kode | rujuk terus |
| `docs/ARCHITECTURE.md` | Pola arsitektur, peta folder, trace request antar-lapisan | memahami struktur besar | sekali + rujuk |
| `docs/adr/` | Architecture Decision Records — alasan keputusan | sebelum mengubah keputusan arsitektur | rujuk |
| `docs/SETUP-GUIDE.md` | Setup & build manual + endpoint + troubleshooting | menyiapkan/menjalankan | sekali + rujuk |
| `docs/BUSINESS-FLOW.md` | Domain: RBAC §1 · state machine §2 · alur §3 · ERD §4 | menyentuh status/akses/skema | rujuk per fitur |
| `docs/CODE-WALKTHROUGH.md` | Penjelasan detail kode **backend** + "kenapa" | memahami kode backend | rujuk per slice |
| `docs/FRONTEND.md` | Penjelasan detail **Vue SPA** + pola TanStack Query | memahami/menyentuh SPA | rujuk per fitur FE |
| `docs/PRD.md` | **Kenapa** & batas scope MVP | menentukan scope | jarang |
| `docs/DUMMY-DATA.md` | Akun & data demo | butuh data uji | rujuk |
| `HANDOVER.md` | Status hidup antar-sesi + langkah berikutnya | **awal tiap sesi** | sering |

---

### Inti dari semuanya
Bukan "baca 9 dokumen berurutan", tapi: **kuasai peta mental (§1) → hidupkan project
(Tahap 2) → bedah 1 golden path (booking, §6)**. Setelah itu, sisa dokumen jadi *rujukan
saat butuh* — bukan PR bacaan. Selamat datang di TAS. 🚛
