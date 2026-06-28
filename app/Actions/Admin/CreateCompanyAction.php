<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\CompanyRepositoryInterface;
use App\DataTransferObjects\Admin\CompanyData;
use App\Models\TransportCompany;

final class CreateCompanyAction
{
    public function __construct(private readonly CompanyRepositoryInterface $companies) {}

    public function execute(CompanyData $data): TransportCompany
    {
        return $this->companies->create($data);
    }
}
