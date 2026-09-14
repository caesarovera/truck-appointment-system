# Image PHP untuk TAS — ADR-0007.
#
# Dipakai oleh service `app` (dan nanti `horizon`/`reverb`) di docker-compose.yml.
# Basis CLI, bukan fpm: dev sekarang memakai `php artisan serve`, dan slice ini
# menerjemahkan setup yang SUDAH ADA ke container — bukan sekalian mengganti web
# server. nginx + php-fpm menyusul kalau/ketika memang dibutuhkan (lihat ADR-0007).
FROM php:8.3-cli

# Ekstensi PHP yang dibutuhkan proyek ini. Alasan tiap baris ditulis supaya
# penambahan berikutnya tidak asal tempel:
#   pdo_mysql — DB (slice D1)
#   pcntl     — WAJIB Horizon. Justru inilah yang TAK BISA dipenuhi PHP Windows
#               native, dan salah satu alasan utama containerisasi ini ada.
#   posix     — ikut diminta Horizon
#   gd        — endroid/qr-code (PngWriter) untuk gambar QR gate-in
#   zip       — dipakai Composer saat mengurai paket
#   redis     — klien Redis (pecl). Di Linux cukup satu baris; di PHP Windows
#               native ini butuh DLL yang cocok versi/TS/arsitektur — persis
#               friksi yang menghilang berkat container ini.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pcntl posix gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# OPcache untuk SAPI CLI. `php artisan serve` (server web service `app`) ADALAH SAPI CLI,
# dan default-nya opcache.enable_cli=0.
# PERINGATAN — baris ini SENDIRIAN TIDAK MEMPERCEPAT (diukur: median 3,4 → 4,0 dtk). Biaya
# sebenarnya bukan kompilasi tapi stat() ~925 berkas per request lewat bind mount NTFS, dan
# validate_timestamps=1 tetap men-stat semuanya. Yang membuatnya efektif: vendor/ (88% berkas
# itu) di named volume Linux — lihat docker-compose.yml. Keduanya bersama: 0,13 dtk beruntun,
# 0,64 dtk setelah idle.
# validate_timestamps=1 ditulis eksplisit karena WAJIB untuk dev: edit kode tetap terlihat
# tanpa restart (paling lambat revalidate_freq = 2 dtk, default). Mematikannya memang lebih
# cepat, tapi kode lama lalu jalan DIAM-DIAM sampai restart. JANGAN salin ke produksi — di
# sana kode ada di dalam image dan validate_timestamps=0 justru benar.
# Berkas conf.d, bukan php.ini: php.ini akan ikut menentukan variables_order, dan perilaku
# env var container sudah cukup rapuh (lihat catatan --no-reload di CMD).
# Proses panjang (horizon) tetap butuh `php artisan horizon:terminate` setelah edit kode —
# itu sifat worker, bukan OPcache, dan sudah berlaku sebelum baris ini ada.
RUN { \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=1'; \
    } > /usr/local/etc/php/conf.d/zz-opcache-dev.ini

# Composer diambil dari image resminya — lebih ringkas dan ter-pin ketimbang
# mengunduh installer lalu memverifikasi hash sendiri.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Jalan sebagai user non-root dengan UID 1000 supaya berkas yang dibuat di dalam
# container (cache Laravel, hasil composer) tidak muncul sebagai milik root di
# bind mount. Di Docker Desktop Windows kepemilikan memang di-abstraksi, tapi
# baris ini membuat image tetap benar kalau kelak jalan di Linux/WSL2 langsung.
#
# mkdir vendor: di docker-compose.yml, vendor/ adalah named volume (lihat komentarnya). Volume
# kosong mewarisi kepemilikan direktori dari IMAGE saat pertama dipasang; tanpa baris ini
# direktori itu dibuat milik root dan `composer install` sebagai user `app` gagal menulis.
RUN useradd -u 1000 -m -s /bin/bash app \
    && mkdir -p /var/www/html/vendor \
    && chown -R app:app /var/www/html
USER app

# Perintah default (dipakai service `app`; service lain meng-override `command:`).
#
# --no-reload WAJIB. Tanpa flag ini, selama berkas .env ada, ServeCommand menjalankan
# server web sebagai proses anak dan MEMBUANG setiap env var di luar whitelist-nya
# (ServeCommand::$passthroughVariables) supaya .env bisa di-reload saat berubah. Image ini
# tanpa php.ini → variables_order=EGPCS → semua env var service compose (DB_HOST=mysql,
# CACHE_STORE=redis, BROADCAST_CONNECTION=reverb, ...) ikut terbuang, dan server web
# kembali membaca .env milik jalur native: SQLite, driver `database`, broadcaster `log`.
# Sejak D2' app web di container diam-diam melayani dari database/database.sqlite (bind
# mount) sementara CLI (artisan, horizon, test) memakai MySQL/Redis — terbukti D6' dari
# token login lewat :8000 yang masuk SQLite, bukan MySQL `tas`.
# Harganya: mengubah .env butuh `docker compose restart app` (tak ada auto-reload).
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
