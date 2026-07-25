# ADR-0005 — CI di GitHub Actions; e2e ditunda

**Status:** Accepted · 2026-07-25

## Context

`CLAUDE.md` mencantumkan **CI: GitHub Actions** di bagian Stack (dan di urutan build langkah 1),
tapi sampai 2026-07-25 repo **tidak punya `.github/` sama sekali** — juga tidak punya
`docker-compose.yml` yang diklaim di baris yang sama. Klaim itu tidak pernah benar.

Akibatnya nyata, bukan kosmetik: 191 test Pest dan 87 test Vitest **hanya berjalan bila ada
orang yang ingat mengetik perintahnya**. Sebuah commit bisa hijau di laptop seseorang dan
merah di repo tanpa ada mekanisme yang memberi tahu. Untuk proyek yang dikerjakan lintas sesi
dan lintas perangkat — persis pola kerja proyek ini — itu lubang yang serius: gerbang kualitas
yang sudah dibangun susah payah tidak menjaga apa pun pada saat `git push`.

Pertanyaan kedua muncul bersamaan: apakah sekalian menambah **e2e** (Playwright/Cypress)?
Fakta yang relevan saat menimbang:
- Vitest berjalan di jsdom dan **mem-*mock* axios** → tak pernah benar-benar memanggil API.
  Pest menguji API tapi tak menjalankan SPA. Jadi ada seam yang memang belum teruji siapa pun:
  browser → HTTP → Laravel.
- Tapi e2e menuntut app benar-benar hidup (`serve` + Vite + DB ter-seed), sementara dev DB
  proyek ini adalah **file SQLite** (`database/database.sqlite`); `migrate:fresh --seed` untuk
  e2e akan menghapus data dev. Butuh env/DB terpisah lebih dulu.
- Halaman yang jadi jalur happy-path justru paling miskin pegangan: `LoginPage.vue` dan
  `BookingForm.vue` punya **0 `data-testid`**.
- Binary browser ~140 MB per mesin/runner, plus utang perawatan (e2e = lapisan paling rapuh).

## Decision

**Pasang CI dulu, tunda e2e.**

CI = satu workflow `.github/workflows/ci.yml`, **dua job paralel**:
- `backend` — `composer lint` → `composer analyse` → `composer test`
- `frontend` — `npm run test:js` → `npm run type-check` → `npm run build`

Prinsip yang dipegang: **CI menjalankan perintah yang sama persis dengan lokal** (skrip di
`composer.json`/`package.json`), bukan rangkaian perintah versi CI sendiri. Kalau CI memakai
perintah berbeda, "hijau di CI" dan "hijau di laptop" berhenti berarti sama, dan itu justru
sumber kebingungan baru.

E2E **tidak** ditambahkan sekarang. Bukan karena tak bernilai, tapi karena menambah lapisan uji
keempat di atas fondasi yang belum punya penegak otomatis adalah urutan yang terbalik.

## Consequences

**Untung:**
- Gerbang kualitas akhirnya mengikat pada `push`/PR, bukan pada ingatan orang.
- Klaim `CLAUDE.md` soal CI jadi benar — kontrak dan kenyataan tidak lagi berselisih.
- `composer install` di CI (Linux) berjalan **tanpa** `--ignore-platform-req`. Flag itu
  kebutuhan Windows karena Horizon minta `ext-pcntl`/`ext-posix`; di CI dependensi
  terverifikasi apa adanya — jadi CI sekaligus mendeteksi kalau ada dependensi yang selama ini
  lolos hanya berkat flag tersebut.
- Tak perlu service container DB: `phpunit.xml` memaksa sqlite `:memory:`.

**Rugi / risiko:**
- Seam browser→HTTP→Laravel **tetap tak teruji** sampai e2e dikerjakan. Yang menutupinya
  sementara: verifikasi manual di browser (lihat `HANDOVER.md` → *Langkah berikutnya*).
- CI hanya sekuat perintah yang dipanggilnya. Menambah gerbang baru di lokal **tanpa**
  menambahkannya ke workflow = gerbang itu tak berlaku saat push. Ini jebakan drift yang
  paling mungkin terjadi pada file ini.
- Setiap push memakai menit Actions (repo publik: gratis; privat: terhitung kuota).

## Kapan ditinjau ulang

Tambahkan **e2e** saat salah satu terjadi:
1. Ada regresi nyata yang lolos Pest+Vitest tapi tertangkap manual di browser (bukti seam itu
   memang menggigit, bukan kekhawatiran teoretis).
2. Sebelum rilis ke pengguna sungguhan — smoke test happy-path jadi jauh lebih berharga saat
   ada yang rugi kalau login/booking putus.

Prasyarat sebelum e2e dipasang (supaya tak jadi utang baru): `.env.e2e` + DB terpisah agar DB
dev tak terhapus, dan `data-testid` di `LoginPage`/`BookingForm`.

Tinjau ulang **workflow-nya sendiri** saat: menambah gerbang kualitas baru, pindah dari SQLite
ke MySQL untuk test, atau menaikkan versi PHP/Node di mesin dev (versi di workflow sengaja
dipatok menyamai mesin dev: PHP 8.3, Node 22).
