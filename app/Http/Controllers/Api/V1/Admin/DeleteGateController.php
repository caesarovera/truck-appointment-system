<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\DeleteGateAction;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Models\Gate;
use Illuminate\Http\Response;

final class DeleteGateController
{
    public function __invoke(AdminRequest $request, Gate $gate, DeleteGateAction $action): Response
    {
        $action->execute($gate);

        return response()->noContent();
    }
}
