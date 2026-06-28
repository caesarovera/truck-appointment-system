<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SlotRepositoryInterface;
use App\Http\Requests\V1\SlotAvailabilityRequest;
use App\Http\Resources\V1\SlotWindowResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SlotAvailabilityController
{
    public function __construct(private readonly SlotRepositoryInterface $slots) {}

    public function __invoke(SlotAvailabilityRequest $request): AnonymousResourceCollection
    {
        $windows = $this->slots->cachedAvailability($request->gateId(), $request->requestedDate());

        return SlotWindowResource::collection($windows);
    }
}
