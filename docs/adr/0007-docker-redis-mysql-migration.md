# ADR-0007 — Migrasi ke Docker penuh + Redis + MySQL; test konkurensi didahulukan

**Status:** Accepted · 2026-09-12

## Context

Sejak awal proyek, `CLAUDE.md` §Stack menyebut **Redis 7 + Horizon** sebagai *target produksi*
sementara dev memakai driver `database` di atas SQLite. Selisih itu sudah lama dicatat jujur
(baris "target", bukan "keadaan") dan jadi item #1 *Langkah berikutnya* di `HANDOVER.md`. Yang
belum pernah diputuskan: **kapan** dan **sampai sejauh apa** migrasinya.

Saat menimbang prioritas 2026-09-12, pemilik proyek menyatakan framing sebenarnya adalah
**pembelajaran**, bukan tenggat rilis. Framing itu mengubah bobot: nilai utama migrasi bukan
"siap produksi" (belum ada target deploy), melainkan (a) keterampilan DevOps yang belum
tersentuh proyek ini sama sekali, dan (b) satu kebenaran teknis yang selama ini tak pernah
bisa diuji di SQLite.

### Temuan yang mengubah urutan

Sebelum plan disetujui, klaim "race condition adalah inti proyek ini dan sudah teruji"
**diuji lewat mutasi** (pola yang sudah dipakai proyek ini saat menguji audit trail, lihat
`CODE-WALKTHROUGH §Y`). Hasilnya:

> `->lockForUpdate()` dihapus **sepenuhnya** dari `SlotRepository::lockForUpdate()`,
> lalu `it('never over-books the last slot')` dijalankan → **tetap PASS** (2 assertions).

Sebabnya struktural, bukan kebetulan: test di `tests/Feature/Booking/BookAppointmentActionTest.php:140`
memanggil `BookAppointmentAction` **dua kali berurutan dalam satu proses**. Eksekusi sekuensial
tak pernah bisa membuktikan sebuah lock, berapa pun mesin databasenya. Yang teruji hanyalah
aturan bisnis `bookedCount >= capacity` → tolak, dan aturan itu memang benar.

Dua konsekuensi yang harus disebut terang-terangan:
1. **Perilaku paling penting sistem ini tidak pernah diuji sekali pun** — yaitu apa yang terjadi
   saat dua transporter menekan Booking pada detik yang sama untuk slot terakhir.
2. Klaim di `ARCHITECTURE.md §7` ("Action ber-lock terpusat → race-handling **teruji**") **tidak benar**
   sejak ditulis, dan ikut dikoreksi bersama ADR ini.

Ini kelas bug yang **berulang** di proyek ini, sama dengan `TruckStatus::INACTIVE`,
`driver_invalid_role`, dan toleransi jendela gate-in: aturan benar di dokumen, tapi penegak atau
penjaganya tak pernah benar-benar ada. Bedanya, kali ini yang bolong adalah **gerbangnya**, bukan
kodenya — kode `lockForUpdate()` sudah benar, hanya tak ada yang menjaganya tetap benar.

SQLite tak bisa menutup lubang ini: ia menyerialkan tulisan di level file/database, dan Laravel
tak menerbitkan `SELECT ... FOR UPDATE` untuk SQLite. Jadi test konkurensi yang jujur **menuntut**
MySQL lebih dulu. Di sinilah migrasi infrastruktur dan kebenaran uji bertemu.

## Decision

**Migrasi ke Docker penuh + Redis + MySQL, dengan urutan yang menaruh kebenaran paling depan.**

Tiga keputusan teknis yang diambil bersama pemilik proyek:

1. **Cakupan Docker: penuh.** App (Laravel API), Reverb, Horizon, Redis, dan MySQL semuanya jadi
   service di `docker-compose.yml`. Alternatif hibrida (hanya Redis/MySQL/Horizon di container,
   app tetap native Windows) sempat direkomendasikan karena disrupsi lebih kecil, tapi **ditolak**
   pemilik proyek: paritas produksi penuh dan pengalaman orkestrasi multi-service adalah tujuan
   belajarnya, bukan efek sampingnya.
2. **Klien Redis: `phpredis` (ekstensi), bukan `predis`.** Konsekuensi langsung dari keputusan 1 —
   karena PHP kini berjalan di container Linux yang kita kontrol lewat `Dockerfile`, ekstensi cukup
   di-`pecl install redis`. Hambatan asli (DLL Windows harus cocok versi/TS/arsitektur) **hilang**
   bersama PHP native. **Tidak ada package Composer baru.** `REDIS_CLIENT=phpredis` yang sudah jadi
   default `.env.example` tetap berlaku apa adanya.
3. **Pest & CI ikut pindah ke MySQL + Redis sungguhan.** Bukan demi kerapian, tapi karena tanpa ini
   temuan di atas tak bisa ditutup. Konsekuensinya diterima sadar: test lebih lambat, dan
   `--parallel` butuh skema terisolasi per proses (`tas_testing_test_{TEST_TOKEN}`, pola resmi
   Laravel lewat `ParallelTesting::setUpTestDatabase`).

**Urutan eksekusi (kebenaran dulu, kosmetik terakhir):**

| # | Slice | Kenapa di urutan ini |
|---|-------|----------------------|
| 1 | Container **MySQL saja** + arahkan Pest ke sana + **tulis test konkurensi sungguhan** | Menjawab pertanyaan termahal proyek ini tanpa menunggu containerisasi penuh. Ditulis **merah dulu** (loop TDD kontrak). |
| 2 | Redis (cache/session/queue) + **Horizon** di container | Padat pembelajaran; Horizon pertama kali benar-benar bisa jalan (`ext-pcntl` tak ada di PHP Windows). |
| 3 | CI service containers (`mysql:8` + `redis:7`) | Mengunci hasil #1 dan #2 supaya tak diam-diam mundur saat push. |
| 4 | Containerisasi app penuh + docs + ADR ini di-update | Di sini keputusan filesystem WSL2 diambil sadar (lihat Consequences). |
| 5 | Reverb ke container — **terakhir, boleh dilewati** | Sudah jalan native & terverifikasi manual. Memindahkannya = risiko merusak yang bekerja demi selisih belajar paling tipis. |

Plan awal menaruh migrasi test di tahap 6 dari 7. Urutan itu **ditolak setelah temuan mutasi**:
ia menuntut lima tahap setup dibayar lebih dulu sebelum pemilik proyek tahu apakah jantung
sistemnya aman.

## Consequences

**Untung:**
- Perilaku konkuren booking akhirnya **teruji sungguhan**, bukan diasumsikan dari keberadaan kode
  `lockForUpdate()`. Ini menutup lubang gerbang, bukan sekadar menambah lapisan.
- `lockForUpdate()` di test akhirnya memakai semantik **row-lock MySQL** yang sama dengan produksi.
- Horizon, Redis, orkestrasi multi-container, dan CI service containers jadi permukaan belajar baru
  yang sebelumnya nol di proyek ini.
- Selisih lama `CLAUDE.md` §Stack ("target" vs "keadaan") akhirnya bisa ditutup dengan jujur.
- Refaktor `Cache::forget` eksplisit → `Cache::tags(['slot', "gate:{$gateId}"])` jadi **boleh**
  (Redis mendukung tagging). Sengaja **tidak** dimasukkan ke migrasi ini — lihat *Kapan ditinjau ulang*.

**Rugi / risiko:**
- **Repo ada di `C:\Dev\...`, jadi bind mount menembus NTFS ke container Linux.** PHP membaca ribuan
  file kecil saat autoload, dan ini jalur I/O paling lambat di Docker Desktop Windows. Mitigasi baku:
  pindahkan repo ke filesystem WSL2. Itu **keputusan tersendiri** yang sengaja ditunda ke slice #4
  supaya tidak jadi kejutan di tengah jalan.
- Kalau `vendor/` ditaruh di named volume demi kecepatan, **IDE dan PHPStan di host kehilangan
  sumbernya** → semua gerbang kualitas harus dijalankan di dalam container
  (`docker compose exec app composer test`). Alur kerja harian berubah, bukan cuma infrastruktur.
- CI jadi lebih lambat: service container MySQL + Redis harus sehat sebelum step test jalan.
- **Membangun infrastruktur produksi untuk produksi yang belum ada.** Di bawah framing pembelajaran
  ini sah, tapi harus disebut apa adanya: ia mengubah definisi "selesai", dan membuat slice #1–#3
  jelas lebih bernilai daripada #4–#5.
- `composer dev` (concurrently: serve + queue:listen + pail + vite) akan **tumpang tindih** dengan
  pembagian peran antar-container. Nasibnya diputuskan di slice #4, bukan sekarang.

**Yang TIDAK berubah:**
- Frontend Vue/Vite **tetap native** di Windows. Ia bukan bagian dari selisih §Stack yang
  dipersoalkan (Queue/Cache/Session/DB), dan dev server frontend tak punya nilai paritas produksi.
- Tak ada perubahan pada Action, Repository, Policy, atau state machine. Migrasi ini menyentuh
  infrastruktur dan gerbang uji, **bukan** logika domain.

## Catatan eksekusi

**[2026-09-12] Slice D1 SELESAI.** `docker-compose.yml` (MySQL 8.4 saja), `docker/mysql/init/`,
Pest pindah ke MySQL lewat `phpunit.xml`, dan `tests/Concurrency/ConcurrentBookingTest.php` lahir.
262 Pest (dari 260), PHPStan lvl 8 & Pint bersih. Empat hal yang tak terduga saat ADR ini ditulis:

1. **Port 3306 sudah dipakai laragon** di mesin dev, jadi container dipetakan ke **3307**.
   `phpunit.xml` memuat 3307 sebagai default; CI memakai 3306 lewat `env:` pada step Pest
   (PHPUnit tak menimpa env var yang sudah ada, jadi tak perlu dua berkas konfigurasi).
2. **Service container MySQL di CI dikerjakan sekarang, bukan ditunda ke D3.** Begitu
   `composer test` menuntut MySQL, workflow tanpa service container akan merah di push
   berikutnya — membiarkan gerbang rusak beberapa slice adalah cara tercepat mengajari orang
   mengabaikannya. **D3 karena itu menyusut** tinggal menambahkan Redis.
3. **Test konkurensi butuh `DatabaseTruncation`, bukan `RefreshDatabase`.** RefreshDatabase
   membungkus tiap test dalam transaksi yang di-rollback, sehingga data parent tak pernah
   terlihat oleh proses anak. Test konkurensi karena itu hidup di testsuite terpisah
   (`tests/Concurrency`), bukan di `tests/Feature`.
4. **Temuan metodologis, yang paling berharga dari slice ini.** Rencana awal menganggap "test
   konkurensi sungguhan" otomatis jadi gerbang. Mutasi membuktikan **tidak**: dengan
   `->lockForUpdate()` dicabut, test 4-proses itu **tetap hijau**, karena
   `increment('booked_count')` tetap mengunci baris di UPDATE-nya dan `DB::transaction(attempts: 3)`
   mengulang closure yang kalah. Sistem selamat lewat jalur yang tak dirancang untuk itu, dan
   keselamatannya bergantung pada penjadwalan OS. Karena itu berkas test memuat **dua** test:
   yang realistis (membuktikan janji) dan yang deterministik atas `select … for update`
   (menjaga janji). Konsekuensi untuk slice berikutnya: **setiap kali menulis test konkurensi,
   jalankan mutasinya — "realistis" tidak sama dengan "menggigit".**

**[2026-09-12] URUTAN DIUBAH: containerisasi app dimajukan mendahului Redis.** Rencana semula
D2 = Redis, D4 = containerisasi app. Saat D2 mulai dikerjakan, ketahuan rencana itu **cacat**:
D2 menaruh cache/session/queue di Redis, tapi app **dan seluruh test suite** masih berjalan di
PHP native Windows — dan PHP itu tak punya klien Redis sama sekali (`extension_loaded('redis')`
= false). Memenuhinya menuntut pemasangan DLL `php_redis` ke `php.ini` laragon: sudah diverifikasi
bisa (6.3.0 ts-vs16-x64, diuji muat lewat `-d`, bersih), tapi itu **mengubah PHP mesin dev yang
dipakai semua proyek**, dan akan **terbuang** begitu app pindah ke container satu slice kemudian.

Pemilik proyek memilih membalik urutan. Konsekuensinya justru menyederhanakan: di dalam image
Linux, klien Redis cukup `pecl install redis` — satu baris, nol sentuhan ke mesin dev. Urutan
berlaku sekarang: **D1 MySQL ✅ · D2′ containerisasi app ✅ · D3′ Redis + Horizon · D4′ CI redis ·
D5 Reverb (opsional)**.

**Keputusan bind mount: TETAP bind mount, repo TIDAK dipindah ke WSL2.** Ini keputusan yang
sengaja ditunda sampai slice ini supaya diambil dengan **angka**, bukan kekhawatiran:

| Menjalankan `composer test` (262 test, 12 proses paralel) | Durasi |
|---|---|
| PHP native Windows → MySQL container | 47 detik |
| Di dalam container, kode lewat bind mount NTFS | 80 detik |

Sekitar **1,7×** lebih lambat. Nyata, tapi jauh dari melumpuhkan, dan jelas lebih murah daripada
memindahkan repo ke filesystem WSL2 (mengubah lokasi proyek, jalur IDE, dan kebiasaan kerja).
`vendor/` **sengaja tidak** dipindah ke named volume meski itu akan lebih cepat: host akan
kehilangan sumbernya, dan IDE + PHPStan di Windows ikut buta. Tinjau ulang kalau angkanya
memburuk atau mulai mengganggu ritme kerja.

**Efek samping yang disengaja:** kedua jalur tetap hidup. `composer test` di Windows masih jalan
(MySQL lewat port 3307), dan `docker compose exec app composer test` juga jalan (MySQL lewat
jaringan compose). Tak ada yang dipaksa pindah di tengah slice.

## Kapan ditinjau ulang

- **Refaktor cache ke `Cache::tags`**: baru boleh **setelah** slice #2 hijau. Sengaja dipisah dari
  migrasi supaya perubahan perilaku cache tidak bercampur dengan perubahan infrastruktur dalam satu
  commit yang sulit di-*revert* terpisah.
- **Slice #5 (Reverb)**: kerjakan hanya kalau ada kebutuhan nyata (mis. deploy sungguhan, atau
  Reverb native mulai menyulitkan). Kalau tidak, biarkan native dan catat alasannya.
- **Pindah repo ke WSL2**: tinjau saat slice #4, atau lebih awal bila I/O container terasa
  menghambat di slice #1–#3.
- **ADR ini di-*supersede*** bila kelak diputuskan kembali ke dev native (mis. Docker terbukti
  lebih banyak menghambat daripada mengajarkan).
