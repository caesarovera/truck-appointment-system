# HANDOVER.md — truck-appointment-system

> Status hidup proyek **antar-sesi & antar-perangkat**.
> Update di **akhir tiap sesi** Claude Code, dan **setiap kali** kontrak/dokumen/seeder berubah.
> Sesi atau perangkat baru: baca file ini **setelah** `CLAUDE.md`.
>
> Pembagian peran (jangan dicampur):
> `CLAUDE.md` = konstitusi (aturan tetap) · `docs/*` = detail rujukan · `HANDOVER.md` = jurnal (keadaan + keputusan terbaru).

---

## Status

### 🐳 Era Docker (ADR-0007) — entri di bawah judul ini ada DI ATAS batas pra-Docker

- `2026-10-30` (bump keamanan seluruh stack) · **[SELESAI]** dipicu rilis keamanan PHP 21 Okt + advisory
  Laravel. **Runtime:** PHP 8.3.33 → **8.3.36** (CVE-2026-109618/109619, RCE), MySQL 8.4.11 → **8.4.13**
  (CPU Okt; upgrade in-place volume sama, backup dulu), Redis 7.4.5 → **7.4.12** (7.4.9/7.4.10/7.4.12
  terlewat; 7.4.10 = perbaikan RESTORE shared-NACK = CVE-2026-66373 — tetap seri 7, tak perlu Redis 8),
  phpredis → 6.3.0 (pecl tak di-pin). Penyebab: tag image mengambang tapi image lokal tak di-pull ulang
  6 minggu–16 bulan → langkah rutin ditambahkan di `SETUP-GUIDE §3`. **Composer** (update terarah ke paket
  rentan `--with-dependencies`, 58 paket minor/patch, tanpa major & tanpa tool dev): laravel/framework
  12.62 → **12.70.2** (CVE-2026-114509 TokenGuard bypass — **tak terjangkau di sini**, guard = session +
  Sanctum; CVE-2026-114508; XSS debug page), symfony/* 7.4.22 (http-foundation ReDoS, yaml, mime header
  injection), league/commonmark 2.10.6, flysystem 3.36 → `composer audit` bersih. **npm** (tetap major sama,
  pin vite 6 dihormati): vue 3.5.44, vite 6.4.5, axios 1.21.0, vitest 4.1.12, concurrently 9.2.5 (+ transitif
  follow-redirects/shell-quote/undici/source-map-js) → `npm audit` 0. **Jebakan:** `npm install vitest@4.1.12`
  gagal `Cannot read properties of null (reading 'edgesOut')` (bug arborist npm 11 di optional peer vitest);
  dipasang sekali dengan `--legacy-peer-deps`, lalu dibuktikan `npm ci` polos (jalur CI) sukses. Audit
  awal `npm audit --omit=dev` menyembunyikan 4 temuan dev (axios ada di devDependencies). **Gerbang:**
  container 310 lolos (0 skip, MySQL + Redis nyata), native 307 + 3 skip Redis, PHPStan 0, Pint bersih,
  Vitest 116, type-check, build. **CI `98b14b3` hijau** (dipantau via `gh`; runner ikut PHP 8.3.36, Pest
  310). Belum: PHP laragon Windows (8.3.31, hanya CLI test/IDE) — urusan mesin user. **Anotasi CI baru
  (belum ditindak):** label `ubuntu-latest` bermigrasi ke Ubuntu 26 sejak 19 Okt 2026 (actions/runner-images#14748)
  — bila suatu saat job merah tanpa perubahan kode, cek ini dulu.

- `2026-10-08` (error simpan form admin) · **[SELESAI] backlog review 2026-10-07 butir "form admin diam".**
  `AdminPage.vue`: keempat handler submit (terminal/gate/company/user) tak punya `catch` → 422 (password
  lemah, email non-ASCII, kode duplikat) jadi rejection tak tertangani, admin melihat form diam. Kini
  lewat `save(action, reset)`: sukses → reset form; gagal → `formError` (pola `formError`/`mapError`
  seperti `MyTrucksPage`; 422 → pesan field pertama, lainnya → `message` API, non-HTTP → generik) di
  `role="alert"` di bawah tab bar, isian admin dipertahankan, error dibuang saat ganti tab. Petunjuk
  kebijakan password + email ASCII di form buat-user. **Lingkup sengaja:** hanya SIMPAN; hapus tetap
  `alert()` lama. 5 Vitest baru (116), 3 mutasi merah (catch tak mengisi error, tanpa reset ganti tab,
  422 abaikan pesan field), `type-check` + `build` bersih. **CI push sebelumnya (`0bed5ec`) hijau**,
  dipantau via `gh`. **Pelajaran:** `sed` mengganti `&` di teks pengganti dengan teks yang cocok —
  merusak sebuah komentar (ketahuan saat dibaca ulang); untuk teks bebas pakai Edit/perl, bukan sed.

- `2026-10-08` (kunci throttle login) · **[SELESAI] backlog (1) dari review 2026-10-07.** Limiter `login`
  kini dua bucket: `email:<huruf-kecil>|ip` 5/mnt dan `ip:` 30/mnt (env `TAS_RL_LOGIN_IP`, baru);
  `email` non-string → `''` (sebelumnya array → 500). **Review keamanan (Opus) menemukan lowercase
  SAJA belum menutup bypass**, dan saya buktikan di MySQL: `utf8mb4_unicode_ci` menyamakan `plánner@`,
  `ｐlanner@` (fullwidth), dan `ß`/`ss` dengan padanan ASCII-nya → 6× kuota per-email. Penutup: aturan
  `ascii` di `LoginRequest` + `Create/UpdateUserRequest` (email non-ASCII berhenti di 422, tak sampai
  `findByEmail`). **Regresi yang tertangkap test:** `ascii` Laravel melempar `Array to string
  conversion` untuk input array (→ 500) dan tak berhenti walau `email` sudah gagal → ketiganya
  `['bail','required','string','email','ascii',…]`. Bucket per-ip memakai `->after()` (hanya upaya
  GAGAL dihitung) agar petugas di satu NAT tak saling mengunci saat pergantian shift. 5 test baru + 3
  mutasi merah (tanpa lowercase, tanpa bucket ip, tanpa `ascii`, tanpa `after`). **Catatan produksi:**
  tanpa `trustProxies` di belakang LB semua klien berbagi satu IP → bucket per-ip jadi kuota global
  (dicatat di `SETUP-GUIDE §17f`; sekarang aman karena `$request->ip()` = REMOTE_ADDR, tak bisa
  dipalsukan). **Tidak dikerjakan:** urutan Limit (bucket email tetap terpakai saat IP penuh — minor).

- `2026-10-07` (hardening login & CORS, temuan audit 2026-09-16 a–c) · **[SELESAI] B6 (a)(b)(c).**
  **(a)** `LoginAction`: email tak terdaftar kini membayar satu `Hash::check` ke hash tiruan ber-cost sama
  (di-cache per konfigurasi hashing; dibuat per request malah membuat jalur itu lebih LAMBAT).
  **Live (rounds 12, median 15×):** sebelum known 322,9 ms vs unknown **1,5 ms**; sesudah 181,1 vs 180,4 ms.
  **(b)** `Password::defaults()` tanpa konfigurasi **sama dengan `min:8`** — mengganti string saja tak
  menguatkan apa pun; kini didefinisikan di `AppServiceProvider` = min 10 + huruf besar/kecil + angka
  (tanpa `uncompromised()`: panggilan API luar, gagal tertutup bila jaringan mati). Hanya berlaku saat
  admin membuat/mengubah password; password lama tak dipaksa ganti. **(c)** `config/cors.php` baru:
  `allowed_origins` dari env `CORS_ALLOWED_ORIGINS`, default kosong (SPA same-origin, `baseURL '/api/v1'`).
  Live: origin asing tak menerima `Access-Control-Allow-Origin`. **Jebakan:** dengan SATU origin terdaftar,
  library CORS memantulkan origin itu di semua respons — test memakai dua origin. 16 test baru (301 Pest),
  3 mutasi menggigit (M3 pertama salah: sed merusak kurung → "0 assertion" = parse error, bukan bukti).
  **Review (`qa-reviewer` + `security-reviewer`, Opus) menemukan test (a) LEMAH:** versi pertama hanya
  menghitung panggilan `Hash::check` (mock `Hash`) — hash tiruan `''` (bcrypt langsung `false`, 1,5 ms)
  tetap hijau 7/7, terbukti lewat mutasi. Diganti spy hash sungguhan: argumen harus bcrypt ber-cost =
  konfigurasi + test "cost berubah → dummy dibuat ulang"; 3 mutasi (`''`, cost dikunci 4, kunci cache
  konstan) kini merah. Pelajaran sama dengan klaim "3 mutasi menggigit" di atas: mutasi harus merusak
  **nilai yang dijaga**, bukan hanya menghapus panggilan.
  **Backlog baru dari review:** (1) ~~kunci throttle login — SELESAI 2026-10-08~~ (asal: kunci throttle login
  `AppServiceProvider.php:72-73` tak dinormalisasi — kolasi `utf8mb4_unicode_ci` menyamakan `Planner@`
  dan `planner@`, jadi tiap variasi kapital dapat kuota 5/mnt sendiri; tak ada limit per-IP (password
  spraying); `email` berupa array → 500. Melemahkan perlindungan brute-force; sudah ada sebelum slice
  ini. (2) tak ada rehash saat login (`Hash::needsRehash`): naikkan `BCRYPT_ROUNDS` → hash tiruan cost
  baru vs user lama cost lama → oracle kembali (terbalik). (3) `DemoSeeder` membuat `admin@tas.test` /
  `password` tanpa cek environment — `db:seed --force` di produksi = akun admin berpassword lemah
  (masuk B6). (4) SPA di domain terpisah butuh lebih dari env: `baseURL`, `echo.ts authEndpoint`,
  `paths` + `broadcasting/*`.
  **Belum:** (d) CSP/HSTS; `config/reverb.php:85` `allowed_origins ['*']` (WebSocket) — **temuan baru**;
  form admin `AdminPage.vue` tak menampilkan error validasi 422 (simpan gagal diam-diam) dan tak ada
  petunjuk aturan password — makin terasa sekarang. **Lingkungan:** stack Docker mati sejak ±2 minggu;
  `composer test` kena timeout 300 dtk Composer saat 12 worker migrasi bersamaan setelah run terputus
  (sisa DB `tas_testing_test_N` setengah jadi → "migrations already exists"); pest langsung
  `--parallel --processes=4` 33 dtk.

- `2026-09-16` (image Docker menumpuk) · **[SELESAI] A + B, pelajaran dicatat di `SETUP-GUIDE §17g`.**
  User melihat 17 baris image PHP @671 MB di Docker Desktop. **Diagnosis [dijalankan]:** `docker system
  df -v` → ke-17 image UNIQUE **0 B** (layer sama, dipakai sekali di disk); asalnya tag per service
  (`build:` di anchor tanpa `image:`), sisa uji `tasfresh-*`, dan `<none>` dari dua build ulang. Ruang
  yang sungguh bisa dibebaskan (±1,8 GB) milik proyek lain → tak disentuh (keputusan user).
  **A:** hapus 4 `tasfresh-*` + 9 `<none>` berlabel `tas` (prune ber-filter label; `<none>` milik
  task-management & `postgres:17-alpine` baru tetap) → image 28 → 15, *reclaimed 0B* sesuai prediksi.
  **B:** `x-php` → `image: tas-php:dev` + `pull_policy: never`, `build:` hanya di `app`. **Dugaan pertama
  gugur saat diuji:** `image:` di anchor saja → Compose v5.5.1 tetap build 4× ke tag yang sama (4 image
  ID, 3 jadi `<none>` tiap build). Sesudah perbaikan: 1 build. Jalur dari nol (`down` tanpa `-v`, hapus
  semua tag PHP, `up -d --wait`): 1 build, 0 error pull, 6 service sehat, data dev utuh, 285 Pest.
  Kasus tepi terbukti di proyek uji terpisah: `up -d horizon` sendirian tanpa image → `No such image`
  (tanpa pull). Hasil: image PHP TAS **17 → 1**, total image 28 → 12.

- `2026-09-16` (audit menyeluruh atas permintaan user) · **[SELESAI] 10 perbaikan, masing-masing commit
  sendiri, CI hijau.** Audit memeriksa: runtime stack, gerbang di semua jalur (container 281, native
  278+3, Vitest 111, vue-tsc, build, e2e 5/5), kontrak layer, keamanan (kode, dependensi, infra), dan
  kemutakhiran dokumen & memori — tiap klaim ditandai dijalankan vs dibaca. Yang terbukti sehat: stack,
  16 migrasi, 50 route terdokumentasi dua arah, tanpa secret ter-commit/raw SQL/XSS, QR ber-HMAC.
  **Diperbaiki (user memilih "semua rekomendasi"):**
  - `4c7e28b` **C1** `APP_ENV` di-force di `phpunit.xml` — suite container berjalan sebagai `local` &
    menulis 244 baris per file test ke log dev (kelas bug D3′/D6′; hanya APP_ENV yang bocor).
  - `037ba03` **S1** port compose → `127.0.0.1` — MySQL (password `secret`), Redis (tanpa password),
    app, Reverb terbukti terjangkau lewat IP Wi-Fi; sesudahnya tertutup, localhost tetap jalan.
  - `39f7af9` **S2** Guzzle 7.15.5 & league/commonmark 2.10.1 — 16 advisory (9 high), dependensi
    produksi; live: broadcast via Horizon & render email markdown dengan versi baru.
  - `7c716ad` **S3** channel `gate.queue.*` tak lagi mengizinkan `driver` — payload berisi
    `booking_code`/status perusahaan lain; SPA tak pernah memakainya sebagai sopir.
  - `907f983` **S4** npm audit 0 (dari 8, 6 high, semua alat dev). `npm audit fix` crash (bug arborist
    `edgesOut`) → update per paket + `vitest@^4.1.11 --legacy-peer-deps`; lockfile 0 paket hilang.
  - `a81e292` **C2** 12 exception domain ber-`render()` → `ShouldntReport` (penolakan 4xx tak lagi
    ERROR + stack trace; log 29 MB) + log container harian. *(Pesan commit menulis "284 Pest" — yang
    benar 285.)*
  - `acd38e5` **K1** `strict_types` di 3 file; `Controller.php` scaffold yang tak dipakai dihapus.
  - `b8f8b4d` + `9e2508d` **K2** Login/Logout/MyFleet/2 controller QR → Action/Repository/Resource.
    Test karakterisasi bentuk respons dikunci DULU, `git diff b8f8b4d -- tests/` kosong; live login
    flat 201, QR 200 + PNG. PHPStan menangkap docblock Sanctum yang terlalu sempit.
  - `2e4f1e2` **K4** checkout v7 / setup-node v7 / cache v6 — anotasi Node 20 hilang (dicek via `gh`).
  - `d9f3818` **D2** `docs/tas-claude-code-guide.html` (rencana build Juni, tak dirujuk) dihapus.
  - `e065a6d` dokumen usang (angka test, "gh tak terpasang", skill `/slice`, agent `security-reviewer`
    + cek otorisasi channel). Memori ditulis ulang: log build & angka lama dibuang.
  **Tidak dikerjakan (dicatat ke B6):** selisih waktu login (enumerasi email, tertahan throttle),
  password min 8 tanpa kebijakan lain, CORS default `*`. **Kesalahan proses yang tertangkap:**
  memulihkan mutasi dengan `git checkout` pada file yang belum di-commit mengembalikan versi LAMA
  (fix S3 hilang sesaat — terdeteksi pengecek skrip, dipasang ulang); sisipan import lewat awk/sed
  membuang backslash dan Pint lalu menghapus import rusak itu — diselesaikan dengan skrip PHP +
  verifikasi refleksi 12/12. **Gerbang akhir:** 285 Pest container, PHPStan lvl 8, Pint, CI hijau.

- `2026-09-16` (CI mandiri + B8 + B7 + kesehatan Claude Code) · **[SELESAI] B8, B7; CI merah diperbaiki.**
  **(1) `gh` CLI terpasang & login** (winget v2.101.0, akun `caesarovera`, scope `repo`) — **pertama kali
  agen bisa membaca CI repo privat ini sendiri**, tanpa menunggu user membuka tab Actions.
  **(2) CI merah di `ee99cac` → hijau di `a631d80`.** Push 4 commit sesi 2026-09-15 langsung ketahuan
  merah: 1 dari 273 — test baru "SPA tetap HTML" (`ApiJsonErrorsTest`) gagal `Vite manifest not found`,
  karena job Backend tak menjalankan `npm run build`; lokal lolos hanya karena `public/build/`
  (gitignored) kebetulan ada. Direproduksi lokal dengan memindahkan manifest → merah persis; fix
  `withoutVite()`. **Koreksi klaim sendiri:** mutasi callback `shouldRenderJsonWhen` jadi selalu `true`
  tak membuat test itu merah (hanya memengaruhi exception; SPA 200 tak melempar) → ia smoke test
  catch-all, bukan penjaga B4; 4 test lain tetap penjaga (§Temuan D2′ dibetulkan).
  **(3) `103c363` B8 — gerbang perilaku** `SlotAvailabilityFreshnessTest`: booking/cancel/no-show/
  reschedule lewat Action sungguhan → sisa kuota dibaca lewat cache segar + ter-broadcast. 7 mutasi,
  semua menggigit tepat. Temuan sampingan: `SlotAvailabilityBroadcastTest` memicu event **manual**, jadi
  tak pernah membuktikan Action memicu event. Tanpa kode produksi.
  **(4) `d0a380c` B7 — alarm job gagal permanen.** Keadaan sebelum [dijalankan]: hanya 1 baris `ERROR`
  (tak bisa dibedakan dari percobaan ulang) + `failed_jobs` + `FAIL` di konsol Horizon. Satu listener
  global `JobFailed` (mencakup job broadcast framework): log CRITICAL + email `TAS_ALERT_MAIL`
  ter-throttle, tak di-queue, try/catch. 4 test + 4 mutasi. **Live:** Reverb dimatikan → broadcast gagal
  di Horizon → CRITICAL dengan nama event & UUID. Sampingan: `horizon:terminate` dari container `app`
  mencetak "No processes to terminate" tapi **tetap** memuat ulang worker (`SETUP-GUIDE §3`).
  **(5) Kesehatan Claude Code (`/doctor`, di luar repo).** Instalasi sehat (npm global di bawah nvm Node
  v22.14.0, versi 2.1.272 = terbaru), tanpa hook/plugin/CLAUDE.md lokal; `CLAUDE.md` dinilai tak perlu
  dipangkas (isinya jebakan & larangan, bukan hal yang bisa diturunkan dari kode). User menolak auto mode
  sebagai default. **`.claude/settings.local.json` dirapikan 105 → 39 aturan** atas pilihan user: ±63
  aturan sekali pakai + ±20 wildcard berisiko dibuang (`php *`, `php artisan *`, `npm install *`,
  `git push/reset/checkout/stash/rm/fetch *`, `filter-branch`, `taskkill /IM php.exe`, `composer dev *`),
  `docker compose *` dipersempit. File lokal (gitignored); cadangan
  `~/.claude/truck-appointment-system.settings.local.json.bak-2026-09-16`.
  **Gerbang akhir sesi:** 281 Pest / 730 assert di container, PHPStan lvl 8 & Pint bersih, **CI hijau di
  `d0a380c` (kedua job, dicek agen via `gh`)**. Vitest & e2e tak dijalankan ulang lokal (frontend tak
  disentuh; job Frontend CI hijau; e2e 5/5 pada 2026-09-15).

- `2026-09-15` (analisis ulang backlog + eksekusi prioritas) · **[SELESAI] N1, B4, N2, B5.** Backlog
  B1–B6 dianalisis ulang terhadap kode & stack hidup (bukan catatan) → 3 temuan baru yang tak tercatat,
  lalu dikerjakan sesuai urutan yang disetujui user.
  **(1) `ad620e0` N1 — ganti password tak mencabut token (P2).** Dibuktikan via tinker dalam transaksi
  yang di-rollback: token `budi@majulog.test` tetap valid setelah password diganti admin → reset
  password untuk akun dicuri tak menghentikan pencuri s/d 12 jam. Fix di `UpdateUserAction`; admin
  yang ganti password sendiri mempertahankan token sesinya. 3 test, mutasi menggigit.
  **(2) `a496193` B4 — error API selalu JSON.** Lebih lebar dari catatan: tanpa `Accept` juga 422→302
  redirect, 404→HTML, `/broadcasting/auth`→500. Dugaan "sebaris cukup" **salah**: butuh
  `shouldRenderJsonWhen` **dan** `redirectGuestsTo(null)` (detail §Temuan D2′).
  **(3) `f1fc1e2` N2 — e2e flaky.** Run pertama 1/5 gagal (booking pertama >5 dtk); ulang 5/5. Dugaan
  "kompilasi Blade, obat `view:cache`" **gugur saat diulang** (dingin pun 0,45–0,50 dtk). Dipasang
  `expect.timeout` 15 dtk sebagai margin, bukan klaim akar hilang (`SETUP-GUIDE §15d`).
  **(4) B5 ditutup — `Cache::tags` tidak dipakai** (`CLAUDE.md §Cache`). Mutasi saat verifikasi
  menemukan celah gerbang baru → **B8** di Backlog.
  **Terbaca, belum dikerjakan:** N3 `failed()` kosong di 3 job → **B7**; B1 dapat alasan tunda baru;
  B3 dapat catatan desain push ganda. **Sekali terjadi:** satu `composer test` di container macet
  >300 dtk (dibunuh batas proses Composer, output kosong); kode yang sama lulus 33 dtk saat diulang —
  penyebab tak diketahui, catat bila terulang. Commit sesi ini di-push 2026-09-16 dan CI-nya **merah**
  di `ee99cac` — penyebab & perbaikan di entri 2026-09-16 di atas.

- `2026-09-14` (kecepatan + clone kosong + D5′) · **[SELESAI] ADR-0007 tuntas.** Atas permintaan user:
  push D6′, perbaiki kecepatan, lalu perbarui **semua** dokumen (perbaiki yang salah, hapus yang usang).
  **(1) `98bf375` perf — request API 3,4 → 0,12 dtk.** Dugaan awal (OPcache CLI mati) **salah
  sebagian**: `opcache.enable_cli=1` sendirian 4,0 dtk walau OPcache terbukti aktif (924 skrip, 8293
  hit). Eksperimen `validate_timestamps=0` → 0,2 dtk membuktikan biayanya `stat()` ~925 berkas lewat
  bind mount NTFS, 816 dari `vendor/`. Opsi yang ditimbang dengan angka: `validate_timestamps=0`
  (ditolak — kode lama jalan diam-diam), `revalidate_freq=60`, WSL2, dan **`vendor/` di named volume**
  (diukur di container percobaan dulu, lalu dipilih user). Hasil: 0,12 dtk beruntun, 0,68 dtk setelah
  idle, edit PHP tetap ≤2 dtk, suite container 68 → **29 dtk** (kini lebih cepat dari native 36 dtk),
  vendor Windows terbukti tak tersentuh (jumlah berkas/mtime/hash), set paket host = container (153,
  versi sama). Membalik catatan D2′ "vendor sengaja tidak di volume". Harga: dua `composer install`.
  **(2) `e3d3746` `.env.example` — kunci Reverb dev.** *Mulai cepat* yang baru **dijalankan dari clone
  kosong** (stack terisolasi: project/container/volume/port terpisah, di-`!override`): dengan kunci
  kosong setiap job broadcast gagal (`Pusher error: Not found`) sementara booking 201. Setelah fix:
  clone → app ±1 menit, realtime domain end-to-end lulus (4,7 dtk), 265 test lulus. Stack verifikasi
  dibongkar; hanya volume `tasfresh-*` yang dihapus.
  **(3) D5′ dokumen.** `SETUP-GUIDE` ditulis ulang Docker-first (nomor topik §9–§18 dipertahankan agar
  rujukan lama tak putus; panduan build era native dihapus); `README` (*Mulai cepat* terverifikasi),
  `ONBOARDING`, `CLAUDE.md` (§Stack, §Cache, §Queue, §Perintah, §Urutan build usang dihapus, klarifikasi
  kredensial dev), `CODE-WALKTHROUGH`, `DUMMY-DATA`, `FRONTEND`, ADR-0007 (catatan eksekusi + status),
  komentar `ci.yml`. **`HANDOVER` 1.694 → ±1.020 baris:** jurnal sesi & changelog pra-Docker dihapus
  (terverifikasi utuh di `git show pre-docker:HANDOVER.md`), §Jebakan/§Lingkungan ditulis ulang,
  §Langkah berikutnya jadi tabel ADR-0007 selesai + Backlog B1–B6.
  **Klaim dokumen yang ternyata salah (diuji, bukan dibaca):** repo "publik" (API 404 — privat);
  contoh curl `driver_id: 6` (sopir company lain → 422; benar `5`); SPA di container memuat aset Vite
  native (**benar**, terverifikasi 200). **Insiden kecil:** menghentikan `npm run dev` lewat task
  runner meninggalkan Vite yatim (PID 37020) + `public/hot` basi — dihentikan per PID, dicatat sebagai
  jebakan `SETUP-GUIDE §9a`.

- `2026-09-14` (D6′ + 2 fix) · **[SELESAI] Scheduler + Reverb di container — dan dua klaim D2′/D3′
  ternyata SALAH; keduanya ditutup dengan bukti merah→hijau.** Keputusan user sesi ini: **Docker
  jadi jalur jalan utama, native cukup untuk `composer test`**; `composer dev` dibiarkan (native
  only — peringatannya ditulis di D5′); pemisahan penuh dokumen pra/pasca-Docker **tidak**
  dikerjakan (effort besar) — D5′ menulis ulang banner & tetap menunjuk tag `pre-docker`.
  **Tiga commit kode:**
  (1) `d186c4a` **fix(test) — isolasi test di container tak pernah berlaku.** `pest` di container
  memakai `DB_DATABASE=tas` (**DB dev**), cache/queue `redis`, broadcast `reverb` — sambil hijau.
  Dua sebab bertumpuk: `DB_DATABASE` sengaja tanpa `force` (garis D3′ ditarik di tempat salah), dan
  **`force="true"` sendirian tak pernah berefek** — PHPUnit menulis `$_ENV`+`putenv`, sedangkan
  `env()` Laravel membaca `$_SERVER` lebih dulu, tempat env compose tertanam. **Bukti merah:** satu
  file test non-paralel di container → 3 passed, `tas` users **7 → 0**; probe: `env('DB_DATABASE')`
  = `tas` walau `$_ENV` = `tas_testing`. **Fix:** force `DB_DATABASE` + `tests/TestCase.php::
  createApplication()` menyalin `$_ENV`→`$_SERVER`. **Hijau:** file yang sama → `tas` utuh; suite
  penuh → `tas` utuh. Jejak bug: schema `tas_test_1..12` (dihapus). **DB dev `tas` ternyata kosong
  total saat sesi dimulai — korban bug ini** — lalu di-seed ulang.
  (2) `38258e7` **fix(docker) — app web di `:8000` sejak D2′ melayani dari SQLite, bukan MySQL.**
  `artisan serve` tanpa `--no-reload` (selama `.env` ada) membuang env var di luar whitelist dari
  proses server web; image tanpa `php.ini` (`variables_order=EGPCS`) → semua env compose terbuang →
  server web membaca `.env` native (sqlite, `database`, `log`). **Hanya CLI** (artisan, Horizon,
  test) yang benar-benar di MySQL/Redis. Akibat: job dari request web masuk tabel `jobs` SQLite yang
  tak pernah dibaca Horizon; `/broadcasting/auth` 200 kosong. **Bukti merah:** token login lewat
  `:8000` masuk SQLite, MySQL 0. **Fix:** `--no-reload` di `Dockerfile`. **Hijau:** token masuk
  MySQL, SQLite 0; auth balas JSON bertanda tangan.
  (3) `234db36` **feat(D6′)** — service `scheduler` (`schedule:work`) + `reverb` (8080), env semua
  service PHP disatukan lewat YAML anchor. **Gap scheduler:** tak ada yang pernah menjalankan
  `routes/console.php` — tidak di container, tidak di `composer dev`; `NoShowSweepJob` & prune token
  cuma terdaftar. **Realtime di jalur container sebelumnya putus** (`REVERB_HOST=localhost` dari
  `.env` = container itu sendiri) — kini **terbukti**, bukan dugaan: mutasi nilai lama → cURL 7.
  **Verifikasi live:** config compose sebelum/sesudah tak kehilangan apa pun; `NoShowSweepJob`
  didorong scheduler & dieksekusi Horizon; klien WebSocket di Windows (`localhost:8080`) menerima
  broadcast dari container; mutasi reverb mati → gagal; **domain end-to-end:** login → auth channel
  privat `slot.2` → booking 201 → event `remaining 4` → cancel → event `remaining 5`.
  Gerbang: container **265 passed (674 assert)**, native **262 + 3 skip (671)**, Pint bersih
  (PHPStan tak mencakup berkas yang berubah).
  **Pola yang berulang — kali ini menimpa saya sendiri:** fix pertama untuk (1) cuma
  `force="true"`, dan **tidak menggigit** (`tas` tetap terhapus). Ketahuan karena uji merah
  **diulang setelah fix**, bukan diasumsikan; tanpa itu fix yang salah sudah ter-commit dengan pesan
  yang meyakinkan — persis kesalahan D3′ yang sedang dikoreksi.
  **Temuan terbuka baru:** §Temuan D6′ (OPcache CLI mati → tiap request ~3 dtk) — **FIXED `98bf375`**
  di sesi yang sama (penyebab sebenarnya `stat()` lewat bind mount, lihat entri di atas).
  **Data dev `tas` sesudah sesi:** hasil seed 2026-09-14 + 1 appointment `CANCELLED`
  (`D6VU0000001`, dari verifikasi domain).
  Docs: entri ini, §Temuan D6′, §MULAI SESI, §Langkah berikutnya, ADR-0007 §Catatan eksekusi,
  `CLAUDE.md` §Stack/§Perintah. README/SETUP-GUIDE/ONBOARDING **sengaja** menunggu D5′.

- `2026-09-12` (D3′) · **[SELESAI] Redis + Horizon — dan Horizon AKHIRNYA benar-benar jalan.**
  **265 Pest di container** (262 + 3 baru), native Windows **262 lulus + 3 dilewati**, PHPStan lvl 8
  & Pint bersih. **Tak ada kode aplikasi yang berubah.**
  **Dibangun:** service `redis` (7-alpine, `appendonly yes` — antrian menyimpan JOB, tanpa
  persistensi `restart` membuangnya) + service `horizon` di compose; `app` & `horizon` memakai
  `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION`=`redis` (**identik** — kalau worker beda
  connection dari pendorong job, job tak pernah terambil dan gejalanya cuma "antrian diam" tanpa
  error); service `redis:7-alpine` + ekstensi `redis` di `.github/workflows/ci.yml`;
  `tests/Feature/Hardening/RedisUniqueJobTest.php` (baru, 3 test).
  **`horizon:status` → "Horizon is running"** — pertama kali sepanjang proyek ini, karena
  `ext-pcntl` yang ia tuntut tak pernah ada di PHP Windows native.
  **BUG YANG SAYA PERKENALKAN SENDIRI DI SLICE INI, lalu ditutup:** menyetel
  `CACHE_STORE`/`QUEUE_CONNECTION`/`SESSION_DRIVER` di `environment:` service `app` **menimpa
  isolasi `phpunit.xml`** — env var yang sudah ada di environment menang atas `<env>` tanpa
  `force="true"`. Akibatnya seluruh suite **di dalam container** diam-diam berhenti memakai
  `sync`/`array`: job ter-antre alih-alih dieksekusi inline, dan test yang mengandalkan itu
  berubah makna **tanpa satu pun berwarna merah**. **[DIKOREKSI 2026-09-14: klaim "ditutup" di
  bawah KELIRU — `force` sendirian tak berefek karena `env()` membaca `$_SERVER`; ditutup sungguhan
  di `d186c4a`, lihat entri D6′.]** Ditutup dengan `force="true"` pada baris
  **isolasi saja**; `DB_*` sengaja tetap tanpa `force` karena justru itu yang wajib berbeda antara
  Windows dan container. Aturannya kini tertulis di `phpunit.xml`: **environment boleh menentukan
  DI MANA, tidak boleh menentukan APAKAH test terisolasi.** Test juga dapat DB Redis sendiri
  (`REDIS_DB=15`) karena `RedisUniqueJobTest` mem-flush-nya — tanpa itu cache & antrian DEV ikut
  terhapus (Redis-nya satu container).
  **Gerbang yang sebelumnya MUSTAHIL, kini ada:** `CODE-WALKTHROUGH §O.3` jujur mencatat bahwa
  klaim `uniqueId()` = `{appointment}:{window}` cuma "dikunci lewat assertion langsung atas
  `uniqueId()`" karena `sync` melepas lock seketika — artinya yang dijaga **string**-nya, bukan
  akibatnya. Dengan Redis nyata, test baru menguji perilaku: duplikat ditahan selama job pending,
  reminder hasil reschedule tetap lolos. **Mutasi dijalankan** (`uniqueId()` dikembalikan ke
  appointment id saja) → **2 dari 3 test MERAH**; dikembalikan → 3/3 hijau. Test ini **skip**
  kalau Redis tak terjangkau (normal di native Windows) — karena itu service `redis` **wajib** di
  CI, sebab gerbang yang dilewati di CI sama saja dengan tak ada.
  **Jebakan `PendingDispatch`:** `dispatch()` baru mendorong job saat objeknya **dihancurkan**;
  menyimpannya ke variabel di dalam loop menunda push satu iterasi dan sempat membuat saya salah
  membaca hasil verifikasi manual.
  **Drift dokumen lama ikut ditambal** (ditemukan karena bertentangan dengan test baru):
  `CLAUDE.md` §Queue masih menulis `uniqueId()` = appointment id (**salah sejak ronde 4**),
  §Stack & §Cache masih menyebut sqlite/`database` sebagai satu-satunya kenyataan, dan
  `CODE-WALKTHROUGH §O.2` memajang `uniqueId()` versi bug seolah kode sekarang.
  Docs: ADR-0007 §Catatan eksekusi, `CLAUDE.md` (§Stack/§Cache/§Queue), `SETUP-GUIDE §18` (baru),
  `CODE-WALKTHROUGH §O.2`+`§O.3`, `README`, entri ini.

- `2026-09-12` (D2′) · **[SELESAI] Containerisasi app — DIMAJUKAN mendahului Redis.**
  **Kenapa urutan diubah:** D2 (Redis) mulai dikerjakan lalu ketahuan rencananya cacat — Redis
  untuk cache/session/queue mengandaikan app bisa bicara ke Redis, padahal app **dan seluruh test
  suite** masih di PHP native Windows yang **tak punya klien Redis sama sekali**
  (`extension_loaded('redis')` = false). Satu-satunya jalan: pasang DLL `php_redis` ke `php.ini`
  laragon. Kelayakannya **sudah diverifikasi** (6.3.0 ts-vs16-x64 diunduh, diuji muat lewat flag
  `-d`, bersih tanpa warning) — tapi itu mengubah PHP yang dipakai **semua** proyek di mesin itu
  dan akan **terbuang** satu slice kemudian. User memilih membalik urutan; hasilnya justru lebih
  sederhana: di image Linux klien Redis cukup `pecl install redis`, nol sentuhan ke mesin dev.
  **Dibangun:** `docker/php/Dockerfile` (baru — PHP 8.3-cli + `pdo_mysql`/`pcntl`/`posix`/`gd`/
  `zip`/`redis`, Composer dari image resmi, user non-root UID 1000), `.dockerignore` (baru),
  service `app` di `docker-compose.yml` (bind mount, env DB via compose, `depends_on` mysql
  healthy). **Terverifikasi:** 262 Pest hijau **di dalam container**, `http://localhost:8000`
  balas 200, `migrate:fresh --seed` jalan di DB `tas`. **[DIKOREKSI 2026-09-14: ketiganya benar
  sendiri-sendiri tapi tak saling membuktikan — server web di :8000 membaca SQLite (`artisan serve`
  membuang env compose), dan "262 Pest di container" menembak DB dev. Ditutup `38258e7` &
  `d186c4a`, lihat entri D6′.]**
  **Keputusan bind mount, diambil dengan ANGKA bukan kekhawatiran** (ini yang sengaja ditunda ke
  slice ini): `composer test` native **47 dtk** vs di container lewat bind mount NTFS **80 dtk** →
  ~**1,7× lebih lambat**. Diputuskan **TETAP bind mount, repo TIDAK dipindah ke WSL2** — nyata tapi
  jauh dari melumpuhkan, dan lebih murah daripada memindahkan lokasi proyek + jalur IDE. `vendor/`
  sengaja **tidak** dipindah ke named volume walau lebih cepat: host akan kehilangan sumbernya dan
  IDE + PHPStan Windows ikut buta.
  **Dua jalur tetap hidup** — `composer test` di Windows (MySQL port 3307) dan
  `docker compose exec app composer test` (MySQL lewat jaringan compose). Tak ada yang dipaksa pindah.
  **Ditemukan saat verifikasi, TIDAK diperbaiki (di luar scope):** lihat §Temuan D2′ di bawah.
  Docs: ADR-0007 (+catatan urutan diubah & tabel angka), `CLAUDE.md` §Stack (**naik status**: app
  kini keadaan, Redis masih target), `SETUP-GUIDE §17` (baru) + banner + daftar isi, `README`,
  entri ini.

- `2026-09-12` (D1) · **[SELESAI] Slice D1: MySQL di Docker + test konkurensi sungguhan.**
  Menutup §Temuan 2026-09-12 (P1). **262 Pest/671 assert** (dari 260/663), PHPStan lvl 8 bersih,
  Pint bersih. Frontend tak disentuh (111 Vitest tetap, tak dijalankan ulang).
  **Yang dibangun:** `docker-compose.yml` (**MySQL 8.4 saja** — Redis/Horizon/app menyusul,
  sengaja tak ditambah sebelum slice-nya), `docker/mysql/init/01-databases.sql`, `phpunit.xml`
  (sqlite `:memory:` → MySQL + testsuite ketiga `Concurrency`), `tests/Pest.php`
  (`DatabaseTruncation` untuk Concurrency), `tests/Concurrency/ConcurrentBookingTest.php` +
  `tests/Concurrency/bin/book-once.php` (baru), `.github/workflows/ci.yml` (+service container
  MySQL).
  **4 hal yang tak terduga waktu plan ditulis:**
  **(1) Port 3306 sudah dipakai laragon** → container dipetakan ke **3307**; `phpunit.xml` memuat
  3307 sebagai default, CI memakai 3306 lewat `env:` di step Pest (PHPUnit tak menimpa env var
  yang sudah ada, jadi cukup satu berkas konfigurasi).
  **(2) Service container MySQL di CI dikerjakan SEKARANG, bukan ditunda ke D3** — begitu
  `composer test` menuntut MySQL, workflow tanpa service container pasti merah di push berikutnya.
  **D3 karena itu menyusut** tinggal menambahkan Redis.
  **(3) Test konkurensi wajib `DatabaseTruncation`, bukan `RefreshDatabase`** — RefreshDatabase
  membungkus tiap test dalam transaksi yang di-rollback, jadi data parent tak pernah terlihat oleh
  proses anak. Karena itu ia hidup di testsuite terpisah, bukan di `tests/Feature`.
  **(4) TEMUAN METODOLOGIS, yang paling berharga dari slice ini:** rencana menganggap "test
  konkurensi sungguhan" otomatis jadi gerbang. **Salah.** Mutasi membuktikan: dengan
  `->lockForUpdate()` dicabut, test 4-proses itu **TETAP HIJAU** — `increment('booked_count')`
  tetap mengunci baris di UPDATE-nya, transaksi berbaris di sana, lalu
  `DB::transaction(attempts: 3)` mengulang closure yang kalah dan pengulangan itu membaca kuota
  yang sudah benar. Sistem "kebetulan selamat" lewat jalur yang sama sekali tak dirancang untuk
  itu, dan keselamatannya bergantung pada penjadwalan OS. Karena itu berkas test memuat **dua**
  test: yang realistis (membuktikan janji, 4 proses PHP sungguhan lewat `symfony/process` dengan
  barrier waktu) dan yang deterministik atas `select … for update` (**menjaga** janji).
  Aturan yang lahir dari ini: **setiap menulis test konkurensi, jalankan mutasinya — "realistis"
  tidak sama dengan "menggigit".**
  **Verifikasi mutasi terakhir:** lock dicabut → test #2 MERAH; lock dikembalikan → 2/2 hijau;
  `git checkout` dipastikan bersih. **Belum di-push** (CI service container belum pernah dieksekusi
  di GitHub — bukti hijaunya menyusul di push berikutnya).
  Docs: ADR-0007 §Catatan eksekusi, `CLAUDE.md` §Hardening/Race (peringatan → ✅ + larangan hapus
  test #2), `ARCHITECTURE §7`, `CODE-WALKTHROUGH §J.1`, `SETUP-GUIDE §16` (baru) + banner, entri ini.

> # ═══ BATAS PRA-DOCKER ═══
>
> Entri **di atas** = era Docker (ADR-0007, 2026-09-12 → sekarang). Jurnal sesi **sebelum** itu —
> dev native Windows + SQLite, 2026-06-27 → 2026-09-12 — **sudah usang dan dihapus dari berkas
> ini** (2026-09-14) supaya bacaan awal sesi tak menyeret keadaan yang tak lagi berlaku. Isinya
> utuh di git: `git show pre-docker:HANDOVER.md`. Fitur dari era itu tetap tercatat di *Sudah
> selesai*; keputusan & temuannya di *Senior review* dan `docs/adr/`.

## Sudah selesai
- [x] **`AppLogo.vue` (1 sumber glyph, ganti 3x duplikat manual) + favicon tab browser
  yang sebelumnya benar-benar kosong (0 byte) — kini svg+png+ico (2026-08-14).** Detail di
  *Status* teratas.
- [x] **Rebrand warna "kepelabuhanan Indonesia" (harbor/signal/sand) — commit+push
  (2026-08-14).** Kerja visual sudah ada di working tree, kini diverifikasi & tercatat.
  Detail di *Status* teratas.
- [x] **5/5 spec e2e hijau — 2 bug lingkungan (mail sync + timezone helper) + 1 kontensi
  paralelisme diperbaiki (2026-08-14).** Detail lengkap di *Status* teratas & §Jebakan.
- [x] **Konfirmasi + loading state di semua tombol aksi lintas role (2026-08-14).** Permintaan
  langsung user, bukan hasil audit. `ConfirmButton.vue` (baru) + `Spinner.vue` (baru) menutup 3
  gap: delete di `AdminPage` (4 tab) pakai `confirm()` native + tanpa loading sama sekali,
  Gate In/Out di `GateDashboardPage` tanpa konfirmasi, Tutup window di `PlannerWindowsPage`
  tanpa konfirmasi. Halaman yang sudah punya pola confirm/pending manual (`MyTrucksPage`,
  `MyBookingsPage`, `RescheduleDialog`, `BookingForm`) cuma ditambah `Spinner`, tak diubah
  strukturnya. Detail lengkap di *Status* teratas.
- [x] **QR gate-in — token ter-sign + endpoint verifikasi + endpoint gambar on-demand (2026-08-14).**
  Menutup satu-satunya sisa temuan ronde 4 (P3). Detail lengkap di *Status* teratas &
  `CODE-WALKTHROUGH §AA`.
- [x] **`DashboardPage` — 4 kartu link diperbaiki sama seperti `AppNav` (2026-08-13).**
  Bug identik yang **belum tertangkap** saat `AppNav.vue` dibetulkan sesi sebelumnya
  hari ini — proyek ini punya **dua** tempat navigasi (kartu Dashboard + navbar
  `AppNav`), dan cuma satu yang dibetulkan waktu itu. Dilaporkan user setelah lihat
  langsung di halaman Dashboard sebagai admin: "Booking Saya"/"Armada Truk"/
  "Jadwal Hari Ini"/"Dashboard Gate" masih tampil walau backend-nya pasti
  403/kosong untuk admin (tak punya `company_id`/`terminal_id`/role `driver`).
  Fix: pola identik — `company_id != null` (Booking Saya, Armada Truk),
  `terminal_id != null` (Dashboard Gate), `hasRole('driver')` (Jadwal Hari Ini).
  Sekalian tambah kartu **"Riwayat Gate"** yang kelupaan waktu fitur itu dibangun
  (ada di `AppNav`, tak ada di Dashboard — drift kecil, ditutup sambil di sini).
  4 test Vitest baru (`tests/js/DashboardPage.test.ts` — **file test pertama untuk
  halaman ini**, sebelumnya 0). Docs: `FRONTEND.md §6` (tabel diperbarui + catatan
  eksplisit: 2 tempat ini harus gating identik, tak ada mekanisme yang memaksa itu
  otomatis — kalau nanti nambah link baru, cek keduanya).
- [x] **Tab "Role & Izin" di Master Data — edit permission 5 role, BUKAN CRUD role
  (2026-08-13).** User minta "add/edit data role"; diklarifikasi jadi 2 kemungkinan
  yang beda jauh cakupannya, user awalnya pilih **"role CRUD penuh (bisa bikin role
  baru)"** — ditolak dulu setelah dijelaskan konsekuensinya: `AppointmentPolicy`
  (jantung otorisasi row-level, `hasRole('gate-officer')`/`hasRole('transporter')`/dst
  untuk scoping terminal/company/self) hardcode nama 5 role yang ada. Role baru lewat
  CRUD murni bisa dikasih permission apa pun tapi **tetap 403** di semua endpoint
  appointment (`default => false` di Policy) — fitur akan terlihat jalan tapi rusak.
  Supaya role baru beneran berperilaku benar butuh refactor Policy jadi data-driven
  (kolom `scope_type`: `NONE`/`TERMINAL`/`COMPANY`/`SELF`) — pekerjaan arsitektur
  terpisah, disarankan ADR dulu. User setuju turun ke scope aman: **editor permission
  untuk 5 role yang sudah ada**, tanpa refactor Policy.
  Backend: `GET /admin/roles` (list role+permission+`meta.all_permissions`) +
  `PUT /admin/roles/{name}/permissions` (sync=replace, bukan tambah) — baru.
  Permission baru `role.manage` (pola sama `terminal.manage`/dst, admin-only
  otomatis karena `admin => $permissions` sudah mencakup semua). `RoleRepository`
  (baru), `UpdateRolePermissionsAction` (baru) — **menolak role `admin`**
  (`ImmutableRoleException` 422 `role_immutable`, **ditegakkan server**, bukan cuma
  UI — kalau admin bisa hapus `role.manage`/`user.manage` dari role sendiri, tak
  ada jalan balik tanpa akses DB langsung). Route param `{role}` **bukan**
  route-model-binding implisit (Spatie `Role` bukan Eloquent model biasa proyek
  ini, dan sudah pernah kejebak jebakan PHPStan route-binding — lihat
  `CODE-WALKTHROUGH §V.4`) — resolve manual via `RoleRepository::find()`.
  Frontend: tab ke-5 `AdminPage.vue` — checkbox grid (baris=role, kolom=permission),
  role `admin` checkbox `disabled` + tanpa tombol Simpan, state lokal `editing`
  disinkronkan ulang dari server tiap query berubah (`watch`), 1 tombol Simpan per
  baris (sync array penuh, bukan diff).
  Test: `tests/Feature/Admin/RolePermissionsTest.php` (baru, 7: list+universe,
  sync=replace, 422 admin immutable, 422 permission tak dikenal, 404 role tak
  dikenal, 403 non-admin, 401). `tests/js/AdminPage.test.ts` (**baru — AdminPage
  sebelumnya 0 test Vitest sama sekali** meski 4 tab lain sudah lama ada; gap lama
  dilaporkan ke user, **tak** ditutup penuh di sini — cuma tab Role yang di-test,
  di luar scope slice ini untuk backfill 4 tab lama).
  PHPStan sempat merah: `RoleRepository::allPermissionNames()` — `pluck('name')->all()`
  tak provable sebagai `list<string>` (union `array<int,mixed>`); diperbaiki jadi
  loop eksplisit (`foreach (...->pluck('name') as $name) { $names[] = (string) $name; }`)
  — **bukan** `@var` override (dilarang instruksi PHPStan sendiri).
- [x] **Halaman "Riwayat Gate" (planner) + gate in/out di "Booking Saya" (transporter)
  (2026-08-13).** Dipicu pertanyaan user langsung: "gimana cara tahu truk yang sudah
  gate-in/out". Sebelum ini cuma bisa lewat menu Gate (gate-officer — hilang begitu
  status `COMPLETED`, antrian bukan riwayat) atau API audit trail mentah tanpa UI.
  Backend: `GET /api/v1/reports/gate-history?gate=&date=` (baru) —
  `GateHistoryReportRequest::authorize()` = `hasAnyRole(['admin','planner'])` (pola
  identik `UtilizationReportRequest`, sibling paling mirip), repo
  `gateHistoryForGate()`: `whereHas('gateIn')` (sudah gate-in, gate-out opsional) +
  `whereRelation('slotWindow', 'gate_id', $gateId)` (**bukan** nested-closure
  `->where('gate_id', …)` di dalam `whereHas` — itu yang bikin PHPStan merah sekali,
  pola persis yang sudah dihindari `queueForTerminal` dan lupa diikuti di draf
  pertama). Transporter **tak dapat endpoint baru** — `forCompany()` (dipakai
  "Booking Saya") cukup ditambah `gateIn`/`gateOut` ke eager-load, field
  `gate_in_at`/`gate_out_at`/`dwell_minutes` di `AppointmentResource` sudah ada sejak
  slice `dwell_minutes`. `AppointmentResource` +`company` (whenLoaded, baru — planner
  lintas-company perlu tahu truk itu milik siapa, sebelumnya cuma `company_id` angka
  polos). Frontend: `GateHistoryPage.vue` (route `/planner/gate-history`, link
  "Riwayat Gate" di `AppNav` gated `slot.manage` — actor sama persis dgn backend
  `hasAnyRole`), urut kronologis diserahkan ke klien (konsisten dgn keputusan repo
  tak sort kolom relasi); `MyBookingsPage.vue` tambah baris Gate in/Gate out per
  card kalau ada. 5 test Pest baru (`GateHistoryReportTest` 5 + `MyAppointmentsTest`
  +1) + 20 test Vitest baru/diperluas (`GateHistoryPage` 6 baru, `MyBookingsPage` +2,
  `AppNav` +1 assert). Docs: `BUSINESS-FLOW §1` (baris matriks baru + footnote³⁴) &
  `§3.7`, `CODE-WALKTHROUGH §N.7` (baru), `SETUP-GUIDE §10d` (+baris `gate-history`,
  **dan menambal drift lama**: baris `no-show` manual dari sesi sebelumnya ternyata
  lupa ditambahkan ke tabel ini), `FRONTEND.md §4`+`§6`.
- [x] **`AppNav` sembunyikan link operasional yang pasti gagal untuk admin (2026-08-13).**
  Dilaporkan langsung oleh user (bukan dari audit): login admin, klik "Booking Saya"/
  "Armada"/"Gate" → 3 warning "Gagal memuat...", dan "Jadwal Hari Ini" tampil tapi selalu
  kosong tanpa penjelasan. Sebab: `AppNav` gate link cuma pakai `can(permission)`, dan
  admin (`RolePermissionSeeder`) punya **semua** permission by design — tapi 3 endpoint di
  baliknya `abort_if($user->company_id/terminal_id === null, 403)` karena admin memang
  tak punya identitas company/terminal (bukan bug backend, itu tepat sesuai desain: admin
  ≠ transporter/gate-officer). `/today` beda lagi: `todayForDriver(admin.id, …)` tak pernah
  403, cuma balikin list kosong karena admin bukan `driver_id` appointment mana pun. Fix
  di `AppNav.vue`: `/bookings` & `/trucks` tambah cek `auth.user?.company_id != null`
  (pola yang sudah ada di `/reports`), `/gate` cek `terminal_id != null`, `/today` cek
  `auth.hasRole('driver')` (satu-satunya link ber-role, dijelaskan di komentar kenapa beda
  dari yang lain). 3 test Vitest baru di `AppNav.test.ts` (admin tak melihat 4 link itu
  sekaligus, driver asli tetap melihat "Jadwal Hari Ini"). Segolongan dengan bug
  `dwell_time`/badge "Tersedia" sebelumnya: aturan sudah benar di satu layer (backend),
  tak dicerminkan ke layer lain (nav FE) — recurring pattern di proyek ini, ketiganya
  ditemukan hari yang sama lewat pemakaian nyata, bukan audit dokumen.
- [x] **Badge "Tersedia" & pesan error booking dibedakan dari window yang sudah berakhir
  (2026-08-13).** Bug ditemukan saat verifikasi realtime manual (§Langkah berikutnya #2):
  `SlotWindow::hasEnded()` sudah ditegakkan `BookAppointmentAction` sejak ronde 2, tapi
  **tak pernah diekspos ke API** — `SlotWindowResource` cuma punya `remaining`/`status`.
  Akibatnya window `OPEN` yang `window.end`-nya sudah lewat tetap tampil badge **"Tersedia"**
  + tombol Booking di `/slots`, dan begitu diklik selalu 409 dengan pesan generik ("Slot sudah
  penuh atau ditutup") walau `SlotUnavailableException` sebenarnya sudah kirim pesan spesifik
  (`full()`/`closed()`/`expired()`). Fix: `SlotWindowResource` +`ended` (dari `hasEnded()`,
  murni baca kolom yang sudah dimuat — bukan query baru); `SlotAvailabilityPage` badge baru
  **"Berakhir"** (menang atas sisa kuota — window besok kuota penuh tapi tanggalnya sudah
  lewat tetap "Berakhir") + tombol Booking disembunyikan bila `ended`; `BookingForm` pakai
  `message` asli dari backend utk `slot_unavailable` alih-alih teks hardcode generik. 2 test
  Pest baru (`SlotAvailabilityTest`: window berakhir → `ended:true`, window belum mulai →
  `ended:false`) + 3 test Vitest (badge "Berakhir" + tombol tersembunyi, pesan backend tampil
  verbatim, fallback generik kalau `message` kosong). **Pola bug ini segolongan dengan beberapa
  temuan *Senior review* sebelumnya** (mis. `TruckStatus::INACTIVE` yang cuma disaring
  repository tapi tak dicek Action, `driver_id` role): aturan ditegakkan di satu tempat tapi
  tak pernah dicerminkan ke representasi lain yang seharusnya ikut tunduk — di sini bukan dua
  layer backend, tapi Action (benar) vs Resource/FE (ketinggalan), kelasnya tetap sama:
  kontrak yang "sudah ditegakkan" ternyata cuma ditegakkan sebagian.
- [x] **No-show manual gate-officer (2026-08-13).** Menutup temuan P2 *ronde 4*: matriks §1
  "Tandai no-show" memberi ✅ ke gate-officer, tapi `MarkNoShowAction` **hanya** dipanggil
  `NoShowSweepJob` — tak ada route, tak ada ability di `AppointmentPolicy` untuk petugas gate
  menandai manual. `POST /api/v1/appointments/{id}/no-show` (baru): `MarkNoShowController` reuse
  `MarkNoShowAction` **apa adanya** (tak ada perubahan Action/state machine), route pakai
  ability `process` yang sama dgn gate-in/out (bukan ability baru — scope aktornya identik:
  gate-officer di terminal appointment, admin via `before()`) + middleware `idempotency`.
  **Sengaja tak ditambah guard idempoten no-op** seperti gate-in/out — panggilan kedua setelah
  status berubah tetap 409 `invalid_state`, mengikuti pola `reschedule`/`cancel`, bukan
  `gate-in`/`gate-out`; perlindungan double-tap cukup dari middleware `idempotency`. 6 test
  Feature (`tests/Feature/Gate/MarkNoShowTest.php`: BOOKED & CONFIRMED bisa ditandai, kuota
  balik, 409 kalau sudah gate-in/completed/cancelled, 403 lintas-terminal & non-gate-officer).
  Docs: `BUSINESS-FLOW §3.5` (blockquote no-show + matriks §1 baris "Tandai no-show" +
  footnote²), `CODE-WALKTHROUGH §N.6` (baru).
- [x] **`dwell_time` diekspos sebagai `dwell_minutes` (2026-08-13).** Menutup bagian `dwell_time`
  dari temuan P3 *ronde 4* (janji kontrak `BUSINESS-FLOW §3.6` "hitung dwell_time" tanpa kode).
  `Appointment::dwellMinutes(): ?int` — dihitung dari `gateIn->processed_at` ke
  `gateOut->processed_at` (relasi yang sudah dimuat, **bukan** query baru — konsisten dgn
  `preventLazyLoading` non-production); null selama `gateIn`/`gateOut` belum di-eager-load atau
  gate-out belum terjadi. Tidak butuh migrasi/kolom baru — datanya sudah ada di
  `gate_transactions` sejak slice gate-in/out. Diekspos di `AppointmentResource.dwell_minutes`
  (whenLoaded implicit lewat method, sama seperti `gate_in_at`/`gate_out_at`). 3 test Unit
  (`tests/Unit/AppointmentDwellTimeTest.php`: relasi tak dimuat→null, gate-out belum
  terjadi→null, hitung menit benar) + 1 test Feature (`GateOutTest`, jam dibekukan `travelTo`,
  assert `data.dwell_minutes` di respons gate-out). **QR masih terbuka** — lihat *Senior review
  ronde 4* & *Langkah berikutnya*.
- [x] **Endpoint audit trail `GET /appointments/{id}/audit` (2026-08-02).** Menutup temuan P2
  terakhir *ronde 4*: baris matriks §1 "Lihat audit log" selama ini **cuma desain** — `audit.read`
  sudah lama diberikan ke planner & transporter di seeder, tapi **tak ada satu pun rute**
  memakainya. Log direkam sejak awal dan tak pernah bisa dibaca siapa pun. **Bentuknya
  per-appointment, bukan endpoint daftar** — bukan demi kesederhanaan tapi keamanan: menempel
  pada appointment berarti isolasi datanya memakai `AppointmentPolicy::view` yang **sudah ada dan
  sudah ber-test**; endpoint daftar menuntut permukaan scoping BARU, dan scoping baru adalah
  tempat kebocoran antar-tenant lahir. **Otorisasi dua lapis**, dan lapis kedua yang benar-benar
  bekerja: `can:view,appointment` (isolasi per-record) **+** `audit.read` di FormRequest.
  Lapis 1 saja tidak cukup — **gate-officer** (terminal cocok) dan **driver** (appointment
  sendiri) sama-sama LOLOS Policy, padahal matriks §1 tak memberi mereka akses audit, karena
  trail memuat **nama orang yang mengubah**. Tanpa lapis 2, sopir bisa membaca siapa saja yang
  menyentuh jadwalnya. Dua test khusus menguncinya. **Kode:** `AuditRepositoryInterface` +
  `AuditRepository` (bound di `AppServiceProvider`; `with('causer')` **wajib** karena
  `preventLazyLoading`, dan disaring `log_name='appointment'` supaya tak bocor ke log domain lain
  kelak), `AppointmentAuditRequest`, `ActivityResource`, `AppointmentAuditController`, 1 route.
  Respons mengekspos `causer: null` apa adanya = tindakan sistem. **Jebakan PHPStan:**
  `Activity::$properties` nullable menurut Spatie → `?->toArray() ?? []`. **Belum ada UI** —
  endpoint dulu, halaman menyusul (pola sama dengan `/me/reports/utilization`). +7 test →
  **219 Pest / 574 assert**. Detail: `CODE-WALKTHROUGH §Z`.
- [x] **Test audit trail Activity Log (2026-08-02).** Menutup temuan P2 *ronde 4*: `Appointment`
  memakai `LogsActivity` sejak awal dan DoD `CLAUDE.md` mensyaratkan "perubahan status tercatat
  di Activity Log", tapi **nol test** menjaganya — hapus trait-nya dan semua gerbang tetap hijau
  sementara audit trail diam-diam kosong. **7 test** di `tests/Feature/Audit/ActivityLogTest.php`,
  semuanya lewat **endpoint sungguhan** (bukan `save()` langsung) supaya jalur auth ikut teruji:
  booking (`created` + causer transporter), gate-in (**dua** entri), gate-out, cancel, reschedule
  (`slot_window_id`+`version`, status tak berubah), no-show sweep (**causer NULL** = tindakan
  sistem), dan batas cakupan. Dua klaim yang selama ini cuma hidup sebagai komentar kini terkunci:
  `recordGateIn` sengaja dua `save()` supaya transisi perantara tak hilang, dan causer NULL yang
  membedakan tindakan sistem dari manusia. **Gerbangnya dibuktikan menggigit lewat mutasi**, bukan
  diasumsikan: cabut `use LogsActivity;` → **7 failed**, kembalikan → **7 passed**. Mutasi itu
  sekaligus menemukan lubang di test saya sendiri — test negatif "ubah `truck_id` → tak ada entri"
  awalnya **lolos walau logging mati total** (`0 == 0`), diperbaiki dengan satu baris jangkar
  `expect($before)->toBeGreaterThan(0)`. Tanpa langkah mutasi, lubang itu ikut ter-commit.
  **Tak ada kode produksi yang berubah** — murni menambal gerbang. +7 test → **212 Pest / 553 assert**.
  **Temuan yang TIDAK diperbaiki (sengaja):** ganti truk/sopir pada appointment yang sama tak
  terekam (`truck_id`/`driver_id` di luar `logOnly`), jadi audit tak bisa menjawab "siapa menukar
  sopirnya" — keputusan produk, kini tertulis di `BUSINESS-FLOW §3.7` **dan** ter-test batasnya,
  supaya melebarkannya harus disengaja. Detail: `CODE-WALKTHROUGH §Y`.
- [x] **Reminder ikut pindah saat reschedule (2026-08-02).** Menutup temuan P1 kedua
  *Senior review ronde 4*: `§3.3` selalu menjanjikan reminder ikut berpindah, tapi listener
  satu-satunya hanya mendengarkan `AppointmentBooked`. Appointment yang digeser tetap
  `CONFIRMED`, jadi reminder meledak di jam window **lama** dan window baru tak pernah dapat
  reminder — **sopir yang jadwalnya dipindah tidak diingatkan sama sekali**. **Fix:** contract
  `SchedulesAppointmentReminder` (diimplementasikan `AppointmentBooked` + `AppointmentRescheduled`),
  `ScheduleAppointmentReminder` type-hint interface itu → satu listener melayani dua event, pola
  sama dengan `AffectsSlotAvailability`. Interface **baru**, bukan menumpang `AffectsSlotAvailability`:
  cancel/no-show/buka-tutup-window juga mengimplementasikannya dan tak satu pun boleh menjadwalkan
  reminder. **Jebakan yang membuat "tinggal dispatch ulang" TIDAK cukup:** `ShouldBeUnique`
  memegang lock selama job masih **pending** di queue (baru lepas saat diproses) — dengan
  `uniqueId()` = appointment id saja, reminder baru hasil reschedule **dibuang diam-diam** oleh
  Laravel dan fix-nya akan terlihat benar di kode tapi mati di queue sungguhan. Karena itu job
  membawa `slotWindowId` dan `uniqueId()` = `{appointmentId}:{slotWindowId}`. Job lama **tidak
  dibatalkan** (job yang sudah antre tak bisa ditarik dari queue) melainkan **menetralkan dirinya
  sendiri** lewat guard `slot_window_id !== $this->slotWindowId` di `handle()` — `BUSINESS-FLOW §3.3`
  ikut dikoreksi dari "dibatalkan" jadi "dinetralkan" supaya orang berikutnya tak mencari kode
  pembatalan yang tak pernah ada. Ikutan bersih: listener kini memakai `SlotWindow::startsAt()`
  (helper dari slice gate-in) menggantikan hitungan `date+start_time` manual — satu sumber kebenaran.
  **Catatan test:** `QUEUE_CONNECTION=sync` bikin lock unique langsung lepas, jadi jebakan
  ShouldBeUnique **tak bisa** ditangkap test perilaku — dikunci lewat assertion langsung atas
  `uniqueId()`. +5 test → **205 Pest / 523 assert**. Detail: `CODE-WALKTHROUGH §O.3`.
- [x] **Toleransi jendela waktu gate-in (2026-08-02).** Menutup temuan P1 terberat
  *Senior review ronde 4*: `GateInAction` hanya bertanya "status CONFIRMED?" dan **tak pernah
  melihat jam** — appointment minggu depan bisa gate-in hari ini. `config/tas.php` bahkan tak
  punya kunci toleransi sama sekali, padahal `BUSINESS-FLOW §2`/`§3.5` dan `PRD §4` menjanjikan
  "toleransi early/late dari config" sejak awal. **Kelas bug ketiga yang identik** setelah
  `INACTIVE` & `driver_invalid_role`: aturan ada di dokumen, dipagari di tempat lain, tak pernah
  ditegakkan di Action. Dampaknya bukan kosmetik — kuota jam 08:00 bisa dipakai truk yang muncul
  jam 20:00, jadi laporan utilisasi per window melaporkan yang tak pernah terjadi, dan meratakan
  kedatangan (alasan TAS ada) berhenti berlaku. **Fix:** `config/tas.php → gate_in`
  (`early_minutes`/`late_minutes`, default 30/30, env `TAS_GATE_IN_EARLY_MINUTES`/`_LATE_`),
  `SlotWindow::startsAt()` (cermin `endsAt()` — satu sumber kebenaran `date+time` dengan deadline
  no-show & guard booking), `GateInWindowException` (409 `gate_in_too_early`/`gate_in_too_late`,
  **terpisah** dari `invalid_state` karena di sini status-nya sah, jamnya yang salah), guard di
  `GateInAction` **setelah** cek idempoten & state. **Urutan guard dikunci test:** idempoten →
  state → waktu. Truk yang sudah di dalam tetap 200 walau retry-nya telat berjam-jam (double-tap
  petugas gate itu normal); `CANCELLED` yang datang kepagian tetap `invalid_state`. **Kenapa tak
  cukup `NoShowSweepJob`:** sweep itu eventual (5 menit) dan diam total kalau worker mati,
  sementara gate-in tetap melayani → guard harus sinkron. Default `late` sengaja sama dengan
  `no_show_grace_minutes` (tenggat sama dari dua sisi — ubah bersamaan; alasannya di komentar
  config). Ditulis lewat loop TDD: merah dulu dengan bukti **200 alih-alih 409**. **Ikutan:**
  `SlotWindowFactory::ongoing()` — default factory "besok" (dibuat agar valid di-book) justru
  **terlalu awal untuk gate-in**, jadi semua test gate-in lama akan merah; diperbaiki di
  default factory, bukan ditambal per-test (pola sama dengan `UserFactory::driver()`). State-nya
  menjepit jam di hari yang sama supaya tak flaky dekat tengah malam, dan `GateInTest` membekukan
  jam via `travelTo`. +7 test → **201 Pest / 519 assert**; Vitest tetap **87** (FE tak berubah:
  `extractError` di `GateDashboardPage` sudah fallback `data.message`, pola sama `truck_inactive`).
  Detail: `CODE-WALKTHROUGH §X`, `BUSINESS-FLOW §2`/`§3.5`.
- [x] **Penegakan role sopir saat booking + ADR-0006 (2026-07-27).** Menutup bug P1 dari
  *Senior review ronde 3*: `driver_id` menerima user mana pun se-company — termasuk akun
  transporter sendiri — dan booking-nya **berhasil 201**. Kelas bug yang **sama persis**
  dengan `INACTIVE` sesi sebelumnya: repository menyaring, Action tidak. Fix: guard ketiga
  `->role('driver','api')->exists()` di `BookAppointmentAction`, **setelah** cek kepemilikan
  (kalau dibalik, beda pesan error membocorkan keberadaan user company lain) →
  `InvalidDriverException` (422 `driver_invalid_role`). Ditulis lewat loop TDD: test merah
  dulu dengan bukti `201 alih-alih 422`, baru guard-nya dipasang. **Akar kenapa lolos lama:**
  3 helper booking di test membuat "sopir" **tanpa role** — kini semua memakai
  `UserFactory::driver()` (state baru, `Role::findOrCreate` jadi jalan dengan/tanpa seeder).
  `BookingRateLimitTest` sempat ikut merah — itu bukti guard-nya menggigit, bukan gangguan.
  +3 test → **194 Pest / 501 assert**; Vitest tetap **87** (FE tak berubah: pesan server lolos
  lewat fallback `data.message` di `BookingForm`, pola sama dengan `truck_inactive`).
  **Keputusan produk menyertainya:** `ADR-0006` — **CRUD sopir tidak dibangun di MVP**, sopir
  dikelola admin lewat Admin User CRUD; transporter tetap melihat sopirnya via `/me/fleet`.
  `PRD §3` dikoreksi dua sisi (IN dipersempit, OUT ditambah) karena aturan PRD "di luar IN
  tidak dikerjakan tanpa memperbarui PRD" berlaku **dua arah**. Detail: `CODE-WALKTHROUGH §W.5`,
  `BUSINESS-FLOW §3.2`.
- [x] **CI GitHub Actions (2026-07-25) — menutup klaim kontrak yang selama ini tidak benar.**
  `CLAUDE.md` mencantumkan "CI: GitHub Actions" sejak awal, tapi **`.github/` tidak pernah ada**.
  Akibatnya nyata: 191 Pest + 87 Vitest hanya jalan bila ada yang ingat mengetik perintahnya —
  di proyek lintas-sesi/lintas-perangkat seperti ini, gerbang kualitas praktis tak menjaga apa pun
  saat `push`. `.github/workflows/ci.yml` = **2 job paralel**: `backend` (`composer lint`→`analyse`
  →`test`) & `frontend` (`npm run test:js`→`type-check`→`build`). **Prinsip: CI memanggil perintah
  yang SAMA PERSIS dengan lokal** — kalau CI punya rangkaian sendiri, "hijau di CI" dan "hijau di
  laptop" berhenti berarti sama. Konsekuensi yang harus diingat: **menambah gerbang baru di lokal
  tanpa menambahkannya ke workflow = gerbang itu tak berlaku saat push.** Detail keputusan lain:
  `if: !cancelled()` (satu push melaporkan SEMUA gerbang rusak, bukan berhenti di yang pertama);
  **tanpa `--ignore-platform-req`** (flag itu kebutuhan Windows karena Horizon minta `ext-pcntl`/
  `ext-posix`; di Linux ada, jadi CI memverifikasi dependensi apa adanya); ~~tanpa service container
  DB (`phpunit.xml` memaksa sqlite `:memory:`)~~ **[usang sejak D1/D3′: CI kini punya service
  `mysql:8.4` + `redis:7`]**; versi dipatok menyamai mesin dev (PHP 8.3, Node 22).
  Cara pakai + tabel gejala→sebab saat CI merah: `docs/SETUP-GUIDE.md §14`. Alasan CI didahulukan
  & **e2e ditunda**: `docs/adr/0005-ci-github-actions.md`.
  **TERVERIFIKASI hijau** (run [30164709068](https://github.com/caesarovera/truck-appointment-system/actions/runs/30164709068),
  commit `f7f4641`): **kedua** job benar-benar jalan — `backend` 32 dtk & `frontend` 30 dtk, paralel
  → **37 dtk** total. Angka itu berguna sebagai basis: kalau suatu saat melonjak jauh, curigai cache
  Composer/npm meleset (key-nya `composer.lock`/`package-lock.json`).
- [x] **Polish UI — skeleton loading (2026-07-25):** `components/SkeletonRows.vue` (props `rows`,
  `label`) menggantikan teks `Memuat…` di **11 titik / 8 halaman + `RescheduleDialog`**. Alasan
  utamanya bukan estetika: placeholder setinggi konten asli menghapus **layout shift** saat data
  masuk. **Aksesibilitas dijaga:** balok abu-abu tak mengabarkan apa pun ke pembaca layar, jadi teks
  lama dipindah ke `<span class="sr-only">` di dalam `role="status" aria-busy="true"` dan balok
  visualnya `aria-hidden`. Efek samping enak: test lama yang meng-assert teks `Memuat laporan`
  **tetap hijau tanpa diubah** — bukti integrasi skeleton↔halaman sekaligus.
  **Sengaja TIDAK diubah:** indikator `isFetching` di `/slots` (refetch latar saat data lama masih
  tampil) — menggantinya dengan skeleton justru menyembunyikan data yang sudah benar. Skeleton hanya
  untuk `isLoading` (belum ada data sama sekali). +3 Vitest → **87 Vitest**. Detail: `docs/FRONTEND.md`
  (§4 → *Loading state*). **Sisa dari task Polish UI: e2e happy-path** — belum, butuh paket baru
  (lihat *Langkah berikutnya* #2).
- [x] **CRUD armada truk transporter + penegakan `INACTIVE` (2026-07-25):** transporter kelola
  armadanya sendiri tanpa lewat admin. **BE:** `Create/Update/DeleteTruckAction`, `TruckData` (Spatie
  Data), `UpsertTruckRequest`/`DeleteTruckRequest`, 4 controller invokable di `Fleet/`, `FleetRepository`
  +`createTruck`/`updateTruck`/`deleteTruck`, `EntityInUseException::truck()`, relasi `Truck::appointments()`,
  4 route `/me/trucks` (GET/POST/PATCH/DELETE). **company_id selalu dari user login, tak pernah dari body** —
  itu beda inti dengan Admin CRUD yang lintas-company. Plat unik **per company** (`Rule::unique(...)->where('company_id')`)
  bukan global: dua perusahaan boleh punya plat sama. Hapus truk ber-riwayat appointment → **409
  `entity_in_use`** (FK `RESTRICT`; hard-delete merusak audit trail). **FE:** `api/trucks.ts`,
  `useTrucks.ts` (mutasi invalidate `me-trucks` **dan** `me-fleet` → dropdown booking ikut segar),
  `MyTrucksPage.vue` (form create/edit dipakai bersama, hapus konfirmasi 2-langkah, map 409/422),
  route `/trucks` + link nav/dashboard gated `fleet.manage`.
  **[BUG DIPERBAIKI] `INACTIVE` cuma label.** Ditemukan saat review slice ini: `BookAppointmentAction`
  hanya cek kepemilikan, `trucksForCompany()` tak menyaring status → truk INACTIVE **muncul di dropdown
  booking dan berhasil di-book (test membuktikan 201)**. Padahal pesan 409 hapus-truk secara eksplisit
  menyuruh "nonaktifkan saja". Fix: `InactiveTruckException` (422 `truck_inactive`) + guard di
  `BookAppointmentAction` **setelah** cek kepemilikan (kalau dibalik, beda pesan error membocorkan
  keberadaan & kondisi truk company lain) + `trucksForCompany($companyId, ?TruckStatus $status = null)`
  → `/me/fleet` ACTIVE saja, `/me/trucks` semua status (biar bisa diaktifkan lagi). Menyaring dropdown
  saja tak cukup: truk bisa dinonaktifkan saat form booking terbuka → Action tetap menolak; pesan server
  lolos ke UI lewat fallback `data.message` di `BookingForm` (tak perlu mapping baru).
  **Test:** 13 `tests/Feature/Fleet/TruckCrudTest.php` + 3 penegakan INACTIVE (2 di `Booking/`, salah satunya
  **mengunci urutan guard**; 1 di `Reference/MyFleetTest`) + 2 file Vitest → **191 Pest / 84 Vitest**.
  Detail kode: `docs/CODE-WALKTHROUGH.md §W`; aturan bisnis: `docs/BUSINESS-FLOW.md §3.2`.
- [x] **Realtime wiring — Reverb + Echo (2026-07-25):** Kuota slot & antrian gate kini live.
  **Slice A (BE, TDD):** `bootstrap/app.php` — `channels:` dipindah dari `withRouting()` ke
  `->withBroadcasting(channels, ['middleware'=>['auth:sanctum']])`. **Kenapa bug:** framework
  mendaftarkan `/broadcasting/auth` dgn guard **`web`** (session cookie) saat channels lewat
  `withRouting(channels:)`; SPA pakai **Bearer token Sanctum** → auth channel privat PASTI gagal.
  **Pindah bukan tambah** (kalau dua-duanya, channels ter-`require` 2x + route ganda). 5 test
  `tests/Feature/Realtime/BroadcastAuthTest.php` (unauth→401; slot.read→200; tanpa izin→403;
  gate-officer terminal sendiri→200, terminal lain→403). **Jebakan test (didokumentasikan di
  file):** `Broadcast::channel()` mendaftar di driver DEFAULT saat boot (`null` di phpunit);
  meng-`config(['broadcasting.default'=>'pusher'])` sesudah boot → driver pusher **kosong channel**
  → semua 403. Fix: `require base_path('routes/channels.php')` ulang di helper `usePusherBroadcaster()`
  (idempoten). channels.php **tidak diubah** — callback RBAC-nya sudah benar.
  **Slice B (FE):** `laravel-echo`+`pusher-js`. `echo.ts` = singleton Echo (init setelah token ada,
  disconnect saat logout, **no-op bila `VITE_REVERB_APP_KEY` kosong** → degradasi mulus: app == polling
  biasa bila Reverb mati). `composables/useRealtime.ts` (`useSlotRealtime`/`useGateQueueRealtime`):
  subscribe reaktif `slot.{gateId}`/`gate.queue.{terminalId}`, event masuk → **invalidateQueries**
  (BUKAN tambal cache — payload broadcast subset; API tetap sumber kebenaran), `leave()` saat param
  ganti/unmount (cegah channel bocor). `.listen('.slot.availability.changed')` — **titik depan wajib**
  (broadcastAs, bukan kelas PHP). Wiring: `SlotAvailabilityPage` (`useSlotRealtime(gate)`),
  `GateDashboardPage` (`useGateQueueRealtime(auth.user.terminal_id)`), siklus connect/disconnect di
  `app.ts` (watch `auth.isAuthenticated`). +7 Vitest `tests/js/useRealtime.test.ts` (Echo di-mock;
  subscribe→invalidate, ganti gate→leave+rejoin, unmount→leave, Echo null→no-op). **Verifikasi live
  (server):** Reverb `reverb:start` bind port di Windows native; dispatch event→driver reverb: dead-port
  9999 → `BroadcastException` cURL 7, live-port 8080 → sukses (Reverb ingest). **BELUM diverifikasi
  end-to-end:** frame WebSocket sampai ke browser (butuh 2 browser + `BROADCAST_CONNECTION=reverb`).
- [x] **FE: nav/layout bersama (2026-07-08):** `components/AppNav.vue` (navbar: brand → `/`, link ber-gate **permission** — bukan nama role, cermin otorisasi server; `/reports` juga cek `company_id`; nama user + tombol Keluar) + `components/AppLayout.vue` (`AppNav` + `RouterView`) sebagai **parent route** semua halaman ber-auth di `router/index.ts`. `requiresAuth` cukup di parent (Vue Router menggabungkan meta parent→child) → halaman baru otomatis terlindungi tanpa mengulang meta. Dashboard disederhanakan (header+logout pindah ke navbar; kartu pintu-masuk dipertahankan); link "← Dashboard" di 6 halaman dihapus (redundan dgn navbar). 4 test `tests/js/AppNav.test.ts` (gating transporter, `/reports` tersembunyi utk planner tanpa company, logout→redirect, nama user) → **67 Vitest**. CATATAN test: stub `RouterLink: true` TIDAK merender slot — pakai `RouterLinkStub` dari `@vue/test-utils` bila perlu assert teks link.
- [x] **FE: halaman Laporan Perusahaan `/reports` (2026-07-08):** UI untuk `GET /me/reports/utilization` (slice BE di bawah). `api/slots.ts` `fetchMyUtilization` (unwrap `data`+`meta.summary`, bentuk sama dgn `fetchUtilization`); `composables/useMyUtilization.ts` (useQuery key `['my-utilization',gate,date]` — **sengaja terpisah** dari `['utilization']` planner: beda scope, tak boleh saling menimpa cache; read-only → tanpa mutation/invalidation); `pages/MyUtilizationPage.vue` (dropdown gate `useGates` + tanggal; state prompt/loading/error/empty; per window "Milik Anda: selesai/no-show/batal/aktif" + konteks gate "terisi X/kapasitas" dipisah visual supaya angka global tak terbaca sebagai milik company; ringkasan `meta.summary`); route `/reports`; link Dashboard "Laporan Perusahaan" gated `can('report.read') && company_id != null` (planner/admin punya `report.read` tapi tanpa company → link tak muncul, mereka pakai `/planner`). +6 Vitest (5 page states + 1 api unwrap) → **63 Vitest**; vue-tsc & build hijau.
- [x] **Utilisasi company-scoped transporter (2026-07-08):** `GET /api/v1/me/reports/utilization?gate=&date=` — menutup janji matriks RBAC §1 ("Laporan utilisasi → transporter: company sendiri") yang selama ini baru desain. `SlotRepository::utilization()` dapat param opsional `?int $companyId` — filter `where('company_id')` diterapkan ke **semua** subquery `withCount` supaya angka company lain tak pernah bocor; `capacity`/`booked_count` tetap global (konteks gate, sudah terbuka via availability). `MyUtilizationReportController` + `MyUtilizationReportRequest` (`can('report.read')`; 403 tanpa `company_id` — pola `/me/appointments`); reuse `SlotUtilizationResource`; `meta.company_id` di respons. Endpoint agregat planner/admin tak berubah. 4 test `tests/Feature/Reports/MyUtilizationReportTest.php` (scoping benar, planner tanpa company 403, driver tanpa `report.read` 403, gate wajib 422). UI-nya: entri FE `/reports` di atas.
- [x] **Hardening: Sanctum token TTL + prune (2026-07-07):** `sanctum.expiration` = env `SANCTUM_EXPIRATION` default **720 menit (12 jam ≈ 1 shift)** — sebelumnya `null` = token bocor valid **selamanya** (logout cuma mencabut satu token). Lewat TTL → 401 → SPA redirect login (handler sudah ada di `api/client.ts`). `sanctum:prune-expired --hours=24` dijadwalkan harian (`routes/console.php`; grace 24 jam supaya token mati masih bisa ditelusuri saat investigasi insiden, dan tabel `personal_access_tokens` tidak tumbuh tanpa batas). 4 test `tests/Feature/Hardening/TokenExpirationTest.php` (expired→401, dalam-TTL→200, prune hapus baris, schedule terdaftar).
- [x] **Admin CRUD master data (commit `0507d86`):** CRUD penuh `Terminal`/`Gate`/`TransportCompany`/`User`. BE: 12 Action (`Admin/`), 3 repo baru (`Terminal/Company/User` + extend `Gate`) ber-interface + bound di `AppServiceProvider`, 20 controller invokable (`Http/Controllers/Api/V1/Admin/`), 10 FormRequest (otorisasi `*.manage` + route-binding `instanceof`-safe utk PHPStan), 4 Resource. `EntityInUseException` (409 `entity_in_use`) guard hapus saat ada dependen (terminal←gate, gate←slot window, company←user/appointment, user←diri sendiri 422). `UserRepository`: filter role (Spatie `role()`), password hash-on-change, `fresh([...])` reload relasi setelah `syncRoles`. Permission baru `terminal.manage`/`gate.manage`/`company.manage` di `RolePermissionSeeder` (admin `→ *`). Route group `/api/v1/admin/*`. FE: `types/api.ts` (Admin* types), `api/admin.ts` (16 fn), `composables/useAdmin.ts` (`useTerminals`/`useAdminGates`/`useCompanies`/`useUsers`/`useAdminRefs`, invalidasi `['admin-*']`), `pages/AdminPage.vue` (4-tab), route `/admin`, kartu Dashboard "Master Data" gated `terminal.manage`. 34 test Admin (Pest) + verifikasi Vitest. CATATAN Pest: `$this->seed(...)` di closure `function(): void` (global `seed()` cuma di arrow-fn). Detail kode: `docs/CODE-WALKTHROUGH.md §V` (BE) + `docs/FRONTEND.md §4` (FE).
- [x] Paket wajib terpasang: Sanctum, Horizon, Reverb, Spatie Permission/ActivityLog/Data, Pest, Larastan. Config/migrasi sudah dipublish; `routes/api.php` + `channels.php` ter-wire (install:api).
- [x] Skema + model (BUSINESS-FLOW §4): 8 migrasi domain + kolom `terminal_id`/`company_id` di users. Model + relasi + casts + 5 Enum (`AppointmentStatus` memuat state machine). Factory untuk semua model.
- [x] RolePermissionSeeder + DemoSeeder jalan (`migrate:fresh --seed` hijau).
- [x] Tooling: `phpstan.neon` (lvl 8), `tests/Pest.php` (RefreshDatabase di Feature), composer scripts `test/analyse/fix/lint`, `AppServiceProvider::preventLazyLoading`. ~~phpunit pakai sqlite `:memory:`~~ **[usang: sejak D1 MySQL `tas_testing`, isolasi di-force + `tests/TestCase.php` — `SETUP-GUIDE §6`]**.
- [x] **Data layer:** `SlotRepositoryInterface`/`AppointmentRepositoryInterface` + impl Eloquent, bound di `AppServiceProvider`.
- [x] **BookAppointmentAction + test race condition:** `DB::transaction(attempts:3)` + `lockForUpdate` + cek penuh/tutup (409) + `containers.slot_window_id` + cek kepemilikan fleet. Event `AppointmentBooked` → listener invalidasi cache. 12 test (Action + endpoint).
- [x] **Endpoint:** `GET /api/v1/slots/availability` (Cache::flexible) + `POST /api/v1/appointments` (auth:sanctum + middleware `idempotency`). FormRequest+DTO masuk, Resource keluar.

- [x] **Auth Sanctum:** `POST /api/v1/login` (token + abilities = permission role), `POST /logout` (cabut token), `GET /me`. `UserResource`. 7 test.
- [x] **AppointmentPolicy + show:** `GET /api/v1/appointments/{id}` via middleware `can:view,appointment`. Policy: admin/planner semua, gate-officer per terminal, transporter per company, driver per `driver_id`. 7 test. (Auto-discovered, tak perlu daftar manual.)
- [x] **Reschedule & Cancel:** `POST /api/v1/appointments/{id}/reschedule` (optimistic `version`, lock 2 window, pindah kuota, `version++`, pindah container) + `POST .../cancel` (kembalikan kuota, NULL container). Policy `update`/`cancel`. Events `AppointmentRescheduled`/`AppointmentCancelled` + cache invalidate via interface `AffectsSlotAvailability`. Exceptions `OptimisticLockException`/`InvalidAppointmentStateException` (409). 9 test.
- [x] **Gate-in & Gate-out:** `POST .../gate-in` (CONFIRMED→ARRIVED→IN_PROGRESS, MVP satu aksi) + `POST .../gate-out` (IN_PROGRESS→COMPLETED). `GateInAction`/`GateOutAction` = `DB::transaction(attempts:3)` + row `lockForUpdate` + guard idempoten (`isGatedIn` / `COMPLETED` → no-op, tak ada transaksi ganda) + middleware `idempotency`. Repo `recordGateIn`/`recordGateOut` buat `gate_transactions` (unik `(appointment_id,type)`). Policy `process` (gate-officer per terminal, admin via before). Events `TruckGatedIn`/`TruckGatedOut` (interface `RecordsGateEvent`) → listener `ProcessGateEventOnTos` → `ProcessGateEventJob` (`ShouldBeUnique`+`WithoutOverlapping` per appointment, guard cek transaksi, push TOS = TODO realtime slice). Resource: `gate_in_at`/`gate_out_at`. 10 test.
- [x] **No-show sweep & Reminder:** `NoShowSweepJob` (`Schedule::job(...)->everyFiveMinutes()` di `routes/console.php`, `WithoutOverlapping('no-show-sweep')->dontRelease()`) → repo `dueForNoShow(now, grace)` (saring kasar DB `whereDate<=` + refine PHP window.end+grace, portabel sqlite/mysql) → per kandidat `MarkNoShowAction` (mirror cancel: `DB::transaction(attempts:3)`+lock+`canMarkNoShow`+`markNoShow`+kembalikan kuota+NULL container, event `AppointmentNoShow` impl `AffectsSlotAvailability`; balapan gate-in/cancel → catch `InvalidAppointmentStateException`). `AppointmentReminderJob` (`ShouldBeUnique` uniqueId=appointment id, tries/backoff/failed) kirim `AppointmentReminderNotification` (mail) ke sopir, cek status terkini (BOOKED/CONFIRMED saja → tahan reschedule/cancel). Listener `ScheduleAppointmentReminder` on `AppointmentBooked` dispatch reminder delayed H-(`reminder_lead_minutes`). Grace & lead di `config/tas.php`. 12 test.
- [x] **Realtime broadcast & TOS seam:** broadcast events `SlotAvailabilityChanged` (channel `slot.{gateId}`, payload datar sisa kuota) & `GateQueueUpdated` (channel `gate.queue.{terminalId}`), keduanya `ShouldBroadcast`. Listener `BroadcastSlotAvailability` (on `AffectsSlotAvailability`, group window by gate) & `BroadcastGateQueue` (on `RecordsGateEvent`, resolve terminal dari slotWindow.gate) — auto-discovered, sejajar listener cache/TOS yang sudah ada. Channel auth di `routes/channels.php` (`slot.{gateId}`→`can('slot.read')`; `gate.queue.{terminalId}`→admin/planner/driver, gate-officer per terminal). TOS seam: contract `GateEventGateway` + `LoggingGateEventGateway` (bound di AppServiceProvider) → `ProcessGateEventJob::handle()` ganti TODO jadi `$tos->push()` (guard idempoten tetap). `phpunit.xml`: `BROADCAST_CONNECTION=null`. 7 test.
- [x] **Endpoint pendukung:** `GET /api/v1/me/appointments/today` (driver, `TodayAppointmentsRequest::authorize`→`appointment.read.self`; repo `todayForDriver(driverId,date)` eager-load truck/driver/company/slotWindow.gate/containers; output `AppointmentResource::collection`) + `GET /api/v1/reports/utilization?gate=&date=` (planner/admin only via `UtilizationReportRequest`; repo `SlotRepository::utilization` `withCount` alias completed/no_show/cancelled/active per window; `SlotUtilizationResource` + `meta.summary` total via `->additional()`). 7 test. CATATAN: laporan utilisasi = agregat lintas-company (planner/admin); varian company-scoped transporter menyusul di `GET /me/reports/utilization` (lihat entri 2026-07-08 di atas).
- [x] **Slot-window management (planner):** `POST /api/v1/slots` (`OpenSlotWindowAction`: repo `create` window OPEN+booked_count 0, unik `(gate_id,date,start_time)` → `DuplicateSlotWindowException` 409; event `SlotWindowOpened`) + `POST /api/v1/slots/{slotWindow}/close` (`CloseSlotWindowAction`: `DB::transaction(attempts:3)`+`lockForUpdate`, status→CLOSED bukan delete, idempoten, event `SlotWindowClosed`). Keduanya event impl `AffectsSlotAvailability` → reuse listener cache-invalidate + broadcast. Auth via FormRequest `slot.manage`. DTO `OpenSlotWindowData` (Spatie Data). Validasi: date `after_or_equal:today`, time `H:i:s` + `after:start_time`, capacity 1..1000. 12 test (termasuk verifikasi window muncul/hilang di endpoint availability).

- [x] **Slice 9 — Planner kelola window (persona terakhir; PURE FE):** reuse endpoint yg sudah ada (tanpa BE baru): `GET /reports/utilization?gate=&date=` (semua window OPEN+CLOSED + counts + meta.summary), `POST /slots` (buka), `POST /slots/{id}/close`. FE: `api/slots.ts` tambah `fetchUtilization`/`openSlotWindow`/`closeSlotWindow`; `composables/usePlannerWindows` (`useUtilization` query `['utilization',gate,date]` + mutations open/close → invalidasi `['utilization']`+`['slots-availability']`); `pages/PlannerWindowsPage.vue` (gate dropdown+tanggal, form buka window [time→H:i:s], list window dgn status/terisi/no-show + tombol **Tutup** utk OPEN, ringkasan; map 409 `duplicate_slot_window`), route `/planner`, link Dashboard gated `slot.manage`. Types `SlotUtilization`/`UtilizationSummary`/`OpenWindowPayload`. +9 test Vitest. CATATAN test: form submit di jsdom → trigger `submit` pada `<form>`, bukan click tombol submit.

- [x] **Slice 8 — Dashboard gate-officer (persona baru):** BE `GET /api/v1/gate/queue?date=` (otorisasi `gate.process` + wajib `terminal_id`, else 403). `AppointmentRepository::queueForTerminal($terminalId,$date)`: status CONFIRMED/IN_PROGRESS, `whereHas('slotWindow', whereDate)` + `whereRelation('slotWindow.gate','terminal_id',…)` (dot-notation hindari nested-closure PHPStan), eager-load + gateIn/gateOut; **urut diserahkan ke FE** (hindari sortBy kolom relasi yg bikin larastan flip-flop nullsafe). `GateQueueController`+`GateQueueRequest`. 4 test. FE: `api/gate.ts` (fetchGateQueue + gateIn/gateOut kirim Idempotency-Key), `composables/useGateQueue` (query `['gate-queue']` + mutations useGateIn/useGateOut → invalidasi `['gate-queue']`), `pages/GateDashboardPage.vue` (list urut jam; tombol **Gate In** utk CONFIRMED, **Gate Out** utk IN_PROGRESS; map 409 invalid_state), route `/gate`, link Dashboard gated `gate.process`. +6 test Vitest.

- [x] **Slice 7 — Jadwal driver (persona baru):** BE additive: `SlotWindowResource` tambah `gate` (`whenLoaded('gate')` → reuse `GateResource`) supaya driver tahu gate tujuan; tak menambah query di endpoint lain (hanya `me/appointments/today` yang eager-load gate). FE: `fetchTodaySchedule()` + `useTodaySchedule` (query `['me-today']`), `pages/DriverSchedulePage.vue` (list urut jam, nama gate, move_type/kode/kontainer, badge status; state loading/error/empty), route `/today`, link Dashboard gated `appointment.read.self`. Type `SlotWindow.gate?`. +5 test (BE assertion gate.name; FE api today + page sort/empty/error). Endpoint `me/appointments/today` murni FE (sudah ada).

- [x] **Frontend slice 6 — reschedule:** `api/appointments.ts` tambah `rescheduleAppointment(id,slotWindowId,version)`; composable `useRescheduleAppointment` (mutation → invalidasi `['me-appointments']`+`['slots-availability']`). `components/RescheduleDialog.vue`: modal picker window target (reuse `useGates`+`useSlotAvailability`; default ke gate/tanggal window saat ini), pilih window (remaining>0) → submit kirim `slot_window_id`+`version`; map 409 `version_conflict`/`slot_unavailable`. `MyBookingsPage`: tombol "Pindah jadwal" (untuk BOOKED/CONFIRMED) buka dialog; sukses → tutup + list/availability auto-refresh. +5 test Vitest (dialog list/validasi/sukses/409 + page buka dialog). **Manajemen booking transporter lengkap** (list+cancel+reschedule).

- [x] **Frontend slice 5 — "Booking Saya" (list + cancel):** `api/appointments.ts` tambah `fetchMyAppointments(status?)` & `cancelAppointment(id,version)`; composable `useMyAppointments` (query `['me-appointments',status]`) + `useCancelAppointment` (mutation → invalidasi `['me-appointments']`+`['slots-availability']`). `pages/MyBookingsPage.vue`: filter status, list (kode/status/slot/truk/sopir/kontainer), tombol **Batalkan** hanya untuk BOOKED/CONFIRMED → konfirmasi 2-langkah → kirim `version` (optimistic lock); map 409 `version_conflict`/`invalid_state`. Route `/bookings` + link Dashboard (gated `appointment.write`). Types `Appointment`/`Container`. +8 test Vitest. CATATAN: **reschedule belum** (perlu picker window target) — slice berikutnya.

- [x] **Endpoint list booking transporter:** `GET /api/v1/me/appointments?status=` (otorisasi `appointment.read` + wajib `company_id`, else 403; filter status ber-`Rule::enum`). `AppointmentRepository::forCompany($companyId,?$status)` eager-load truck/driver/company/slotWindow.gate/containers, `orderByDesc('id')`. `MyAppointmentsController` + `MyAppointmentsRequest`, reuse `AppointmentResource` (sudah ada `version` untuk reschedule/cancel). 6 test. Unblock halaman "Booking Saya" (frontend berikutnya).

- [x] **Frontend slice 4 — form booking (jalur inti transporter):** `api/fleet.ts`+`api/appointments.ts`, `composables/useFleet.ts` (query `['me-fleet']`) + `useBookAppointment.ts` (mutation → `onSuccess` invalidasi `['slots-availability']`). `components/BookingForm.vue`: modal pilih truk/sopir (dari `GET /me/fleet`), move_type, container_no/iso_type/size; submit kirim Idempotency-Key (crypto.randomUUID) lewat `POST /appointments`; map error 409 `slot_unavailable`/`duplicate_booking` & fallback message; emit `booked`/`cancel`. `SlotAvailabilityPage`: tombol "Booking" per window tersedia (muncul bila `auth.can('appointment.write')`), buka modal, banner sukses tampilkan `booking_code`; mutation otomatis refresh sisa kuota. Types `Truck/Driver/Fleet/MoveType/BookAppointmentPayload/BookedAppointment`. +8 test Vitest (api fleet/appointments, BookingForm render/validasi/sukses/409, page tombol per-izin). CATATAN test: `setValue(number)` di `<select>` v-model.number bekerja; mock composable + `stubs:{BookingForm:true}` di page test.

- [x] **Frontend slice 3 — dropdown gate:** `api/gates.ts` (`fetchGates(terminal?)`) + `composables/useGates.ts` (`useQuery` key `['gates']`, staleTime 5 mnt) → `SlotAvailabilityPage` ganti input angka jadi `<select>` gate (placeholder "Pilih gate", opsi dari `GET /gates`). Type `Gate` di `types/api.ts`. +3 test Vitest (gates.api unwrap/filter + render opsi). Sisa: form booking (Task berikutnya).

- [x] **Read endpoints referensi (master data):** `GET /api/v1/gates` (opsional `?terminal=`, otorisasi `slot.read`, `GateResource`) + `GET /api/v1/me/fleet` (otorisasi `fleet.manage`, respons `{data:{trucks:[],drivers:[]}}` ber-scope `company_id` transporter; sopir = user company ber-role `driver`). Pola data layer konsisten: `GateRepositoryInterface`/`FleetRepositoryInterface` + impl, di-bind di `AppServiceProvider`; controller invokable + FormRequest (`ListGatesRequest`/`FleetRequest`); reuse `TruckResource`/`DriverResource`, baru `GateResource`. 8 test (`tests/Feature/Reference/`). Tujuan: unblock dropdown gate (ganti input angka) & form booking (pilih truk/sopir).

- [x] **Frontend slice 2 — Ketersediaan Slot (TanStack Query pertama):** `api/slots.ts` (`fetchAvailability(gate,date?)` unwrap `data`), `composables/useSlotAvailability.ts` (`useQuery` key `['slots-availability',gate,date]` reaktif, `enabled` saat gate>0), `pages/SlotAvailabilityPage.vue` (input gate angka + date default hari-ini; state prompt/loading/error/empty/list; kartu sisa-kuota + badge Tersedia/Penuh), route `/slots` (requiresAuth), link di Dashboard (muncul bila `auth.can('slot.read')`). Type `SlotWindow`/`SlotAvailabilityResponse` di `types/api.ts`. 6 test Vitest (api unwrap + page states; composable di-mock spt LoginPage). CATATAN: gate masih input angka — belum ada `GET /gates` untuk dropdown (lihat Langkah berikutnya).

- [x] **Frontend foundation + Auth/Login (slice 1):** SPA Vue 3 in-repo di `resources/js` (Vite + laravel-vite-plugin + Tailwind v4 yang sudah ada). Stack: Vue 3 (Composition + `<script setup>`) + TS + Pinia + Vue Router + TanStack Query + Axios. Build: `@vitejs/plugin-vue` di `vite.config.js` (input → `app.ts`, alias `@`→`resources/js`), `tsconfig.json` (extends `@vue/tsconfig`), `vitest.config.ts` (jsdom). Shell `resources/views/app.blade.php` + catch-all `routes/web.php` (`^(?!api).*$`). Kode: `api/client.ts` (axios `/api/v1` + Bearer interceptor + 401 handler, token di localStorage), `stores/auth.ts` (login/logout/fetchMe/restore/can/hasRole), `router/index.ts` (guard requiresAuth/guestOnly + restore sesi), `pages/LoginPage.vue` + `DashboardPage.vue`, `App.vue`, `app.ts`. 7 test Vitest (store + komponen login). CATATAN respons: `/login` flat (`{token,user}`), `/me` terbungkus `{data}` — store menangani keduanya.

- [x] **Hardening: rate limiting (slice keamanan):** named limiter di `AppServiceProvider::configureRateLimiters()` — `login` (anti brute-force, kunci `email|ip`), `api` (batas umum endpoint ber-auth, kunci user id/ip), `booking` (lebih ketat dari `api`, anti bot borong slot). Nilai di `config/tas.php` → `rate_limits` (env `TAS_RL_LOGIN`=5, `TAS_RL_API`=60, `TAS_RL_BOOKING`=10). Pasang di `routes/api.php`: `throttle:login` pada login, group ber-auth `throttle:api`, booking tambah `throttle:booking`. Menutup gap kontrak CLAUDE.md §Hardening (rate limit) yang sebelumnya KOSONG. 3 test (`tests/Feature/Hardening/`: login 429 + keyed-by-email, booking 429 per user). CACHE_STORE=array → limiter ter-reset per test (tak ada bleed antar-test).

## Senior review (2026-06-28) — temuan & keputusan
> Audit menyeluruh actions/repos/middleware/policy/migrasi/auth. Kesimpulan: foundation kuat (race handling, layering, idempotency benar). Temuan & status:
- **[FIXED] Tidak ada rate limiting** (melanggar kontrak) → slice di atas. Login brute-forceable & booking bisa di-borong bot — kini ber-throttle.
- **[DEFERRED — sengaja tidak diimplementasikan] Token abilities Sanctum tak ditegakkan.** Login mencetak token dgn abilities = SELURUH permission role, dan tak ada jalur token ber-scope sempit. Maka `abilities:` middleware tak pernah bisa menolak yang Policy/permission belum tolak → murni redundan + friksi (paksa semua test `actingAs` kirim `['*']`). Tegakkan NANTI saat aplikasi benar-benar menerbitkan token sempit (mis. token mobile read-only). Otorisasi saat ini tetap aman lewat Policy + FormRequest `can()`.
- **[FIXED] `version` optimistic lock tak konsisten:** `cancel` kini terima `version` opsional → bila dikirim, optimistic lock ditegakkan (`OptimisticLockException` 409 `version_conflict`); bila tidak, cancel tetap jalan (backward compatible). `CancelAppointmentRequest` + `CancelAppointmentAction::execute($appointment, ?int $expectedVersion)`. 4 test.
- **[FIXED] `dueForNoShow` muat semua kandidat ke memori:** kini dipindai `chunkById` (size dari `config('tas.no_show_chunk_size')` default 500) → hanya N baris di-hydrate per iterasi; hanya yang lewat grace ditahan. Test lintas-batas chunk (size=2, 5 due).
- **[FIXED] Idempotency lock TTL 10 dtk → 60 dtk** (`config('tas.idempotency.lock_seconds')`, ttl_hours juga) supaya lock tak kedaluwarsa di tengah request berat. Nilai header di-`hash('sha256')` jadi kunci cache (bounded lintas store, anti key-injection). 2 test (replay key panjang + contention 409).
- **[FIXED 2026-07-07 → ADR-0004] `$fillable` memuat `status`/`version`/`company_id`:** dulu aman hanya karena konvensi (Action set eksplisit) — kini kolom state/kuota dikeluarkan dari `$fillable` + `preventSilentlyDiscardingAttributes` aktif di non-prod. Lihat *Senior review ronde 2* di bawah.

- [x] **Backlog hardening (3 item dari senior review):** (1) optimistic `version` opsional di cancel, (2) `dueForNoShow` chunked scan (config `no_show_chunk_size`), (3) idempotency lock TTL 60s + key hashing (config `idempotency.lock_seconds`/`ttl_hours`). Semua via `config/tas.php` (tunable env). +8 test di `tests/Feature/{Appointments,Jobs,Hardening}`. Detail di *Senior review* (status [FIXED]).

## Senior review ronde 2 (2026-07-07) — temuan & keputusan
> Audit ulang seluruh Actions/Repositories/middleware/jobs/routes/seeder. Kesimpulan: fondasi tetap sehat; 2 temuan diperbaiki, 2 dicatat sebagai backlog sadar (bukan lupa).
- **[FIXED] Idempotency key tidak di-scope per endpoint (bug replay lintas operasi).**
  *Kenapa bug:* kunci cache lama = `user + sha256(header)` saja. User yang memakai nilai
  `Idempotency-Key` sama di dua endpoint berbeda (mis. booking lalu gate-in — realistis bila
  klien mem-buffer key atau salah reuse UUID) menerima **replay respons booking di gate-in**,
  dan operasi keduanya tidak pernah dieksekusi. Idempotency semestinya berlaku **per operasi**,
  bukan per nilai header global (bandingkan Stripe: key di-scope per endpoint).
  *Perbaikan:* `method|path` ikut di-hash → `idem:{user}:sha256(METHOD|path|key)`
  (`IdempotencyKey::cacheKey`). +1 test cross-endpoint di `tests/Feature/Hardening/IdempotencyTest.php`.
- **[FIXED → ADR-0004] Mass-assignment trap (temuan #4 ronde 1).** `status`/`version`/`company_id`
  keluar dari `Appointment::$fillable`; `booked_count`/`status` keluar dari `SlotWindow::$fillable`;
  `Model::preventSilentlyDiscardingAttributes(!prod)` diaktifkan supaya pelanggaran meledak di
  dev/test alih-alih dibuang diam-diam. `DemoSeeder` beralih ke `forceFill()` (bypass yang
  disengaja & terlihat). Kenapa-nya lengkap di `docs/adr/0004-guard-state-quota-columns.md`.
  +5 test `tests/Feature/Hardening/MassAssignmentGuardTest.php`.
- **[FIXED 2026-07-07 sesi lanjutan] Booking ke window yang sudah lewat tidak ditolak.**
  Dulu: window kemarin yang masih `OPEN` bisa di-book → `NoShowSweepJob` menandainya
  `NO_SHOW` ≤5 menit kemudian (absurd bagi transporter walau tak merusak data).
  Kini: `SlotWindow::hasEnded()` (basis `date+end_time` — sama dengan deadline no-show)
  ditolak `SlotUnavailableException::expired()` (409) di `BookAppointmentAction` &
  `RescheduleAppointmentAction`. **Keputusan produk:** window yang **sedang berjalan**
  (mulai tapi belum berakhir) tetap boleh di-book — truk masih bisa datang sebelum tutup.
  Ikutan: default `SlotWindowFactory` pindah ke `date=besok` ("valid by default") supaya
  test berjam-acak tidak flaky sore/malam; `dueForNoShow` refactor pakai `endsAt()`. +3 test.
- **[catatan, risiko ~nol] `booking_code` collision salah-lapor.** `booking_code` unik di DB;
  bila 8-char random tabrakan (≈1/2.8×10¹²), `UniqueConstraintViolationException`-nya akan
  tertangkap sebagai `DuplicateBookingException` (pesan "kontainer sudah dibooking" — menyesatkan).
  Tidak layak kode tambahan sekarang; cukup tahu saat debugging kasus aneh.
- **[dikembalikan] `docs/adr/README.md`** sempat ter-rename lokal jadi `README_ADR.md` (isi identik,
  belum di-commit) — dikembalikan: `README.md` adalah konvensi GitHub agar isi folder ter-render
  otomatis sebagai indeks.

## Senior review ronde 3 (2026-07-26) — temuan & keputusan
> Audit kode + **dokumen** menyeluruh, semua gerbang dijalankan sendiri (bukan percaya catatan):
> Pest 191/494 · Vitest 87 (20 file) · PHPStan lvl 8 **0 error** (207 file) · Pint bersih · vue-tsc bersih.
> Angka yang tercatat di README/ONBOARDING/SETUP-GUIDE/HANDOVER **cocok dengan hasil run** — tak ada yang dibesarkan.
- **[FIXED 2026-07-27 — P1] `driver_id` tidak divalidasi harus ber-role `driver`.**
  *Fix:* `InvalidDriverException` (422 `driver_invalid_role`) + guard ketiga di
  `BookAppointmentAction::assertFleetBelongsToCompany()` — `User::query()->whereKey(...)
  ->role('driver','api')->exists()`, **setelah** cek kepemilikan. Query scope (bukan
  `$driver->hasRole()`) karena `hasRole()` lazy-load relasi `roles` sementara
  `preventLazyLoading` aktif di non-prod — sekaligus memakai sumber kebenaran yang sama
  persis dengan `driversForCompany()`, jadi dropdown & penegakan tak bisa beda pendapat.
  +3 test (2 Action incl. urutan guard, 1 endpoint) + `UserFactory::driver()`. Detail di
  bawah ini adalah temuan aslinya, disimpan sebagai catatan kenapa fix-nya ada:
  *Bukti (bukan teori):* probe test booking dengan `driver_id` milik user ber-role **`transporter`**
  → **`201 Created`**. *Kelas bug identik dengan bug `INACTIVE` ronde lalu:* FE menyaring, API tidak.
  `FleetRepository::driversForCompany()` menyaring `role('driver','api')`, tapi `BookAppointmentRequest`
  (`driver_id` → `Rule::exists('users','id')->where('company_id')`) & `BookAppointmentAction::
  assertFleetBelongsToCompany()` hanya cek `exists` + `company_id`. *Akibat:* `AppointmentReminderJob`
  mengirim pengingat ke non-sopir; appointment itu **tak akan pernah muncul** di `/me/appointments/today`
  (butuh `appointment.read.self`) → sopir asli tak lihat jadwalnya. **Bukan** kebocoran lintas-company —
  scoping `company_id` tetap utuh. *Kenapa lolos test:* test booking yang ada memakai `User::factory()`
  **tanpa assignRole**, jadi gap ini memang tak pernah tersentuh. *Fix yang disarankan:* guard role di
  Action **setelah** cek kepemilikan — urutan yang sama seperti fix `INACTIVE` (kalau dibalik, beda pesan
  error membocorkan keberadaan user company lain).
- **[FIXED] Drift dokumen sisa dari audit `CLAUDE.md` (task #3 ditandai SELESAI padahal belum tuntas
  dipropagasi).** Lihat entri changelog `2026-07-26` di bawah: `README` masih mengklaim Redis/Horizon
  sebagai stack terpakai, dan §Perintah `CLAUDE.md` sendiri masih basi di 3 titik.
- **[FIXED 2026-07-27 — P4] Hygiene `.claude/` & dokumen yatim.** Temuan aslinya: (1)
  `.claude/settings.local.json` **tracked di git** (tak masuk `.gitignore`) & menumpuk 74 entri izin
  one-off termasuk perintah `sed` berisi hitungan test lama; (2) 3 dokumen yatim tak dirujuk peta
  README — `docs/VIBE-CODING.md`, `docs/SKILL.md`, `docs/security-reviewer.md`; dua terakhir
  **definisi skill/agent Claude Code** yang diparkir di `docs/` sehingga **tak teregistrasi apa pun**.
  *Fix:* `SKILL.md` → `.claude/skills/slice/SKILL.md` & `security-reviewer.md` →
  `.claude/agents/security-reviewer.md` (keduanya `git mv`, history terjaga); `settings.local.json`
  di-`git rm --cached` + masuk `.gitignore` **per-file, bukan seluruh `.claude/`** — `skills/` &
  `agents/` justru wajib di-commit supaya tersedia lintas perangkat (`tas-claude-code-guide.html §07`);
  `VIBE-CODING.md` dihapus (isinya cuma penunjuk ke guide, tak ada yang merujuknya — tetap terekam
  di git history). Keduanya juga **usang saat dipindah** dan ikut dikoreksi: `SKILL.md` menyuruh
  `composer test --parallel` (flag dobel — `composer test` sudah `pest --parallel`) dan tak menyebut
  gerbang FE; `security-reviewer` menyuruh memeriksa "setiap endpoint punya middleware scope"
  padahal ADR-0003 **sengaja menunda** token abilities → agent itu akan melaporkan false positive
  di **setiap** endpoint. Ditambah item baru: guard armada saat booking (ADR-0004, §W.4/§W.5).
- **[catatan] `DemoSeeder` tak punya truk `INACTIVE`** — keenam truk di-hardcode `'status' => 'ACTIVE'`.
  Aturan bisnis + jalur 422 `truck_inactive` yang baru ditambahkan **tak bisa didemokan dari data demo**,
  dan checklist anti-drift di bawah ("DemoSeeder menyentuh semua status & semua entitas") belum terpenuhi
  untuk enum `TruckStatus`. Sengaja tidak diubah sesi ini: menyentuh seeder menuntut `migrate:fresh --seed`
  yang **menghapus DB dev**.
- **[diverifikasi BENAR, tak ada aksi]** Lock order konsisten (appointment → slot window; id disortir di
  reschedule) → tak ada inversi deadlock. Idempotency scope `method|path` sesuai ADR. Cache explicit-key
  sesuai kontrak. **43 route** cocok penuh dgn tabel endpoint `SETUP-GUIDE §10d`; 10 route SPA cocok dgn
  `FRONTEND.md`; peta folder `ARCHITECTURE.md` cocok dgn `app/` aktual (termasuk sub-folder `Fleet/`).
  CI workflow benar memanggil skrip yang sama dengan lokal. **ERD §4 ternyata SUDAH** memuat
  `containers.slot_window_id` — TODO changelog `2026-06-27` sebenarnya sudah tuntas, hanya tak ditandai.

## Senior review ronde 4 (2026-08-02) — temuan & keputusan
> Audit **tasklist**: bukan "apa yang sudah dikerjakan benar?" (itu ronde 3), tapi **"apa yang
> dijanjikan kontrak tapi tak pernah punya kode?"**. Semua gerbang dijalankan sendiri sebelum
> menilai: Pest 194/501 · PHPStan lvl 8 **0 error** (208 file) · Pint bersih · Vitest 87 (20 file)
> · vue-tsc bersih — angka HANDOVER cocok dengan run nyata.
>
> **Blind spot yang memunculkan temuan ini:** ronde 3 memverifikasi arah *kode → dokumen*
> ("43 route cocok dengan tabel endpoint `SETUP-GUIDE §10d`"). Arah sebaliknya tak pernah dicek —
> janji di `BUSINESS-FLOW`/`PRD` yang **tak punya route sama sekali** tidak akan muncul di
> perbandingan itu. Lima dari tujuh temuan di bawah tak kelihatan dari sudut pandang ronde 3.
- **[FIXED 2026-08-02 — P1] `GateInAction` tidak memvalidasi jendela waktu sama sekali.**
  Hanya `status->canGateIn()`; tak ada cek tanggal/jam, dan `config/tas.php` tak punya kunci
  toleransi — padahal `BUSINESS-FLOW §2`/`§3.5` + `PRD §4` menjanjikannya. Detail fix ada di
  entri *Sudah selesai* teratas.
- **[FIXED 2026-08-02 — P1] Reminder hilang saat reschedule.** `§3.3` menjanjikan reminder ikut
  berpindah; kenyataannya `ScheduleAppointmentReminder` **hanya** mendengarkan `AppointmentBooked`,
  jadi appointment yang digeser tetap `CONFIRMED` dan reminder-nya meledak di jam window **lama**
  sementara window baru tak pernah dapat reminder. Detail fix ada di entri *Sudah selesai* teratas.
  Satu catatan yang perlu diingat dari perbaikannya: dugaan awal "perbaikan kecil, `ShouldBeUnique`
  sudah per-appointment" **salah** — justru `ShouldBeUnique` per-appointment itulah yang akan
  membuang reminder baru diam-diam. Asumsi itu ketahuan karena diuji, bukan karena dibaca ulang.
- **[FIXED 2026-08-02 — P2] `audit.read` diberikan, endpoint audit tidak ada.** Permission sudah
  ada di seeder & matriks §1 menjanjikan barisnya, tapi rute audit **nol** — log direkam, tak
  pernah bisa dibaca. Ditutup lewat `GET /appointments/{id}/audit` dengan otorisasi dua lapis;
  detail di entri *Sudah selesai* teratas. Yang layak diingat: temuan ini baru bisa dikerjakan
  **setelah** test Activity Log ada, jadi urutannya (test dulu, endpoint kemudian) memang benar —
  endpoint dibangun di atas jaminan yang sudah teruji, bukan di atas asumsi.
- **[FIXED 2026-08-02 — P2] Activity Log tak punya satu pun test.** Gerbang yang bolong: hapus
  trait `LogsActivity` dan semua gerbang tetap hijau. Ditutup dengan 7 test + **verifikasi mutasi**
  (cabut trait → 7 failed). Detail di entri *Sudah selesai* teratas. Satu pelajaran yang layak
  dibawa: langkah mutasi menemukan bahwa salah satu test baru itu sendiri **tidak menjaga apa pun**
  (test negatif yang lolos walau logging mati) — menambah test tanpa membuktikannya menggigit
  hanya memindahkan rasa aman, bukan menciptakannya.
- **[FIXED 2026-08-13 — P2] Gate Officer sekarang bisa menandai no-show manual.**
  `POST /api/v1/appointments/{id}/no-show`, reuse `MarkNoShowAction` + ability `process`.
  Lihat *Sudah selesai* & `CODE-WALKTHROUGH §N.6`.
- **[FIXED 2026-08-13 — P3] `dwell_time` diekspos sebagai `AppointmentResource.dwell_minutes`.**
  Dihitung dari `gate_transactions.processed_at` (IN→OUT) yang sudah ada sejak slice gate-in/out;
  tidak butuh migrasi baru. Lihat *Sudah selesai* & `CODE-WALKTHROUGH §N.5`.
- **[FIXED 2026-08-14 — P3] QR disebut berulang, nol implementasi → kini token ter-sign.**
  User pilih opsi lebih besar (token ter-sign + endpoint verifikasi, bukan cuma representasi
  visual `booking_code`). `AppointmentQrTokenService` (HMAC-SHA256) + `GET /appointments/qr/{token}`
  + `GET /appointments/qr/{token}/image` (PNG on-demand, nol storage — dites eksplisit). Lihat
  *Sudah selesai* & `CODE-WALKTHROUGH §AA`.
- **[catatan] Vitest berpotensi flaky di mesin terbebani.** Satu run melaporkan `9 file / 37 test
  + 11 errors`; dua run bersih berikutnya `20/20` & `87/87` exit 0 — kontensi resource, bukan
  kegagalan assertion. Tapi `environment 125 dtk` itu tipis: kalau runner CI dikecilkan, ini bisa
  jadi flake beneran.
- **[masih terbuka dari ronde 3]** `DemoSeeder` tak punya truk `INACTIVE` (keenam truk
  `'status' => 'ACTIVE'`) — jalur 422 `truck_inactive` tak bisa didemokan dari data demo.

## Temuan 2026-09-12 — test race-condition tidak menjaga lock (P1, **FIXED hari yang sama**)

> **[FIXED 2026-09-12 lewat slice D1]** Ditutup `tests/Concurrency/ConcurrentBookingTest.php`
> (2 test: 4 proses PHP sungguhan + assertion deterministik `for update`). Temuan aslinya
> dibiarkan utuh di bawah — ia menjelaskan KENAPA bentuk penutupannya seperti itu, dan slice D1
> menemukan satu hal lagi yang tak terduga: test konkurensi realistis **sendirian tetap hijau**
> saat lock dicabut. Lihat entri D1 di §Status poin (4).

- **[FIXED — P1] `it('never over-books the last slot')` tetap PASS walau `->lockForUpdate()`
  dihapus total.** Dibuktikan lewat **mutasi**, bukan dugaan: baris `->lockForUpdate()` dicabut dari
  `SlotRepository::lockForUpdate()` (`app/Repositories/SlotRepository.php:43`), test dijalankan →
  **1 passed (2 assertions)**. Kode langsung dikembalikan (`git checkout`), working tree bersih.
  - **Sebab struktural:** test di `tests/Feature/Booking/BookAppointmentActionTest.php:140` memanggil
    `BookAppointmentAction` **dua kali berurutan dalam satu proses**. Eksekusi sekuensial **tak akan
    pernah** bisa membuktikan sebuah lock, berapa pun mesin databasenya. Yang dijaga test itu adalah
    aturan bisnis `bookedCount >= capacity` → tolak. Aturan itu benar dan tetap berharga; ia hanya
    bukan bukti locking.
  - **Dampak:** perilaku yang oleh `CLAUDE.md` disebut **"INTI proyek ini"** — dua transporter
    menekan Booking pada detik yang sama untuk slot terakhir — **belum pernah diuji sekali pun**.
    260 test hijau selama ini memberi rasa aman yang tak ditopang bukti pada titik justru paling
    penting.
  - **Beda dari 4 temuan sekelas sebelumnya** (`TruckStatus::INACTIVE`, `driver_invalid_role`,
    toleransi jendela gate-in, badge "Berakhir"): di sana **kodenya** yang bolong sementara dokumen
    benar. Di sini **kodenya benar** — `lockForUpdate()` sudah tepat sejak awal — yang bolong
    **gerbangnya**. Kelas barunya: *"kode benar, tapi tak ada yang menjaganya tetap benar"*. Hapus
    lock-nya besok karena salah paham, dan **tak ada** yang memberi tahu.
  - **Kenapa tak bisa ditutup sekarang juga:** SQLite tak menerbitkan `SELECT … FOR UPDATE` (ia
    menyerialkan tulisan di level file/DB), jadi test konkurensi yang jujur **menuntut MySQL lebih
    dulu**. Inilah alasan migrasi infra dan kebenaran uji digabung, dan alasan MySQL naik jadi
    **slice #1** di *Langkah berikutnya* — bukan tahap 6 seperti draf plan pertama.
  - **Ditutup di:** slice #1 [ADR-0007](docs/adr/0007-docker-redis-mysql-migration.md). Ditulis
    **merah dulu** (loop TDD kontrak): test konkurensi sungguhan (proses paralel / request bersamaan)
    harus **gagal** saat lock dicabut, baru ia layak disebut penjaga.
  - **Dokumen yang ikut dikoreksi hari ini** karena klaimnya tak benar: `ARCHITECTURE §7`
    ("race-handling **teruji**" → dikoreksi), `CODE-WALKTHROUGH §J.1` (+peringatan), `CLAUDE.md`
    §Hardening/Race (+peringatan & larangan mengklaim "teruji" sampai slice #1 selesai).

> **Pelajaran metodologis (transferable, di luar proyek ini).** Kebiasaan **membuktikan gerbang itu
> menggigit lewat mutasi** — yang sudah dipakai proyek ini saat menguji audit trail
> (`CODE-WALKTHROUGH §Y`, di sana ia menemukan lubang di test-nya sendiri) — menemukan lubang di
> test terpenting proyek ini dalam waktu **di bawah satu menit**. Pertanyaannya bukan "apakah test
> hijau", tapi **"apakah test ini merah kalau fitur yang dijaganya saya rusak?"**. Terapkan ke test
> mana pun yang menjaga sesuatu yang mahal: konkurensi, otorisasi, audit, idempotency.

## Temuan D2′ 2026-09-12 — endpoint API balas 500 (bukan 401) tanpa header JSON (P3, FIXED 2026-09-15)

- **[FIXED 2026-09-15 — B4]** Analisis ulang menemukan cakupannya lebih lebar dari catatan di bawah:
  tanpa header, **422 jadi redirect 302** ke `/`, **404 jadi halaman HTML**, dan `/broadcasting/auth`
  juga 500. Perbaikan butuh **dua** baris di `bootstrap/app.php`, bukan satu:
  `shouldRenderJsonWhen(api/*, broadcasting/*)` (untuk 422/404) **dan**
  `redirectGuestsTo(fn () => null)` — `shouldRenderJsonWhen` saja **tidak** menutup 401, karena
  middleware `Authenticate` mengevaluasi `route('login')` bawaan framework *sebelum* exception
  dilempar (terbukti: setelah baris pertama saja, 2 test 401 tetap merah). Dijaga
  `tests/Feature/Hardening/ApiJsonErrorsTest.php`: 4 test penjaga (mutasi membuang
  `shouldRenderJsonWhen` → 4 merah). Test ke-5 "SPA tetap HTML" **hanya smoke test catch-all, bukan
  penjaga B4**: mutasi callback jadi selalu `true` tak membuatnya merah, karena `shouldRenderJsonWhen`
  hanya memengaruhi rendering *exception* dan halaman SPA 200 tak melempar apa pun (dicek 2026-09-16).
  Catatan asli dipertahankan di bawah.
- **[asal — P3] `Route [login] not defined` → 500.** Request **tak terautentikasi** ke endpoint
  ber-`auth:sanctum` **tanpa** header `Accept: application/json` membuat Laravel mencoba
  me-*redirect* ke route bernama `login`, yang **tidak ada** di app API-only ini
  (`routes/api.php:60` mendaftarkan `Route::post('login', …)` **tanpa** `->name('login')`).
  Hasilnya `RouteNotFoundException` → **500**, bukan **401**.
  - **Bukan efek containerisasi** — murni pre-existing, cuma ketahuan saat verifikasi D2′ karena
    saya menembak endpoint pakai `curl` polos. Dengan `Accept: application/json` responsnya benar:
    `{"message":"Unauthenticated."}`.
  - **Dampak terbatas tapi nyata:** SPA selalu mengirim `Accept: application/json` lewat Axios,
    jadi pengguna sungguhan tak menyentuhnya. Yang kena: siapa pun yang membuka URL API langsung di
    browser, dan **log produksi** — 401 yang wajar tercatat sebagai 500 yang terlihat seperti
    server rusak.
  - **Sengaja TIDAK diperbaiki di slice ini** (kontrak `CLAUDE.md`: lapor, jangan diam-diam
    perbaiki di luar scope). Perbaikan sebaris kalau nanti disetujui: beri nama route login, atau
    paksa `expectsJson()` untuk seluruh grup API di `bootstrap/app.php`.

## Temuan D6′ 2026-09-14 — jalur container ternyata tak semuanya memakai container

Ditemukan saat **verifikasi live** D6′ dan saat menjalankan *Mulai cepat* dari clone kosong, bukan saat
membaca kode. **Semua FIXED 2026-09-14** (detail & bukti merah→hijau di entri §Status `2026-09-14`):

- **[FIXED `d186c4a`] Test di container menembak DB dev `tas`** + `force="true"` tak pernah berefek
  (`env()` membaca `$_SERVER`). Kelas bug ronde 5 lagi: **tak ada gerbang** — suite tetap hijau.
- **[FIXED `38258e7`] App web di `:8000` membaca SQLite/`.env`** — `artisan serve` membuang env
  compose. Klaim D2′/D3′ "app memakai MySQL/Redis" hanya benar untuk CLI.
- **[FIXED `234db36`] Scheduler tak pernah dijalankan siapa pun** + **realtime putus di jalur
  container** (`REVERB_HOST=localhost`).
- **[FIXED `98bf375`] App di container lambat (median 3,4 dtk/request, lonjakan 8 dtk).** Penyebab
  terbukti **bukan** kompilasi: `opcache.enable_cli=1` sendirian tak membantu (4,0 dtk) walau OPcache
  aktif. Eksperimen `validate_timestamps=0` → 0,2 dtk membuktikan biayanya `stat()` ~925 berkas per
  request lewat bind mount NTFS — 816 dari `vendor/`. Fix: `vendor/` di named volume + OPcache CLI →
  **0,12 dtk**; suite container 68 → ±30 dtk. Tabel angka: `SETUP-GUIDE §17a`.
- **[FIXED `e3d3746`] Clone baru: realtime mati diam-diam.** `.env.example` mengosongkan kunci Reverb →
  setiap job broadcast gagal di Horizon (`Pusher error: Not found`) sementara booking tetap 201.
  Ditemukan dengan **menjalankan** *Mulai cepat* dari clone kosong di stack terisolasi, bukan membacanya.
- **[FIXED D5′] Klaim dokumen yang salah:** `SETUP-GUIDE §14c` "repo publik, cek CI via curl tanpa token"
  (repo privat — API 404); contoh curl booking `driver_id: 6` (sopir company lain → 422); `README`
  *Mulai cepat* rusak untuk clone baru; di `SETUP-GUIDE` versi lama: subbagian phpunit sqlite `:memory:`,
  "CI tanpa service DB" (§14), dan "`force` menutup isolasi" (§18) — kini ditulis ulang.
- **[CATATAN] Metode yang menemukan semuanya:** memeriksa **di mana data benar-benar mendarat**
  (token login di MySQL atau SQLite? schema test bernama apa?), bukan "apakah responsnya 200" atau
  "apakah test hijau". Kedua pertanyaan terakhir itulah yang dijawab D2′/D3′ — dan keduanya lolos
  sementara sistemnya salah.

## Sedang dikerjakan
- (kosong) — **sesi 2026-09-16 selesai** (CI merah diperbaiki, B8, B7, `settings.local.json` dirapikan,
  lalu audit menyeluruh + 10 perbaikan), semua di-commit & di-push. Gerbang: **285 Pest / 761 assert** di
  container, native 282 + 3 skip, Vitest 111, e2e 5/5, PHPStan lvl 8, Pint, `composer audit` & `npm audit`
  bersih, **CI hijau** (lihat MULAI SESI butir 2).

### ▶️ MULAI SESI BERIKUTNYA DARI SINI

**1. Nyalakan stack** (container juga hidup lagi sendiri setelah reboot):
```bash
docker compose up -d --wait   # mysql redis app horizon scheduler reverb
npm run dev                   # bila menyentuh frontend — buka http://localhost:8000
```
DB dev kosong/basi → `docker compose exec app php artisan migrate:fresh --seed`. `composer.lock`
berubah sejak sesi terakhir → `docker compose run --rm --no-deps app composer install`. Semua
"setelah mengubah X": `SETUP-GUIDE §3`. **Jangan** menyalakan `composer dev` / `artisan serve` /
`reverb:start` native bersamaan stack.

**2. CI `98b14b3` — hijau, dicek agen sendiri via `gh` 2026-10-30** (kedua job, setelah bump keamanan
stack; satu anotasi informatif migrasi `ubuntu-latest` → Ubuntu 26, lihat entri Status 2026-10-30). Sejak hari itu `gh`
terpasang & login (akun `caesarovera`, scope `repo`) — **setelah push, pantau sendiri**, jangan minta
user membuka tab Actions: `gh run list --branch main --limit 3`, `gh run watch <id> --exit-status`,
`gh run view <id> --log-failed`. Shell yang dibuka sebelum instalasi belum mengenal PATH-nya → pakai
`"C:\Program Files\GitHub CLI\gh.exe"` atau buka terminal baru. (Riwayat sebelum `gh`: D1 `4ea097b`,
D2′ `3e1f0c2`, D3′ `8319cf6`, `4f5d6ad` — dikonfirmasi user lewat tab Actions.)

**3. Pekerjaan tersisa:** tak ada slice aktif. 2026-09-15 menutup N1/B4/N2/B5; 2026-09-16 menutup B8 &
B7. Sisa Backlog (B1 token abilities, B2 perluasan e2e, B3 TOS sungguhan, B6 image produksi) semuanya
**menunggu keputusan user** — tak ada kandidat yang diusulkan agen.

**4. Catatan mesin:**
- Berkas sisa `php.ini.bak-pre-redis-20260912` di folder PHP laragon aman dihapus.
- **`.claude/settings.local.json` sengaja dipersempit (2026-09-16, 105 → 39 aturan).** `git push`,
  `docker compose stop/down/restart`, `php artisan migrate:fresh`/`tinker`, `npm install`, `git reset` dll.
  kini **ditanyakan dulu** (atau diputuskan classifier di auto mode) — itu disengaja, bukan rusak; jangan
  sarankan "always allow" dengan wildcard lebar. Cadangan versi lama:
  `~/.claude/truck-appointment-system.settings.local.json.bak-2026-09-16`.
- Alarm job gagal: di dev `TAS_ALERT_MAIL` kosong → cari `CRITICAL` "Queue job gagal permanen" di
  log harian `storage/logs/laravel-YYYY-MM-DD.log` (sejak 2026-09-16; `laravel.log` lama ±30 MB berisi
  sisa pra-audit, aman dihapus).


## Langkah berikutnya (urut)
**Fitur MVP lengkap** (4 persona UI + admin CRUD + armada truk + riwayat gate + QR + realtime) **dan
migrasi Docker (ADR-0007) tuntas.** Tak ada slice aktif — semua sisa di *Backlog* menunggu keputusan user.

### ✅ ADR-0007 — selesai 2026-09-14 (arsip ringkas; detail di §Status & ADR)

| Slice | Isi | Commit |
|---|---|---|
| D1 | MySQL + test konkurensi sungguhan (+ CI service `mysql`) | `4ea097b` |
| D2′ | app di container (dimajukan mendahului Redis) | `3e1f0c2` |
| D3′ + D4′ | Redis + Horizon + gerbang `ShouldBeUnique` berbasis perilaku (+ CI service `redis`) | `8319cf6` |
| D6′ | scheduler + Reverb di container; fix isolasi test & `serve --no-reload` | `d186c4a` · `38258e7` · `234db36` |
| — | kecepatan (`vendor/` volume + OPcache CLI); kunci Reverb dev di `.env.example` | `98bf375` · `e3d3746` |
| D5′ | dokumen Docker-first; jurnal & panduan pra-Docker dihapus (utuh di tag `pre-docker`) | commit dokumen sesi 2026-09-14 |

Prinsip sepanjang migrasi: **kebenaran paling mahal dibayar paling dulu, kosmetik terakhir** — dan
setiap klaim "terverifikasi" diuji ulang dengan pertanyaan *data ini benar-benar mendarat di mana?*
Tiga klaim D2′/D3′ gugur karena pertanyaan itu (§Temuan D6′).

### Backlog (semua menunggu keputusan user)

**B1. Token abilities sempit** (ADR-0003) — tegakkan saat ada token ber-scope sempit. Belum ada
   konsumen nyata; mengerjakannya sekarang = menambah permukaan sebelum ada yang memakainya.
   **Alasan tambahan (2026-09-15):** login menyalin *semua permission* ke token. Menegakkan abilities
   dalam bentuk itu justru **memburuk** — permission yang dicabut admin tetap hidup di token s/d 12 jam,
   padahal kini Spatie mengecek langsung tiap request. Kalau dikerjakan: scope kasar, bukan salinan.
**B2. Perluasan e2e** — belum ada spec untuk admin, utilisasi planner, no-show, QR. E2E juga masih
   lokal-only & jalur native (belum di CI). Keputusan biaya, bukan kelupaan (`SETUP-GUIDE §15`).
**B3. Swap gateway TOS** (`LoggingGateEventGateway` → integrasi TOS sungguhan) — terikat spek API
   eksternal yang belum ada di tangan. **Catatan desain (2026-09-15, dibaca):** gate-in/out yang no-op
   tak memicu event ulang (aman hari ini), tapi tak ada penanda "sudah di-push" — bila TOS timeout
   setelah sebenarnya menerima, retry `ProcessGateEventJob` mengirim dua kali. Integrasi nyata butuh
   kunci idempotensi ke TOS (mis. `booking_code`+`type`) atau kolom penanda push.
**~~B4. Bug 500-vs-401~~ — SELESAI 2026-09-15 (`a496193`)**, cakupan ternyata lebih lebar; §Temuan D2′.
**~~B5. `Cache::tags`~~ — DITUTUP 2026-09-15, tidak dikerjakan.** Satu keluarga kunci, invalidasi
   eksplisit sudah lengkap; alasan & pemicu tinjau ulang di `CLAUDE.md §Cache`.
**B6. Image produksi** — Dockerfile multi-stage terpisah: kode + `vendor --no-dev` di image,
   nginx+php-fpm (atau FrankenPHP), `validate_timestamps=0`, `config/route/event/view:cache`, kunci
   Reverb acak dari secret. Setup dev **tidak** boleh dibawa ke produksi (`SETUP-GUIDE §17f`).
   Baru relevan saat ada target deploy. Prasyarat B7 (alarm job gagal) sudah selesai 2026-09-16; di
   produksi isi `TAS_ALERT_MAIL` + SMTP sungguhan. **Temuan audit 2026-09-16 yang sengaja ditunda ke
   sini:** ~~(a) selisih waktu login~~ ~~(b) password `min:8`~~ ~~(c) CORS `*`~~ — **SELESAI 2026-10-07**
   (entri Status teratas); tersisa (d) header keamanan (CSP, HSTS) dan `config/reverb.php`
   `allowed_origins ['*']`.
**~~B7~~ — SELESAI 2026-09-16.** Keadaan sebelum [dijalankan]: job gagal permanen hanya meninggalkan
   1 baris `ERROR` (tak bisa dibedakan dari percobaan ulang) + baris `failed_jobs` + `FAIL` di konsol
   Horizon. Dipilih **satu listener global `JobFailed`** (bukan isi `failed()` per job, karena kegagalan
   nyata pertama proyek ini adalah job broadcast milik framework): log CRITICAL selalu + email ke
   `TAS_ALERT_MAIL` bila diisi, dibatasi per jenis job; listener & notifikasi sengaja tak di-queue dan
   pengiriman dibungkus try/catch (listener jalan di `finally` `Job::fail()`). 4 test + 4 mutasi menggigit.
   Live: Reverb dimatikan → broadcast gagal di Horizon → CRITICAL dengan nama event & UUID. Temuan
   sampingan: `horizon:terminate` dari container `app` mencetak "No processes to terminate" (master di
   container lain) tapi **tetap** memuat ulang worker lewat sinyal `illuminate:queue:restart` di Redis —
   dicatat di SETUP-GUIDE §3. Email live tak diuji (`TAS_ALERT_MAIL` kosong di dev); jalurnya dijaga test.
   Catatan asal:
**B7 (asal). Alerting job gagal permanen** (temuan N3, 2026-09-15, dibaca) — `failed()` di
   `ProcessGateEventJob`, `NoShowSweepJob`, `AppointmentReminderJob` kosong ("di-wire saat observability
   slice" — slice itu tak pernah dijadwalkan). Kegagalan tetap tercatat di `failed_jobs`/Horizon, tapi
   tak ada yang diberi tahu. Belum mendesak tanpa produksi.
**~~B8~~ — SELESAI 2026-09-16.** Dipilih test **perilaku**, bukan `instanceof`: cek interface tak
   menangkap Action yang lupa memicu event. `tests/Feature/Slots/SlotAvailabilityFreshnessTest.php`
   (4 test: booking/cancel/no-show/reschedule lintas 2 gate) memanggil Action sungguhan, lalu memeriksa
   sisa kuota yang dibaca **lewat cache** dan payload broadcast. Tujuh mutasi, semuanya menggigit tepat:
   lepas interface ×4 event → test event itu merah; hapus `AppointmentCancelled::dispatch` dari Action →
   merah; `forgetAvailability` no-op → 4 merah (assertion cache); listener broadcast no-op → 4 merah
   (assertion broadcast). Temuan sampingan: `SlotAvailabilityBroadcastTest` memicu event **manual**,
   jadi tak pernah membuktikan Action memicu event — kini tertutup oleh test ini. Catatan asal:
**B8 (asal). [kecil] Gerbang invalidasi cache/broadcast per event** (2026-09-15, **dijalankan**) — mutasi:
   lepas `implements AffectsSlotAvailability` dari `AppointmentCancelled` → **273 test tetap hijau**.
   Yang terjaga hanya: listener itu sendiri (via test buka/tutup window) dan event booking (tak langsung,
   via `SlotAvailabilityBroadcastTest`). Cancel/reschedule/no-show tak punya gerbang → sisa kuota basi
   (≤30 dtk, batas `Cache::flexible`) dan tanpa broadcast realtime, tanpa test merah. Usul: satu test
   ber-dataset yang memastikan keempat event tsb `instanceof AffectsSlotAvailability`, atau test perilaku
   (prime cache → cancel → availability segar).

### ✅ Riwayat item yang sudah selesai (arsip, jangan dikerjakan ulang)

1. **[SELESAI 2026-08-14] QR (P3, ronde 4) — satu-satunya sisa janji kontrak tanpa kode.**
   `dwell_time` **[FIXED 2026-08-13]**, no-show manual **[FIXED 2026-08-13]**. QR: user
   pilih opsi yang lebih besar — token ter-sign + endpoint verifikasi baru (bukan cuma
   representasi visual `booking_code`). Ditutup sekalian dgn endpoint gambar on-demand
   (opsi B) supaya tetap nol-storage. Detail: *Status* teratas & `CODE-WALKTHROUGH §AA`.
   **Semua 7 temuan ronde 4 kini FIXED — tak ada lagi janji kontrak tanpa kode yang diketahui.**
2. **[SELESAI 2026-08-13] Verifikasi realtime end-to-end di browser.** `BROADCAST_CONNECTION=reverb`, `composer dev` + `php artisan reverb:start`, booking di satu browser (`dispatcher@majulog.test`) → sisa kuota berubah sendiri **tanpa refresh** di browser lain (`dispatcher@sinarkargo.test`) — **dikonfirmasi manual oleh user**. Verifikasi ini sekaligus menemukan 2 bug nyata (dicatat terpisah di *Sudah selesai*): (a) `DemoSeeder` pakai `Carbon::today()` saat di-seed → data demo jadi basi kalau DB tak di-`migrate:fresh --seed` ulang belakangan (bukan bug kode, tapi jebakan operasional — lihat §Jebakan); (b) badge "Tersedia" & pesan error tak bedakan window yang sudah berakhir. `BROADCAST_CONNECTION` dev **dikembalikan ke `log`** (default `CLAUDE.md §Stack`) setelah rangkaian testing manual sesi ini selesai — dipakai `reverb` sementara untuk beberapa verifikasi (realtime kuota, badge "Berakhir", role editor, dashboard fix). Swap ke TOS riil (`LoggingGateEventGateway`) masih opsional, belum dikerjakan.
3. **[SELESAI 2026-08-14, lokal-only] Polish UI — konfirmasi/loading + e2e.** Loading skeleton
   **DONE** sejak sebelumnya; konfirmasi eksplisit + spinner di semua tombol aksi lintas role
   **DONE** (`ConfirmButton`+`Spinner`, lihat *Status*). E2E: prasyarat `ADR-0005` beres,
   diperluas dari 1 happy-path jadi **5 spec/5 test** (booking-happy-path, booking-errors,
   gate-in-out, reschedule-cancel ×2) — semua lulus konsisten. Detail: *Status* teratas,
   `docs/SETUP-GUIDE.md §15`. **Sisa yang belum dikerjakan (sengaja, bukan lupa):** e2e **tidak**
   masuk CI (biaya browser binary ~140MB + waktu run belum sepadan sebelum benar-benar
   dibutuhkan), dan belum ada spec untuk admin/planner-utilization/no-show/QR. Wiring CI atau
   perluasan cakupan lagi = sesi terpisah kalau dibutuhkan.
4. **[SELESAI 2026-07-25]** Audit klaim `CLAUDE.md` vs kenyataan tuntas: baris Docker Compose, baris CI, baris `Queue/Cache/Session`, dan aturan `Cache::tags` di §Hardening — semuanya kini selaras dengan `.env` & kode. **Sisa pekerjaan nyata (bukan dokumen):** benar-benar pindah ke **Redis + Horizon + MySQL** untuk paritas produksi (butuh `docker-compose.yml`) — sesi tersendiri. Saat itu dikerjakan, dua hal ikut berubah: cache boleh direfaktor dari explicit-key `Cache::forget` ke `Cache::tags`, dan §Stack CLAUDE.md naik status dari **target** jadi **keadaan**.
5. **[DIPUTUSKAN 2026-07-27 → ADR-0006] CRUD sopir: TIDAK dibuat di MVP — sopir dikelola admin.** Pertanyaan produk yang menggantung di item ini ("transporter boleh bikin akun user sendiri?") kini dijawab: **tidak**. Sopir = `User` ber-role `driver`, jadi self-service berarti transporter menerbitkan **akun login** — permukaan keamanan baru (undangan/password sementara, dan role/`company_id` wajib dipaksa server) yang tak sepadan dengan nilainya, sementara CRUD truk sudah mendemonstrasikan pola company-scoped CRUD-nya secara lengkap. Jalur resmi: Admin User CRUD (§V) yang sudah ber-guard. Transporter tetap **melihat** sopirnya via `GET /me/fleet` (read-only, sudah ada). `PRD §3` sudah dikoreksi supaya kontrak tak lagi menjanjikan yang tak dibangun. Pemicu tinjau ulang ada di `docs/adr/0006-driver-management-admin-only.md`.
6. **[DIPINDAH 2026-09-12 → B1]** Backlog hardening token abilities sempit (ADR-0003). Kini tercatat
   di *Backlog* di atas supaya tak tercampur arsip.

> **Catatan arsip:** item 4 di atas menyisakan "pindah ke Redis + Horizon + MySQL" sebagai pekerjaan
> nyata. Sisa itu **sudah dituntaskan** lewat ADR-0007 (tabel di atas).

## Changelog kontrak / dokumen / seeder
> Catat tiap perubahan yang menyentuh CLAUDE.md, docs/*, atau seeder.
> Format: `tanggal: APA yang berubah → file mana yang ikut diupdate. Alasan.`
- `2026-09-16` (audit menyeluruh): **Tak ada perubahan `CLAUDE.md`; kontrak justru ditegakkan ulang di
  kode.** Infra/test: `docker-compose.yml` (port `127.0.0.1`, `LOG_STACK=daily`), `phpunit.xml`
  (`APP_ENV` force), `.github/workflows/ci.yml` (checkout v7/setup-node v7/cache v6), lockfile composer &
  npm. Docs: `docs/SETUP-GUIDE.md` (§1/§2/§3/§9/§10/§11/§14: angka test, port lokal, `gh`, log harian,
  gejala CI Vite, aturan exception), `docs/BUSINESS-FLOW.md` §3.4 (driver tanpa channel gate.queue),
  `docs/CODE-WALKTHROUGH.md` (§K login/logout Action, §P.3 channel, §AA QR), `.claude/skills/slice`,
  `.claude/agents/security-reviewer` (+ cek channel broadcast); `docs/tas-claude-code-guide.html`
  DIHAPUS. Seeder: **tak ada**. Alasan: audit menemukan klaim dokumen usang dan kode yang menyimpang
  dari kontrak yang sudah benar tertulis.
- `2026-09-16` (B7 + B8 + fix CI): **Kontrak alarm job gagal permanen ditambahkan.** Kode:
  `app/Listeners/AlertOnPermanentJobFailure.php`, `app/Notifications/PermanentJobFailureNotification.php`
  (baru), `config/tas.php` (`alerts.mail_to` ← `TAS_ALERT_MAIL`, `alerts.throttle_minutes` ←
  `TAS_ALERT_THROTTLE_MINUTES`), komentar `failed()` di 3 job. Test baru: `PermanentJobFailureAlertTest`,
  `SlotAvailabilityFreshnessTest`; `ApiJsonErrorsTest` (+`withoutVite()`). Docs: `CLAUDE.md` §Queue
  (1 baris: alarm = listener global, jangan per job), `docs/CODE-WALKTHROUGH.md` §J (gerbang cache) &
  §O.4 (baru), `docs/SETUP-GUIDE.md` §3 (`horizon:terminate` dari `app`) & §10 (env alarm), `HANDOVER`.
  Seeder: **tak ada**. Alasan: kegagalan permanen sebelumnya tak memberi tahu siapa pun; kontrak ditulis
  supaya sesi berikutnya tak menambah alerting per job di `failed()`.
- `2026-09-15` (N1/B4/N2/B5): **`CLAUDE.md` §Cache — `Cache::tags` diputuskan tidak dipakai** (sebelumnya
  "belum diputuskan"). Kode: `UpdateUserAction` (cabut token saat ganti password), `bootstrap/app.php`
  (error API selalu JSON), `playwright.config.ts` (`expect.timeout` 15 dtk). Docs: `CLAUDE.md` §Cache,
  `docs/CODE-WALKTHROUGH.md` (§V ganti password, catatan cache), `docs/SETUP-GUIDE.md` §10 (header
  `Accept` tak wajib) & §15d (margin e2e), `docs/ONBOARDING.md` & `docs/adr/0007` (rujukan tags),
  `HANDOVER` (§Temuan D2′ FIXED, Backlog B1–B8). Seeder: **tak ada**. Alasan: keputusan yang menggantung
  di kontrak membuat tiap sesi menimbang ulang hal yang sama.
- `2026-09-14` (D5′): **Semua dokumen disinkronkan ke Docker sebagai jalur utama; yang usang dihapus.**
  Kode/infra sesi ini: `docker-compose.yml` + `docker/php/Dockerfile` (volume `vendor`, OPcache CLI),
  `.env.example` (kunci Reverb dev). Docs: `docs/SETUP-GUIDE.md` (ditulis ulang), `README.md`,
  `docs/ONBOARDING.md`, `CLAUDE.md`, `docs/CODE-WALKTHROUGH.md`, `docs/DUMMY-DATA.md`,
  `docs/FRONTEND.md`, `docs/adr/0007` (catatan eksekusi + status), `.github/workflows/ci.yml`
  (komentar), `HANDOVER.md` (jurnal & changelog pra-Docker dihapus, bagian hidup ditulis ulang).
  Seeder: **tak ada**. Alasan: dokumen adalah satu-satunya yang menyeberangi sesi — klaim usang di
  dalamnya persis yang menyesatkan sesi berikutnya; riwayat tetap utuh di tag `pre-docker`.
- `2026-09-14` (D6′ + 2 fix): **Scheduler + Reverb di container; isolasi test & server web container
  diperbaiki.** Kode/infra: `docker-compose.yml` (service `scheduler`/`reverb`, env PHP via anchor),
  `docker/php/Dockerfile` (`serve --no-reload`), `phpunit.xml` (force `DB_DATABASE` + koreksi komentar
  D3′), `tests/TestCase.php` (`$_ENV`→`$_SERVER`). Docs (minimal — sisanya D5′): `HANDOVER` §Status/
  §Temuan D6′ (baru)/§Sedang dikerjakan/§MULAI SESI/§Langkah berikutnya (D6′ selesai, D5′ didefinisikan
  ulang dengan checklist) + penanda `[DIKOREKSI]` pada klaim D2′ & D3′ yang terbukti salah;
  `ADR-0007` §Catatan eksekusi; `CLAUDE.md` §Stack/§Perintah (baris yang kini salah saja). Seeder:
  **tak ada**. Alasan: jurnal & ADR tak ditulis ulang — klaim lama diberi penanda koreksi supaya
  pembaca sesi berikutnya tak mewarisi keyakinan yang sudah dibantah bukti.
- `2026-09-12` (penutup sesi): **Sinkronisasi dokumen dengan keadaan pasca-D3′ + catatan mulai
  sesi berikutnya.** **Tak ada kode/infra yang berubah — dokumen saja.** Menambal 6 klaim yang
  jadi tidak akurat setelah migrasi: `README` §Stack ("Redis masih target — belum dipakai"),
  `SETUP-GUIDE` banner ("stack ini belum diganti apa pun" → tabel D1/D2′/D3′),
  `ONBOARDING` banner (**"migrasi belum dieksekusi"** + poin race-condition yang sudah ditutup D1)
  & 2 baris cheat-sheet jebakan (Horizon, `Cache::tags`) & blok perintah harian (`php artisan
  horizon` manual → service container), `DUMMY-DATA` (`migrate:fresh --seed` kini mengisi DB
  berbeda di tiap jalur). `HANDOVER` §Sedang dikerjakan diganti blok **"MULAI SESI BERIKUTNYA
  DARI SINI"** (nyalakan infra, CI D3′ yang belum diverifikasi, sisa pekerjaan, catatan mesin).
  Alasan: dokumen adalah satu-satunya yang menyeberangi sesi, dan klaim basi di dalamnya persis
  yang menyesatkan sesi berikutnya.
- `2026-09-12` (D3′): **Redis + Horizon + gerbang ShouldBeUnique berbasis perilaku.** Kode/infra:
  `docker-compose.yml` (+service `redis` & `horizon`, env redis di `app`), `phpunit.xml`
  (**`force="true"` pada baris isolasi** + `REDIS_DB`/`REDIS_CACHE_DB`=15),
  `.github/workflows/ci.yml` (+service `redis:7-alpine`, +ekstensi `redis`, +env REDIS_*).
  Test: `tests/Feature/Hardening/RedisUniqueJobTest.php` (**baru**, 3 test, skip tanpa Redis).
  262→**265 Pest** (container). **Tak ada kode aplikasi yang berubah.** Docs: ADR-0007,
  `CLAUDE.md` §Stack/§Cache/§Queue (**3 baris kontrak yang sudah tak akurat ditambal**, termasuk
  `uniqueId()` yang salah sejak ronde 4), `SETUP-GUIDE §18` (**baru**) + daftar isi,
  `CODE-WALKTHROUGH §O.2`/`§O.3`, `README`, `HANDOVER` §Status/§Sedang dikerjakan/§Langkah
  berikutnya/changelog ini. Seeder: **tak ada**. Alasan: `sync` melepas lock unique seketika,
  jadi klaim `uniqueId()` hanya bisa dijaga sebagai string sampai Redis nyata tersedia.
- `2026-09-12` (D2′): **Containerisasi app, dimajukan mendahului Redis.** Kode/infra:
  `docker/php/Dockerfile` (**baru**), `.dockerignore` (**baru**), `docker-compose.yml` (+service
  `app`). **Tak ada kode aplikasi (`app/`, `tests/`) yang berubah.** Docs: ADR-0007 (+catatan
  urutan diubah + tabel pengukuran bind mount), `CLAUDE.md` §Stack (**app naik dari target ke
  keadaan**; Redis tetap target), `SETUP-GUIDE §17` (**baru**) + banner + daftar isi, `README`,
  `HANDOVER` §Status/§Temuan D2′ (**baru**, bug 500-vs-401)/§Langkah berikutnya (dinomori ulang
  D2′–D6′)/changelog ini. Seeder: **tak ada**. Alasan: PHP Windows native tak punya klien Redis,
  jadi Redis-sebelum-container menuntut DLL yang akan terbuang satu slice kemudian.
- `2026-09-12` (D1): **MySQL di Docker + test konkurensi sungguhan + CI service container.**
  Kode/infra: `docker-compose.yml` (**baru**, MySQL 8.4 saja), `docker/mysql/init/01-databases.sql`
  (**baru**), `phpunit.xml` (DB → MySQL, +testsuite `Concurrency`), `tests/Pest.php`
  (+`DatabaseTruncation` untuk Concurrency), `.github/workflows/ci.yml` (+service `mysql:8.4`,
  +ekstensi `pdo_mysql`, +env DB pada step Pest). Test: `tests/Concurrency/ConcurrentBookingTest.php`
  (**baru**, 2 test) + `tests/Concurrency/bin/book-once.php` (**baru**, anak proses — bukan test).
  260→**262 Pest/671 assert**. **Tak ada kode aplikasi (`app/`) yang berubah** — murni infrastruktur
  uji. Docs: `docs/adr/0007-*.md` §Catatan eksekusi, `CLAUDE.md` §Hardening/Race, `ARCHITECTURE §7`,
  `CODE-WALKTHROUGH §J.1`, `SETUP-GUIDE §16` (**baru**) + banner + daftar isi, `HANDOVER` §Status/
  §Temuan (→FIXED)/§Langkah berikutnya (D1 selesai, D3 menyusut)/changelog ini. Seeder: **tak ada**.
  Alasan: sqlite tak menerbitkan `SELECT … FOR UPDATE`, jadi gerbang anti-race mustahil jujur di
  atasnya — lihat §Temuan 2026-09-12.
- `2026-09-12`: **Keputusan migrasi Docker/Redis/MySQL (ADR-0007) + penanda batas pra-Docker +
  koreksi klaim "race-handling teruji".** **Tak ada kode aplikasi yang berubah** — dokumen &
  keputusan saja. Docs: `docs/adr/0007-docker-redis-mysql-migration.md` (**baru** — 3 keputusan
  teknis + urutan slice D1–D5 + biaya yang diterima sadar), `docs/adr/README.md` (+baris 0007),
  `docs/adr/0005-ci-github-actions.md` (+catatan trigger "SQLite→MySQL untuk test" **terpenuhi**,
  dan klaim "tak perlu service container DB" ditandai akan berhenti berlaku di D3), `CLAUDE.md`
  (§Stack +status migrasi disetujui-belum-dieksekusi & larangan mengubah baris Docker per slice;
  §Hardening/Race +peringatan lock belum dijaga test), `docs/ARCHITECTURE.md` §7 (**klaim
  "race-handling teruji" DIKOREKSI** — tak benar sejak ditulis), `docs/CODE-WALKTHROUGH.md` §J.1
  (+peringatan di tempat `lockForUpdate()` dijelaskan), `docs/SETUP-GUIDE.md` (+banner "dokumen ini
  = stack pra-Docker" + daftar § yang akan berubah), `docs/ONBOARDING.md` (+banner 2 hal untuk
  developer baru), `README.md` (+catatan pra-Docker), `HANDOVER.md` (§Batas pra-Docker, entri
  Status, §Temuan 2026-09-12, §Langkah berikutnya dirombak jadi D1–D5 + B1–B3 + arsip).
  Seeder: **tak ada perubahan**. Alasan: temuan mutasi (test race-condition tetap PASS tanpa
  `lockForUpdate()`) membalik urutan plan — kebenaran uji naik jadi slice #1 — dan batas pra/pasca
  Docker perlu ditandai eksplisit sebelum satu baris infra pun disentuh.
- **Entri changelog sebelum 2026-09-12 (era pra-Docker) dihapus 2026-09-14** — riwayat, bukan
  keadaan. Utuh di `git show pre-docker:HANDOVER.md` (juga `git log -- docs/ CLAUDE.md`).

## Jebakan / catatan

> Jebakan **infrastruktur & lingkungan** (Docker, isolasi test, `vendor/` volume, `--no-reload`,
> Reverb dua alamat, `public/hot` basi, kunci Reverb) kini terkumpul di **`SETUP-GUIDE` §3, §6, §11,
> §17, §18** — satu tempat, bukan tersebar di jurnal. Di sini hanya yang bersifat proses/proyek.
> Jebakan era native (SQLite, `queue:listen`, Reverb native, `php.ini` laragon) sudah usang dan dihapus
> (utuh di `git show pre-docker:HANDOVER.md`).

- **"Hijau" bukan bukti.** Tiga kali di proyek ini klaim "terverifikasi" gugur: race condition (test
  sekuensial tetap hijau tanpa lock), isolasi test di container (hijau sambil menulis ke DB dev), app di
  container (200 padahal membaca SQLite). Dua pertanyaan wajib sebelum menulis "terverifikasi":
  **(a)** *apakah test ini MERAH kalau fitur yang dijaganya dirusak?* — rusak beneran, jalankan;
  **(b)** *data ini benar-benar mendarat di mana?* Dan **ulangi uji merah setelah fix** — fix pertama
  isolasi test (cuma `force="true"`) terlihat meyakinkan dan tidak menggigit.
- **Git:** 1 slice = 1 commit; atribusi `Co-Authored-By: Overa Caesar` (bukan Claude) — lihat
  `docs/GIT-HISTORY-REWRITE.md`. GitHub membuat **1 run CI per PUSH, bukan per commit**.
- **Repo privat → CI dibaca lewat `gh`** (terpasang & login sejak 2026-09-16; REST API tanpa token
  tetap 404). Jangan menulis "CI hijau" sebelum `gh run watch <id> --exit-status` benar-benar selesai
  sukses — sesi 2026-09-15 menutup pekerjaan "hijau lokal" yang ternyata merah di CI.
- **CI job Backend tak membangun frontend.** Test Pest yang merender view ber-`@vite` lulus di lokal
  (karena `public/build/` gitignored kebetulan ada) tapi gagal di CI. Pakai `withoutVite()`; cek dengan
  memindahkan `public/build/manifest.json` lalu jalankan test-nya.
- **Test yang memicu event manual bukan gerbang wiring Action.** `event(new X)` membuktikan listener,
  bukan bahwa Action memanggil `X::dispatch`. Untuk efek samping penting, panggil Action sungguhan
  (pola `SlotAvailabilityFreshnessTest`).
- **Satu pengukuran = anekdot.** Dugaan penyebab e2e lambat (kompilasi Blade) gugur saat diulang; bila
  penyebab tak bisa direproduksi, pasang margin berlabel — jangan klaim akar masalah hilang.
- **Memulihkan mutasi: salin file dulu, jangan `git checkout`.** Pada file yang perbaikannya belum
  di-commit, `git checkout -- file` mengembalikan versi LAMA yang justru sedang diperbaiki (terjadi di
  audit 2026-09-16; tertangkap karena skrip memeriksa hasil pemulihan).
- **Jangan menyunting kode PHP ber-backslash lewat sed/awk di Git Bash.** Namespace kehilangan
  backslash tanpa error; Pint lalu menghapus import rusak sebagai "unused". Pakai Edit tool atau skrip
  PHP, lalu verifikasi dengan refleksi/PHPStan — `php -l` tak menangkap interface yang tak ter-import.
- **`npm audit fix` bisa crash `Cannot read properties of null (reading 'edgesOut')`** (bug arborist
  npm 11 saat menyusun peer set vitest). Jalan: `npm update <paket>` per paket; untuk vitest
  `npm install vitest@^X --save-dev --legacy-peer-deps`, lalu bandingkan lockfile (paket hilang = 0).
- **Exception domain baru yang punya `render()` wajib `implements ShouldntReport`** — kalau lupa,
  `DomainExceptionReportingTest` merah. Tanpa itu tiap penolakan 4xx menulis ERROR + stack trace.
- **Ukuran image di Docker Desktop menyesatkan.** Kolom *Size* menghitung layer bersama di setiap
  baris; yang benar `docker system df -v` (UNIQUE SIZE). Keempat service PHP memakai **satu** image
  `tas-php:dev` yang hanya dibangun `app` — jangan kembalikan `build:` ke anchor `x-php` (4 build, 4 tag,
  `<none>` menumpuk). Bersihkan dengan filter label, bukan `docker image prune` polos (`SETUP-GUIDE §17g`).
- **Port container hanya `127.0.0.1`.** App tak bisa dibuka dari perangkat lain di jaringan; jangan
  "perbaiki" dengan membuka ulang `mysql`/`redis` ke semua antarmuka (`SETUP-GUIDE §2`).
- **`DemoSeeder` relatif ke tanggal seed** dan **scheduler menyapu data demo lampau jadi `NO_SHOW`**
  — "slot kosong / status berubah sendiri" biasanya bukan bug; seed ulang dulu (`DUMMY-DATA.md`).
- **Mematikan proses di Windows: pakai PID, bukan nama.** `taskkill /IM php.exe` pernah mematikan
  semua proses PHP user; menghentikan pembungkus `npm run dev` pernah meninggalkan Vite yatim +
  `public/hot` basi. Cek port (`Get-NetTCPConnection`) setelah menghentikan server.
- **Frontend versi di-pin ke vite 6** (kompat `laravel-vite-plugin@1.2`) → `vue-router@^4`,
  `@vitejs/plugin-vue@^5`. `npm install` paket baru: cek peer vs vite 6 dulu. TS 6 men-deprecate
  `baseUrl` → `tsconfig` pakai `paths` relatif.
- **Akun demo & password:** `docs/DUMMY-DATA.md` (semua `password`).

## Lingkungan (dev)
> Menjalankan & mengoperasikan: `docs/SETUP-GUIDE.md` (§1 dari clone, §3 sehari-hari).
> Penjelasan kode: `docs/CODE-WALKTHROUGH.md` (backend) · `docs/FRONTEND.md` (SPA).
```bash
docker compose up -d --wait                                # mysql redis app horizon scheduler reverb
docker compose exec app php artisan migrate:fresh --seed   # data demo
docker compose exec app composer test && docker compose exec app composer analyse
docker compose run --rm --no-deps app composer install     # setelah composer.lock berubah
# Frontend (SPA Vue di resources/js), native:
npm run dev               # Vite HMR — app tetap dibuka di http://localhost:8000
npm run test:js           # Vitest
npm run type-check        # vue-tsc --noEmit
npm run build             # bundel produksi → public/build
```

---

### Checklist sebelum commit (anti-drift)
- [ ] Dokumen sumber kebenaran sudah diubah **sebelum** kode.
- [ ] Perubahan dicatat di *Changelog* di atas (apa & kenapa).
- [ ] Seeder ikut diupdate; `migrate:fresh --seed` hijau.
- [ ] `RolePermissionSeeder` & Policy cocok dengan matriks RBAC (BUSINESS-FLOW §1).
- [ ] `DemoSeeder` menyentuh semua status (BUSINESS-FLOW §2) & semua entitas (§4).
- [ ] `composer test / analyse / fix` bersih.
- [ ] Test baru yang merender view `@vite` lulus **tanpa** `public/build/` (CI Backend tak build frontend).
- [ ] Test penjaga dibuktikan menggigit: rusak kodenya, pastikan test yang tepat merah, pulihkan.
- [ ] Setelah push: `gh run watch <id> --exit-status` sampai hijau sebelum menyebut pekerjaan selesai.
- [ ] Dokumen + kode + seeder + HANDOVER dalam **satu** PR.
