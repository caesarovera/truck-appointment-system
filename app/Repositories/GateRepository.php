<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\GateRepositoryInterface;
use App\Models\Gate;
use Illuminate\Support\Collection;

final class GateRepository implements GateRepositoryInterface
{
    public function all(?int $terminalId): Collection
    {
        return Gate::query()
            ->when($terminalId !== null, fn ($q) => $q->where('terminal_id', $terminalId))
            ->orderBy('terminal_id')
            ->orderBy('code')
            ->get();
    }
}
