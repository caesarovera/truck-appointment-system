<?php

declare(strict_types=1);

use App\Contracts\GateEventGateway;
use App\Enums\GateTransactionType;
use App\Jobs\ProcessGateEventJob;
use App\Models\Appointment;
use App\Models\GateTransaction;
use App\Notifications\PermanentJobFailureNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/*
 * Job yang menyerah (JobFailed) wajib meninggalkan log CRITICAL dan — bila TAS_ALERT_MAIL diisi —
 * email yang dibatasi per jenis job. Tanpa ini kegagalan permanen (TOS tak menerima gate event,
 * broadcast mati) hanya jadi satu baris ERROR yang tak bisa dibedakan dari percobaan ulang.
 */

beforeEach(function (): void {
    Log::spy();
    config(['tas.alerts.mail_to' => 'ops@tas.test', 'tas.alerts.throttle_minutes' => 15]);
});

function failGateEventJobOnce(): void
{
    $appointment = Appointment::factory()->create();
    GateTransaction::factory()->create(['appointment_id' => $appointment->id]);

    $tos = Mockery::mock(GateEventGateway::class);
    $tos->shouldReceive('push')->andThrow(new RuntimeException('TOS tidak merespons'));
    app()->instance(GateEventGateway::class, $tos);

    expect(fn () => ProcessGateEventJob::dispatchSync($appointment->id, GateTransactionType::IN))
        ->toThrow(RuntimeException::class, 'TOS tidak merespons');
}

function assertCriticalJobFailureLogged(int $times): void
{
    Log::shouldHaveReceived('critical')
        ->times($times)
        ->withArgs(fn (string $message, array $context): bool => $context['job'] === ProcessGateEventJob::class
            && $context['exception'] === RuntimeException::class
            && $context['message'] === 'TOS tidak merespons');
}

it('logs critical and emails the alert address when a job fails permanently', function (): void {
    NotificationFacade::fake();

    failGateEventJobOnce();

    assertCriticalJobFailureLogged(1);
    NotificationFacade::assertSentOnDemand(
        PermanentJobFailureNotification::class,
        fn (PermanentJobFailureNotification $n, array $channels, AnonymousNotifiable $to): bool => $to->routes['mail'] === 'ops@tas.test'
            && $n->jobName === ProcessGateEventJob::class
            && $n->exceptionMessage === 'TOS tidak merespons',
    );
});

it('emails once per job type within the throttle window but logs every failure', function (): void {
    NotificationFacade::fake();

    failGateEventJobOnce();
    failGateEventJobOnce();

    assertCriticalJobFailureLogged(2);
    NotificationFacade::assertSentOnDemandTimes(PermanentJobFailureNotification::class, 1);
});

it('only logs when no alert address is configured', function (): void {
    config(['tas.alerts.mail_to' => null]);
    NotificationFacade::fake();

    failGateEventJobOnce();

    assertCriticalJobFailureLogged(1);
    NotificationFacade::assertNothingSent();
});

it('keeps the original job error when sending the alert itself fails', function (): void {
    app(ChannelManager::class)->extend('mail', fn () => new class
    {
        public function send(object $notifiable, Notification $notification): void
        {
            throw new RuntimeException('SMTP mati');
        }
    });

    failGateEventJobOnce();

    assertCriticalJobFailureLogged(1);
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['job'] === ProcessGateEventJob::class
            && $context['alert_error'] === 'SMTP mati');
});
