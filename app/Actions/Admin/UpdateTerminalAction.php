<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\TerminalRepositoryInterface;
use App\DataTransferObjects\Admin\TerminalData;
use App\Models\Terminal;

final class UpdateTerminalAction
{
    public function __construct(private readonly TerminalRepositoryInterface $terminals) {}

    public function execute(Terminal $terminal, TerminalData $data): Terminal
    {
        return $this->terminals->update($terminal, $data);
    }
}
