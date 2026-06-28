<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TerminalRepositoryInterface;
use App\DataTransferObjects\Admin\TerminalData;
use App\Exceptions\EntityInUseException;
use App\Models\Terminal;
use Illuminate\Support\Collection;

final class TerminalRepository implements TerminalRepositoryInterface
{
    /** @return Collection<int, Terminal> */
    public function all(): Collection
    {
        return Terminal::withCount('gates')->orderBy('code')->get();
    }

    public function find(int $id): Terminal
    {
        return Terminal::withCount('gates')->findOrFail($id);
    }

    public function create(TerminalData $data): Terminal
    {
        return Terminal::create(['code' => $data->code, 'name' => $data->name]);
    }

    public function update(Terminal $terminal, TerminalData $data): Terminal
    {
        $terminal->update(['code' => $data->code, 'name' => $data->name]);

        return $terminal->fresh() ?? $terminal;
    }

    public function delete(Terminal $terminal): void
    {
        if ($terminal->gates()->exists()) {
            throw EntityInUseException::terminal();
        }

        $terminal->delete();
    }
}
