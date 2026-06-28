<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\UpdateGateAction;
use App\Http\Requests\V1\Admin\UpsertGateRequest;
use App\Http\Resources\V1\GateResource;
use App\Models\Gate;

final class UpdateGateController
{
    public function __invoke(UpsertGateRequest $request, Gate $gate, UpdateGateAction $action): GateResource
    {
        return GateResource::make($action->execute($gate, $request->toData()));
    }
}
