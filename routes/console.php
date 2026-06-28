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
