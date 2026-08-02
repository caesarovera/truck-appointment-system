<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | No-show grace period (menit)
    |--------------------------------------------------------------------------
    | Setelah window.end + grace ini terlewati, appointment yang masih BOOKED/
    | CONFIRMED (truk tak datang) ditandai NO_SHOW oleh NoShowSweepJob & kuotanya
    | dikembalikan. Lihat docs/BUSINESS-FLOW.md §2.
    */
    'no_show_grace_minutes' => (int) env('TAS_NO_SHOW_GRACE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | No-show sweep chunk size
    |--------------------------------------------------------------------------
    | `dueForNoShow` memindai kandidat secara chunk (chunkById) supaya memori
    | tetap terbatas saat appointment pra-kedatangan menumpuk di produksi —
    | hanya N baris di-hydrate per iterasi, bukan seluruh tabel sekaligus.
    */
    'no_show_chunk_size' => (int) env('TAS_NO_SHOW_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Toleransi gate-in (menit)
    |--------------------------------------------------------------------------
    | Truk hanya boleh masuk di SEKITAR jendelanya (BUSINESS-FLOW §2 & §3.5):
    | mulai `early_minutes` sebelum window.start sampai `late_minutes` setelah
    | window.end. Di luar itu ditolak 409 gate_in_too_early / gate_in_too_late.
    | Tanpa ini status CONFIRMED saja sudah cukup — appointment minggu depan bisa
    | gate-in hari ini dan kuota jam sibuk terpakai truk yang datang kapan saja.
    |
    | `late_minutes` default SAMA dengan no_show_grace_minutes: keduanya
    | menggambarkan tenggat yang sama dari dua sisi. Kalau dibuat lebih besar dari
    | grace, NoShowSweepJob keburu menandai NO_SHOW → penolakannya berubah jadi
    | invalid_state; kalau lebih kecil, ada zona mati sampai sweep berikutnya
    | jalan. Ubah keduanya bersamaan.
    */
    'gate_in' => [
        'early_minutes' => (int) env('TAS_GATE_IN_EARLY_MINUTES', 30),
        'late_minutes' => (int) env('TAS_GATE_IN_LATE_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder lead time (menit)
    |--------------------------------------------------------------------------
    | Seberapa lama sebelum window.start sopir diingatkan (H-2 jam = 120 menit
    | default). Dipakai listener ScheduleAppointmentReminder untuk menunda
    | AppointmentReminderJob.
    */
    'reminder_lead_minutes' => (int) env('TAS_REMINDER_LEAD_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per menit)
    |--------------------------------------------------------------------------
    | Pertahanan abuse (CLAUDE.md → Hardening §rate limit). Didaftarkan sebagai
    | named limiter di AppServiceProvider::boot(), dipasang di routes/api.php.
    |  - login   : anti brute-force, dikunci per email+ip.
    |  - api     : batas umum endpoint ber-auth, dikunci per user id (fallback ip).
    |  - booking : lebih ketat dari `api` (anti bot borong slot), per user id.
    */
    'rate_limits' => [
        'login' => (int) env('TAS_RL_LOGIN', 5),
        'api' => (int) env('TAS_RL_API', 60),
        'booking' => (int) env('TAS_RL_BOOKING', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency (middleware Idempotency-Key)
    |--------------------------------------------------------------------------
    | lock_seconds: TTL lock saat 1 request kembar diproses. HARUS lebih panjang
    |   dari durasi worst-case handler (booking + broadcast) supaya lock tak
    |   kedaluwarsa di tengah jalan & membiarkan duplikat menyelinap.
    | ttl_hours: berapa lama respons sukses disimpan untuk diputar ulang.
    */
    'idempotency' => [
        'lock_seconds' => (int) env('TAS_IDEMPOTENCY_LOCK_SECONDS', 60),
        'ttl_hours' => (int) env('TAS_IDEMPOTENCY_TTL_HOURS', 24),
    ],

];
