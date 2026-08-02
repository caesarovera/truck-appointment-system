<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\AuditRepositoryInterface;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

final class AuditRepository implements AuditRepositoryInterface
{
    /** @return Collection<int, Activity> */
    public function forAppointment(Appointment $appointment): Collection
    {
        /** @var Collection<int, Activity> $activities */
        $activities = Activity::query()
            // `with('causer')` wajib, bukan optimasi: Resource membaca nama causer
            // dan preventLazyLoading aktif di non-prod.
            ->with('causer')
            // Disaring ke log_name `appointment` supaya endpoint ini tak pernah
            // membocorkan log domain lain kalau kelak ada model lain yang diaudit.
            ->where('log_name', 'appointment')
            ->where('subject_type', $appointment->getMorphClass())
            ->where('subject_id', $appointment->getKey())
            ->orderBy('id')
            ->get();

        return $activities;
    }
}
