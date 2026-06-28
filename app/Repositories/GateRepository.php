<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\GateRepositoryInterface;
use App\DataTransferObjects\Admin\GateData;
use App\Exceptions\EntityInUseException;
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

    public function allForAdmin(?int $terminalId = null): Collection
    {
        return Gate::with('terminal')
            ->when($terminalId !== null, fn ($q) => $q->where('terminal_id', $terminalId))
            ->orderBy('terminal_id')
            ->orderBy('code')
            ->get();
    }

    public function findForAdmin(int $id): Gate
    {
        return Gate::with('terminal')->findOrFail($id);
    }

    public function create(GateData $data): Gate
    {
        return Gate::create([
            'terminal_id' => $data->terminalId,
            'code' => $data->code,
            'name' => $data->name,
        ]);
    }

    public function update(Gate $gate, GateData $data): Gate
    {
        $gate->update([
            'terminal_id' => $data->terminalId,
            'code' => $data->code,
            'name' => $data->name,
        ]);

        return $gate->fresh(['terminal']) ?? $gate;
    }

    public function delete(Gate $gate): void
    {
        if ($gate->slotWindows()->exists()) {
            throw EntityInUseException::gate();
        }

        $gate->delete();
    }
}
