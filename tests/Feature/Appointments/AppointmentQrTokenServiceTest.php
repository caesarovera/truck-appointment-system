<?php

declare(strict_types=1);

use App\Exceptions\InvalidQrTokenException;
use App\Models\Appointment;
use App\Models\Gate;
use App\Models\SlotWindow;
use App\Services\AppointmentQrTokenService;
use Illuminate\Support\Carbon;

function makeAppointmentForQr(): Appointment
{
    // ->ongoing(): window mulai 1 jam lalu, berakhir 1 jam lagi (lihat
    // SlotWindowFactory) — jam absolut hari ini bikin flaky tergantung jam
    // suite dijalankan.
    $window = SlotWindow::factory()->for(Gate::factory()->create())->ongoing()->create();

    return Appointment::factory()->create(['slot_window_id' => $window->id]);
}

beforeEach(function (): void {
    $this->service = app(AppointmentQrTokenService::class);
});

it('round-trips: generate then verify returns the same appointment id', function (): void {
    $appointment = makeAppointmentForQr();
    $appointment->load('slotWindow');

    $token = $this->service->generate($appointment);

    expect($this->service->verify($token))->toBe($appointment->id);
});

it('rejects a token whose signature was tampered with', function (): void {
    $appointment = makeAppointmentForQr();
    $appointment->load('slotWindow');

    $token = $this->service->generate($appointment);
    [$payload] = explode('.', $token, 2);
    $tampered = $payload.'.'.str_repeat('0', 64);

    expect(fn () => $this->service->verify($tampered))
        ->toThrow(InvalidQrTokenException::class);
});

it('rejects a token whose payload was tampered with (id swapped)', function (): void {
    $a = makeAppointmentForQr();
    $a->load('slotWindow');
    $b = makeAppointmentForQr();

    $tokenForA = $this->service->generate($a);
    [, $signature] = explode('.', $tokenForA, 2);

    $forgedPayload = rtrim(strtr(base64_encode((string) $b->id.'|'.now()->addHour()->getTimestamp()), '+/', '-_'), '=');
    $forged = "{$forgedPayload}.{$signature}";

    expect(fn () => $this->service->verify($forged))
        ->toThrow(InvalidQrTokenException::class);
});

it('rejects an expired token', function (): void {
    $appointment = makeAppointmentForQr();
    $appointment->load('slotWindow');

    $token = $this->service->generate($appointment);

    // ->ongoing() berakhir ~1 jam dari sekarang; toleransi gate-in default
    // 30 menit setelah itu. Lompat 2 jam ke depan sudah pasti melewati TTL.
    Carbon::setTestNow(now()->addHours(2));

    expect(fn () => $this->service->verify($token))
        ->toThrow(InvalidQrTokenException::class);

    Carbon::setTestNow();
});

it('rejects a malformed token string', function (): void {
    expect(fn () => $this->service->verify('not-a-real-token'))
        ->toThrow(InvalidQrTokenException::class);

    expect(fn () => $this->service->verify('###.###'))
        ->toThrow(InvalidQrTokenException::class);
});
