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

];
