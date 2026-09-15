<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sengaja TIDAK ShouldQueue: alarm untuk queue yang gagal tak boleh bergantung pada queue itu.
 */
final class PermanentJobFailureNotification extends Notification
{
    public function __construct(
        public readonly string $jobName,
        public readonly string $queue,
        public readonly ?string $uuid,
        public readonly string $exceptionClass,
        public readonly string $exceptionMessage,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('[TAS] Job gagal permanen — '.class_basename($this->jobName))
            ->line('Job: '.$this->jobName)
            ->line('Queue: '.$this->queue)
            ->line('Error: '.$this->exceptionClass.' — '.$this->exceptionMessage)
            ->line('Detail lengkap ada di Horizon (Failed Jobs) atau tabel failed_jobs.');

        if ($this->uuid !== null) {
            $mail->line('Ulangi setelah penyebabnya diperbaiki: php artisan queue:retry '.$this->uuid);
        }

        return $mail;
    }
}
