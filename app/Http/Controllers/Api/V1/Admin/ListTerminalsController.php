<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\TerminalRepositoryInterface;
use App\Http\Requests\V1\Admin\ListTerminalsRequest;
use App\Http\Resources\V1\TerminalResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListTerminalsController
{
    public function __construct(private readonly TerminalRepositoryInterface $terminals) {}

    public function __invoke(ListTerminalsRequest $request): AnonymousResourceCollection
    {
        return TerminalResource::collection($this->terminals->all());
    }
}
