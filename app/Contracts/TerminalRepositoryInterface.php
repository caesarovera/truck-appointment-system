<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Admin\TerminalData;
use App\Models\Terminal;
use Illuminate\Support\Collection;

interface TerminalRepositoryInterface
{
    /** @return Collection<int, Terminal> */
    public function all(): Collection;

    public function find(int $id): Terminal;

    public function create(TerminalData $data): Terminal;

    public function update(Terminal $terminal, TerminalData $data): Terminal;

    public function delete(Terminal $terminal): void;
}
