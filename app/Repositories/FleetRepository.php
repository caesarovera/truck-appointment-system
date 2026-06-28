<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\FleetRepositoryInterface;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Collection;

final class FleetRepository implements FleetRepositoryInterface
{
    public function trucksForCompany(int $companyId): Collection
    {
        return Truck::query()
            ->where('company_id', $companyId)
            ->orderBy('plate_no')
            ->get();
    }

    public function driversForCompany(int $companyId): Collection
    {
        // Sopir = user di company ber-role `driver` (guard api). Transporter/
        // dispatcher tidak ikut karena role-nya bukan driver.
        return User::query()
            ->where('company_id', $companyId)
            ->role('driver', 'api')
            ->orderBy('name')
            ->get();
    }
}
