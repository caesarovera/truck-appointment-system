<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\GateRepositoryInterface;
use App\Http\Requests\V1\ListGatesRequest;
use App\Http\Resources\V1\GateResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListGatesController
{
    public function __construct(private readonly GateRepositoryInterface $gates) {}

    public function __invoke(ListGatesRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: slot.read (FormRequest::authorize).
        return GateResource::collection($this->gates->all($request->terminalId()));
    }
}
