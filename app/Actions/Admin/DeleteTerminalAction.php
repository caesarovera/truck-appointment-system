<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\TerminalRepositoryInterface;
use App\Models\Terminal;

final class DeleteTerminalAction
{
    public function __construct(private readonly TerminalRepositoryInterface $terminals) {}

    public function execute(Terminal $terminal): void
    {
        $this->terminals->delete($terminal);
    }
}
