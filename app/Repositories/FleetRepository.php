<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\FleetRepositoryInterface;
use App\DataTransferObjects\Fleet\TruckData;
use App\Enums\TruckStatus;
use App\Exceptions\EntityInUseException;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Collection;

final class FleetRepository implements FleetRepositoryInterface
{
    public function trucksForCompany(int $companyId, ?TruckStatus $status = null): Collection
    {
        return Truck::query()
            ->where('company_id', $companyId)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
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

    public function createTruck(int $companyId, TruckData $data): Truck
    {
        return Truck::create([
            'company_id' => $companyId,
            'plate_no' => $data->plateNo,
            'status' => $data->status,
        ]);
    }

    public function updateTruck(Truck $truck, TruckData $data): Truck
    {
        $truck->update(['plate_no' => $data->plateNo, 'status' => $data->status]);

        return $truck->fresh() ?? $truck;
    }

    public function deleteTruck(Truck $truck): void
    {
        // FK appointments.truck_id = RESTRICT (bukan cascade) → hard-delete truk
        // yang punya riwayat appointment akan melanggar FK & merusak audit trail.
        // Untuk "pensiunkan" truk berhistory: set status INACTIVE, jangan hapus.
        if ($truck->appointments()->exists()) {
            throw EntityInUseException::truck();
        }

        $truck->delete();
    }
}
