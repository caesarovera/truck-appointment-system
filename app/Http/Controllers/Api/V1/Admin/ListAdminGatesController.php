<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\GateRepositoryInterface;
use App\Http\Requests\V1\Admin\ListAdminGatesRequest;
use App\Http\Resources\V1\GateResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminGatesController
{
    public function __construct(private readonly GateRepositoryInterface $gates) {}

    public function __invoke(ListAdminGatesRequest $request): AnonymousResourceCollection
    {
        return GateResource::collection(
            $this->gates->allForAdmin($request->terminalId())
        );
    }
}
