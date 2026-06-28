<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\FleetRepositoryInterface;
use App\Http\Requests\V1\FleetRequest;
use App\Http\Resources\V1\DriverResource;
use App\Http\Resources\V1\TruckResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class MyFleetController
{
    public function __construct(private readonly FleetRepositoryInterface $fleet) {}

    public function __invoke(FleetRequest $request): JsonResponse
    {
        // Otorisasi: fleet.manage (FormRequest::authorize).
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        // Transporter tanpa company tak punya armada (data tak konsisten) → 403.
        $companyId = $user->company_id;
        abort_if($companyId === null, Response::HTTP_FORBIDDEN);

        return response()->json([
            'data' => [
                'trucks' => TruckResource::collection($this->fleet->trucksForCompany($companyId)),
                'drivers' => DriverResource::collection($this->fleet->driversForCompany($companyId)),
            ],
        ]);
    }
}
