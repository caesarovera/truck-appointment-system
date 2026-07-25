<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Fleet\TruckData;
use App\Enums\TruckStatus;
use App\Exceptions\EntityInUseException;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Collection;

interface FleetRepositoryInterface
{
    /**
     * Truk milik satu company. `$status` null = semua (halaman kelola armada);
     * `TruckStatus::ACTIVE` = hanya yang siap dijadwalkan (form booking).
     *
     * @return Collection<int, Truck>
     */
    public function trucksForCompany(int $companyId, ?TruckStatus $status = null): Collection;

    /**
     * Sopir (user ber-role `driver`) milik satu company.
     *
     * @return Collection<int, User>
     */
    public function driversForCompany(int $companyId): Collection;

    public function createTruck(int $companyId, TruckData $data): Truck;

    public function updateTruck(Truck $truck, TruckData $data): Truck;

    /** @throws EntityInUseException bila truk masih dipakai appointment. */
    public function deleteTruck(Truck $truck): void;
}
