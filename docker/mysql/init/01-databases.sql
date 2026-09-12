-- Dijalankan SEKALI saja, saat volume mysql-data masih kosong.
-- Mengubah file ini tidak berpengaruh pada container yang sudah terlanjur dibuat;
-- perlu `docker compose down -v` (HAPUS DATA) supaya init diulang.

-- DB dev sudah dibuat oleh MYSQL_DATABASE di docker-compose.yml. Yang belum: DB test.
--
-- Kenapa DB test TERPISAH dari DB dev: `RefreshDatabase` menjalankan `migrate:fresh`,
-- yang MENGHAPUS seluruh tabel. Kalau test menumpang DB dev, sekali `composer test`
-- data demo hasil seeder ikut hilang. Pemisahan ini mencegah jebakan itu.
CREATE DATABASE IF NOT EXISTS `tas_testing`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Pest berjalan `--parallel` (12 proses di mesin dev ini). Laravel membuat satu DB
-- per proses bernama `tas_testing_test_{TOKEN}` (lihat Illuminate\Testing\Concerns\TestDatabases).
-- Pembuatannya otomatis saat test jalan, TAPI butuh hak CREATE DATABASE — itulah
-- sebabnya test terhubung sebagai root, bukan user aplikasi biasa.
-- Baris di bawah cuma menegaskan hak itu ada (root sudah punya secara default);
-- ditulis eksplisit supaya orang berikutnya tahu syarat ini disengaja, bukan kebetulan.
GRANT ALL PRIVILEGES ON `tas_testing%`.* TO 'root'@'%';
FLUSH PRIVILEGES;
