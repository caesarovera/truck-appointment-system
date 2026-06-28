<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Admin\CompanyData;
use App\Models\TransportCompany;
use Illuminate\Support\Collection;

interface CompanyRepositoryInterface
{
    /** @return Collection<int, TransportCompany> */
    public function all(): Collection;

    public function find(int $id): TransportCompany;

    public function create(CompanyData $data): TransportCompany;

    public function update(TransportCompany $company, CompanyData $data): TransportCompany;

    public function delete(TransportCompany $company): void;
}
