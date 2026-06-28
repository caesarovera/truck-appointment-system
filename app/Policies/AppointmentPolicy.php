<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Isolasi data per role (docs/BUSINESS-FLOW.md §1):
 *  - admin       : semua (lewat before()).
 *  - planner     : semua (monitor lintas-company).
 *  - gate-officer: hanya appointment di terminal yang ditugaskan padanya.
 *  - transporter : hanya appointment milik company-nya.
 *  - driver      : hanya appointment yang di-assign ke dirinya.
 */
final class AppointmentPolicy
{
    /** Admin lolos semua kemampuan tanpa cek lebih lanjut. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return match (true) {
            $user->hasRole('planner') => true,
            $user->hasRole('gate-officer') => $this->atOfficerTerminal($user, $appointment),
            $user->hasRole('transporter') => $appointment->company_id === $user->company_id,
            $user->hasRole('driver') => $appointment->driver_id === $user->id,
            default => false,
        };
    }

    /** Reschedule (self-service transporter atau override planner/admin). */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->canModify($user, $appointment);
    }

    /** Cancel (self-service transporter atau override planner/admin). */
    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->canModify($user, $appointment);
    }

    /** Gate-in / gate-out: hanya gate-officer di terminal appointment (admin via before). */
    public function process(User $user, Appointment $appointment): bool
    {
        return $user->hasRole('gate-officer') && $this->atOfficerTerminal($user, $appointment);
    }

    private function canModify(User $user, Appointment $appointment): bool
    {
        return match (true) {
            // planner & admin (admin via before): appointment.override, boleh lintas-company.
            $user->hasRole('planner') => true,
            // transporter: self-service hanya company sendiri.
            $user->hasRole('transporter') => $appointment->company_id === $user->company_id,
            default => false,
        };
    }

    private function atOfficerTerminal(User $user, Appointment $appointment): bool
    {
        if ($user->terminal_id === null) {
            return false;
        }

        // loadMissing (bukan akses lazy) supaya aman dari preventLazyLoading.
        $appointment->loadMissing('slotWindow.gate');

        return $appointment->slotWindow?->gate?->terminal_id === $user->terminal_id;
    }
}
