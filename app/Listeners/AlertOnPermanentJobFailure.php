<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Notifications\PermanentJobFailureNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * JobFailed hanya dipicu saat job menyerah (bukan tiap percobaan), dan untuk SEMUA job — termasuk
 * job broadcast framework yang tak punya failed() milik kita. Sengaja tidak ShouldQueue.
 */
final class AlertOnPermanentJobFailure
{
    public function handle(JobFailed $event): void
    {
        $context = [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'uuid' => $event->job->uuid(),
            'exception' => $event->exception::class,
            'message' => Str::limit($event->exception->getMessage(), 500),
        ];

        Log::critical('Queue job gagal permanen', $context);

        $to = config('tas.alerts.mail_to');

        if (! is_string($to) || $to === '' || ! $this->claimMailSlot($context['job'])) {
            return;
        }

        // Listener ini jalan di blok `finally` Job::fail() — exception di sini akan menutupi error asli job.
        try {
            Notification::route('mail', $to)->notify(new PermanentJobFailureNotification(
                jobName: $context['job'],
                queue: $context['queue'],
                uuid: $context['uuid'],
                exceptionClass: $context['exception'],
                exceptionMessage: $context['message'],
            ));
        } catch (Throwable $e) {
            Log::error('Email alarm job gagal permanen tak terkirim', [
                'job' => $context['job'],
                'alert_error' => $e->getMessage(),
            ]);
        }
    }

    private function claimMailSlot(string $jobName): bool
    {
        $minutes = (int) config('tas.alerts.throttle_minutes', 15);

        return Cache::add('tas:alerts:job-failed:'.sha1($jobName), true, now()->addMinutes($minutes));
    }
}
