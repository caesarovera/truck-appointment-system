<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Contracts\FleetRepositoryInterface;
use App\Http\Requests\V1\FleetRequest;
use App\Http\Resources\V1\TruckResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ListTrucksController
{
    public function __construct(private readonly FleetRepositoryInterface $fleet) {}

    public function __invoke(FleetRequest $request): AnonymousResourceCollection
    {
        // Otorisasi fleet.manage di FleetRequest; scope company dari user login.
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        $companyId = $user->company_id;
        abort_if($companyId === null, Response::HTTP_FORBIDDEN);

        return TruckResource::collection($this->fleet->trucksForCompany($companyId));
    }
}
