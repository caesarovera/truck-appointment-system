<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\GateRepositoryInterface;
use App\Models\Gate;

final class DeleteGateAction
{
    public function __construct(private readonly GateRepositoryInterface $gates) {}

    public function execute(Gate $gate): void
    {
        $this->gates->delete($gate);
    }
}
