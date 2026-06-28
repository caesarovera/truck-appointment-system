<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\CompanyRepositoryInterface;
use App\Models\TransportCompany;

final class DeleteCompanyAction
{
    public function __construct(private readonly CompanyRepositoryInterface $companies) {}

    public function execute(TransportCompany $company): void
    {
        $this->companies->delete($company);
    }
}
