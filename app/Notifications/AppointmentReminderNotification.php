<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat H-2 jam ke sopir sebelum window mulai. Dipancarkan oleh
 * AppointmentReminderJob memakai data window terkini (tahan reschedule).
 */
final class AppointmentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $window = $this->appointment->slotWindow;
        $when = $window !== null
            ? $window->date->toDateString().' '.$window->start_time
            : 'jadwal Anda';

        return (new MailMessage)
            ->subject('Pengingat Jadwal Gate — '.$this->appointment->booking_code)
            ->line("Truk Anda dijadwalkan tiba pada {$when}.")
            ->line('Kode booking: '.$this->appointment->booking_code)
            ->line('Mohon tiba tepat waktu untuk menghindari status no-show.');
    }
}
