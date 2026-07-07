<?php

declare(strict_types=1);

use App\Jobs\NoShowSweepJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sapu no-show tiap 5 menit (BUSINESS-FLOW §3.5). WithoutOverlapping ada di job.
Schedule::job(new NoShowSweepJob)->everyFiveMinutes();

// Bersihkan baris token yang sudah kedaluwarsa >24 jam (sanctum.expiration = 12 jam).
// Grace 24 jam: token mati masih bisa ditelusuri sehari saat investigasi insiden.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
