<?php

declare(strict_types=1);

namespace App\Actions\Fleet;

use App\Contracts\FleetRepositoryInterface;
use App\DataTransferObjects\Fleet\TruckData;
use App\Models\Truck;

final class UpdateTruckAction
{
    public function __construct(private readonly FleetRepositoryInterface $fleet) {}

    public function execute(Truck $truck, TruckData $data): Truck
    {
        return $this->fleet->updateTruck($truck, $data);
    }
}
