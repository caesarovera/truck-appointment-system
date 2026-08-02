<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

interface AuditRepositoryInterface
{
    /**
     * Jejak audit satu appointment, urut kronologis (lama → baru).
     *
     * @return Collection<int, Activity>
     */
    public function forAppointment(Appointment $appointment): Collection;
}
