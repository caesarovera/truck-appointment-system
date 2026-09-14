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

# Composer diambil dari image resminya — lebih ringkas dan ter-pin ketimbang
# mengunduh installer lalu memverifikasi hash sendiri.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Jalan sebagai user non-root dengan UID 1000 supaya berkas yang dibuat di dalam
# container (cache Laravel, hasil composer) tidak muncul sebagai milik root di
# bind mount. Di Docker Desktop Windows kepemilikan memang di-abstraksi, tapi
# baris ini membuat image tetap benar kalau kelak jalan di Linux/WSL2 langsung.
RUN useradd -u 1000 -m -s /bin/bash app && chown -R app:app /var/www/html
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
