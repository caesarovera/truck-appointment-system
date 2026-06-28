<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\TerminalRepositoryInterface;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Http\Resources\V1\TerminalResource;
use App\Models\Terminal;

final class ShowTerminalController
{
    public function __construct(private readonly TerminalRepositoryInterface $terminals) {}

    public function __invoke(AdminRequest $request, Terminal $terminal): TerminalResource
    {
        return TerminalResource::make($this->terminals->find($terminal->id));
    }
}
