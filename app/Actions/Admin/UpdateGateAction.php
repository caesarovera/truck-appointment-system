<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\GateRepositoryInterface;
use App\DataTransferObjects\Admin\GateData;
use App\Models\Gate;

final class UpdateGateAction
{
    public function __construct(private readonly GateRepositoryInterface $gates) {}

    public function execute(Gate $gate, GateData $data): Gate
    {
        return $this->gates->update($gate, $data);
    }
}
