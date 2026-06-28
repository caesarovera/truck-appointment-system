<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\DeleteTerminalAction;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Models\Terminal;
use Illuminate\Http\Response;

final class DeleteTerminalController
{
    public function __invoke(AdminRequest $request, Terminal $terminal, DeleteTerminalAction $action): Response
    {
        $action->execute($terminal);

        return response()->noContent();
    }
}
