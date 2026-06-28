<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Collection;

interface FleetRepositoryInterface
{
    /**
     * Truk milik satu company (referensi form booking).
     *
     * @return Collection<int, Truck>
     */
    public function trucksForCompany(int $companyId): Collection;

    /**
     * Sopir (user ber-role `driver`) milik satu company.
     *
     * @return Collection<int, User>
     */
    public function driversForCompany(int $companyId): Collection;
}
