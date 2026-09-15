# ADR-0007 — Migrasi ke Docker penuh + Redis + MySQL; test konkurensi didahulukan

**Status:** Accepted · 2026-09-12 · **dieksekusi penuh 2026-09-14** (D1–D6′ + D5′ dokumen; lihat *Catatan eksekusi*)

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

**[2026-09-12] Slice D3′ SELESAI: Redis + Horizon.** Service `redis` (7-alpine, `appendonly`)
dan `horizon` masuk compose; `app` + `horizon` memakai `CACHE_STORE`/`SESSION_DRIVER`/
`QUEUE_CONNECTION` = `redis`. **Horizon akhirnya benar-benar berjalan** (`horizon:status` →
*Horizon is running*) — pertama kali sepanjang proyek ini, karena `ext-pcntl` yang ia tuntut tak
pernah ada di PHP Windows native. 262 → **265 Pest** di container; native Windows **262 lulus + 3
dilewati**. Tiga hal yang perlu diingat:

1. **Bug yang saya perkenalkan sendiri di slice ini, dan cara menutupnya.** Menyetel
   `CACHE_STORE`/`QUEUE_CONNECTION`/`SESSION_DRIVER` di `environment:` service `app` diam-diam
   **menimpa isolasi `phpunit.xml`** — env var yang sudah ada di environment menang atas `<env>`
   tanpa `force="true"`. Akibatnya seluruh suite yang dijalankan **di dalam container** berhenti
   memakai `sync`/`array`: job ter-antre alih-alih dieksekusi inline, dan test yang mengandalkan
   itu berubah makna **tanpa ada yang merah**. Ditutup dengan `force="true"` pada baris isolasi
   saja. Aturannya sekarang eksplisit di `phpunit.xml`: **environment boleh menentukan DI MANA
   (host/port DB), tidak boleh menentukan APAKAH test terisolasi.** `DB_*` sengaja tetap tanpa
   `force` — justru itu yang wajib berbeda antara Windows dan container.
2. **Gerbang baru yang akhirnya mungkin.** `CODE-WALKTHROUGH §O.3` mencatat jujur bahwa klaim
   `uniqueId()` = `{appointment}:{window}` "dikunci lewat assertion langsung atas `uniqueId()`"
   karena `QUEUE_CONNECTION=sync` melepas lock unique seketika — artinya yang dijaga adalah
   **string**-nya, bukan akibatnya. Dengan Redis nyata, `tests/Feature/Hardening/RedisUniqueJobTest.php`
   menguji **perilaku**: duplikat ditahan selama job masih pending, dan reminder hasil reschedule
   tetap lolos. **Mutasi dijalankan** (`uniqueId()` dikembalikan ke appointment id saja) → 2 dari 3
   test MERAH. Test ini **di-skip** kalau Redis tak terjangkau, supaya jalur native Windows tetap
   hidup; karena itu service `redis` **wajib** ada di CI — gerbang yang dilewati di CI sama saja
   dengan tak ada.
3. **Jebakan `PendingDispatch`.** `dispatch()` mengembalikan `PendingDispatch` yang baru benar-benar
   mendorong job saat objeknya **dihancurkan**. Menyimpannya ke variabel di dalam loop menunda push
   sampai variabel itu ditimpa, dan pengukuran ukuran antrian meleset satu langkah — sempat membuat
   saya salah membaca hasil verifikasi manual.

**[2026-09-14] Slice D6′ SELESAI: scheduler + Reverb di container — dan dua koreksi atas D2′/D3′.**

*Penomoran.* Tabel *Decision* di atas memakai urutan asli (#5 = Reverb). Setelah urutan diubah,
`HANDOVER` memakai **D5′ = dokumen, D6′ = Reverb**; catatan ini mengikuti `HANDOVER`. D6′
dikerjakan **sebelum** D5′ atas keputusan pemilik proyek, supaya dokumen ditulis sekali atas
keadaan yang sudah terbukti.

*Keputusan pemilik proyek (2026-09-14).* **Docker jadi jalur jalan utama; native cukup untuk
`composer test`.** `composer dev` — yang nasibnya ditunda "ke slice #4" di *Consequences* dan tak
pernah diputuskan — **dibiarkan, dengan peringatan native-only** (tanpa perubahan kode).

*Yang dibangun.* Service `scheduler` (`schedule:work`) dan `reverb` (`reverb:start`, port 8080);
env semua service PHP disatukan lewat YAML anchor. Alasan "Reverb opsional karena sudah jalan
native" (tabel *Decision* #5 & *Kapan ditinjau ulang*) ternyata **hanya benar untuk jalur native**:
di container, `REVERB_HOST=localhost` dari `.env` menunjuk container itu sendiri — dibuktikan
dengan mutasi (cURL 7). Scheduler bahkan tak pernah dijalankan siapa pun, di jalur mana pun.

*Dua koreksi — ditemukan dengan memeriksa di mana data benar-benar mendarat, bukan apakah
responsnya 200 atau test-nya hijau:*

1. **Koreksi atas catatan D3′ #1 di atas: `force="true"` sendirian TIDAK menutup isolasi.**
   PHPUnit hanya menulis `$_ENV` + `putenv()`; `env()` Laravel membaca `$_SERVER` lebih dulu, tempat
   env var service compose tertanam. Ditambah `DB_DATABASE` yang sengaja tanpa force, suite di
   container memakai **DB dev `tas`** + Redis + Reverb sungguhan sambil tetap hijau — dan
   `RefreshDatabase` non-paralel mengosongkan DB dev (terbukti: users 7 → 0). Ditutup dengan force
   `DB_DATABASE` + `tests/TestCase.php` menyalin `$_ENV` ke `$_SERVER`. Fix pertama (force saja)
   diuji ulang dan **tidak menggigit** — alasan kenapa uji merah wajib diulang *setelah* fix.
2. **Koreksi atas catatan D2′: app web di container tak pernah memakai MySQL/Redis.** `artisan serve`
   tanpa `--no-reload` membuang env var di luar whitelist dari proses server web (demi auto-reload
   `.env`); image tanpa `php.ini` membuat seluruh env compose ikut terbuang, sehingga server web
   membaca `.env` jalur native (SQLite). Hanya CLI yang benar-benar di container stack. Ditutup
   dengan `--no-reload` di `Dockerfile`.

*Temuan terbuka.* OPcache mati untuk SAPI CLI → tiap request ~3 detik di jalur yang kini utama.
Detail & kandidat fix: `HANDOVER` §Temuan D6′.

**[2026-09-14] Kecepatan app di container + clone kosong + D5′ (dokumen) — migrasi selesai.**

1. **Koreksi atas catatan D2′ "`vendor/` sengaja tidak di named volume".** Begitu Docker jadi jalur
   utama, app di container butuh median **3,4 dtk** per request API (lonjakan 8 dtk). Diukur
   bertahap: `opcache.enable_cli=1` sendirian **tak membantu** (4,0 dtk) walau OPcache terbukti
   aktif; eksperimen `validate_timestamps=0` → 0,2 dtk, jadi biayanya `stat()` ~925 berkas per request
   lewat bind mount NTFS — 816 di antaranya `vendor/`. `validate_timestamps=0` **ditolak** (kode lama
   jalan diam-diam sampai restart). Diputuskan: `vendor/` di named volume `tas-vendor` **hanya di
   dalam container** + OPcache CLI dengan `validate_timestamps=1` → **0,12 dtk** beruntun, 0,68 dtk
   setelah idle, edit PHP tetap terlihat; suite di container 68 → ±30 dtk (kini lebih cepat dari
   native). Alasan D2′ ("IDE & PHPStan host jadi buta") keliru: host tetap memegang `vendor/`
   sendiri. Harganya **dua `composer install`** saat `composer.lock` berubah. Repo **tetap** tidak
   dipindah ke WSL2 — angka sekarang tak lagi menuntutnya. Khusus dev; produksi memanggang kode +
   vendor ke image.
2. **Clone kosong diuji** (stack terisolasi: project, container, volume, dan port terpisah). Temuan:
   `.env.example` mengosongkan kunci Reverb, sehingga dengan compose yang memaksa
   `BROADCAST_CONNECTION=reverb` **setiap job broadcast gagal** (`Pusher error: Not found`) sementara
   booking tetap 201. Diisi nilai khusus dev (preseden: password MySQL dev `secret` sudah ter-commit).
   Setelahnya clone kosong → app jalan ±1 menit, realtime domain end-to-end lulus, 265 test lulus.
3. **D5′ (dokumen).** `SETUP-GUIDE` ditulis ulang Docker-first (panduan build era native dihapus —
   tersedia di `git show pre-docker:docs/SETUP-GUIDE.md`); `README`, `ONBOARDING`, `CLAUDE.md`,
   `CODE-WALKTHROUGH`, `DUMMY-DATA`, `FRONTEND`, `HANDOVER` disinkronkan; klaim yang terbukti salah
   diberi penanda koreksi, bukan dihapus diam-diam.

*Status butir "Kapan ditinjau ulang" di bawah per 2026-09-14:* `Cache::tags` — kini mungkin di semua
jalur yang dipakai (Redis & `array`); **diputuskan tidak dipakai 2026-09-15** (B5, `CLAUDE.md §Cache`) · Reverb — **dikerjakan**
(D6′), bukan opsional: di container ia putus tanpa service sendiri · WSL2 — ditinjau dengan angka,
**tidak** diperlukan setelah volume vendor.

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
