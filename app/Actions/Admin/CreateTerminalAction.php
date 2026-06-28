<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\TerminalRepositoryInterface;
use App\DataTransferObjects\Admin\TerminalData;
use App\Models\Terminal;

final class CreateTerminalAction
{
    public function __construct(private readonly TerminalRepositoryInterface $terminals) {}

    public function execute(TerminalData $data): Terminal
    {
        return $this->terminals->create($data);
    }
}
