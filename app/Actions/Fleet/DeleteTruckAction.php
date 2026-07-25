<?php

declare(strict_types=1);

namespace App\Actions\Fleet;

use App\Contracts\FleetRepositoryInterface;
use App\Models\Truck;

final class DeleteTruckAction
{
    public function __construct(private readonly FleetRepositoryInterface $fleet) {}

    public function execute(Truck $truck): void
    {
        $this->fleet->deleteTruck($truck);
    }
}
