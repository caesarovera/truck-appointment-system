# docs/DUMMY-DATA.md — truck-appointment-system

Jalankan: `php artisan migrate:fresh --seed`

**Prasyarat:** `DatabaseSeeder.php` (bawaan Laravel, sudah ada) harus memanggil keduanya dengan urutan ini:
```php
$this->call([
    RolePermissionSeeder::class, // role & permission duluan
    DemoSeeder::class,
]);
```
`DatabaseSeeder` memanggil `RolePermissionSeeder` lalu `DemoSeeder`.

## Yang dibuat

**Role & permission** (Spatie): `admin`, `planner`, `gate-officer`, `transporter`, `driver` + permission sesuai `BUSINESS-FLOW.md §1`.

**Akun demo** (password semua: `password`)

| Email | Role | Catatan |
|-------|------|---------|
| `admin@tas.test` | admin | akses penuh |
| `planner@tas.test` | planner | atur slot |
| `gate@tas.test` | gate-officer | ditugaskan ke Terminal JICT |
| `dispatcher@majulog.test` | transporter | PT Maju Logistik |
| `dispatcher@sinarkargo.test` | transporter | PT Sinar Kargo |
| `budi@majulog.test` | driver | sopir Maju Logistik |
| `andi@sinarkargo.test` | driver | sopir Sinar Kargo |

**Master data**
- 1 Terminal (JICT) + 2 Gate (GATE-A, GATE-B).
- 2 perusahaan angkutan, masing-masing 3 truk + 1–2 sopir.
- Slot windows: kemarin (untuk data COMPLETED/NO_SHOW), **hari ini** (untuk uji gate-in/gate-out & sisa kuota), besok (untuk uji booking). 06:00–18:00 per jam, kapasitas 5/jam (sengaja kecil supaya gampang menguji kondisi penuh & race).
- Satu window hari ini sengaja dibuat **hampir penuh** (sisa 1) → untuk demo race condition saat dua transporter booking bersamaan.

**Appointment contoh** (menyentuh semua status):
- 2× `COMPLETED` (kemarin, lengkap dengan gate-in & gate-out).
- 1× `NO_SHOW` (kemarin, kuota sudah dikembalikan).
- 2× `CONFIRMED` hari ini (siap di-gate-in untuk demo).
- 1× `ARRIVED`/`IN_PROGRESS` hari ini (siap di-gate-out).
- 2× `BOOKED` besok (siap di-reschedule/cancel).
- 1× `CANCELLED` besok.

Tiap appointment punya `booking_code`, 1 container, dan terhubung ke truk+sopir company yang benar — sehingga Policy `company_id` & `driver_id` bisa langsung diuji.

> Catatan: seeder mengasumsikan model & migrasi sudah dibuat sesuai entitas di `BUSINESS-FLOW.md §4`. Bangun model/migrasi dulu di Claude Code, baru jalankan seeder.
