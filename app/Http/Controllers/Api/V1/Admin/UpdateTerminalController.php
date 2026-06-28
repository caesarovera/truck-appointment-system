<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\UpdateTerminalAction;
use App\Http\Requests\V1\Admin\UpsertTerminalRequest;
use App\Http\Resources\V1\TerminalResource;
use App\Models\Terminal;

final class UpdateTerminalController
{
    public function __invoke(UpsertTerminalRequest $request, Terminal $terminal, UpdateTerminalAction $action): TerminalResource
    {
        return TerminalResource::make($action->execute($terminal, $request->toData()));
    }
}
