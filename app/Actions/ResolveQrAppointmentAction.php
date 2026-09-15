<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AppointmentRepositoryInterface;
use App\Exceptions\InvalidQrTokenException;
use App\Models\Appointment;
use App\Services\AppointmentQrTokenService;

final class ResolveQrAppointmentAction
{
    public function __construct(
        private readonly AppointmentQrTokenService $tokens,
        private readonly AppointmentRepositoryInterface $appointments,
    ) {}

    /** @throws InvalidQrTokenException */
    public function execute(string $token): Appointment
    {
        return $this->appointments->findForQr($this->tokens->verify($token));
    }
}
