<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\CompanyRepositoryInterface;
use App\DataTransferObjects\Admin\CompanyData;
use App\Exceptions\EntityInUseException;
use App\Models\TransportCompany;
use Illuminate\Support\Collection;

final class CompanyRepository implements CompanyRepositoryInterface
{
    /** @return Collection<int, TransportCompany> */
    public function all(): Collection
    {
        return TransportCompany::withCount(['users', 'trucks'])->orderBy('code')->get();
    }

    public function find(int $id): TransportCompany
    {
        return TransportCompany::withCount(['users', 'trucks'])->findOrFail($id);
    }

    public function create(CompanyData $data): TransportCompany
    {
        return TransportCompany::create(['code' => $data->code, 'name' => $data->name]);
    }

    public function update(TransportCompany $company, CompanyData $data): TransportCompany
    {
        $company->update(['code' => $data->code, 'name' => $data->name]);

        return $company->fresh() ?? $company;
    }

    public function delete(TransportCompany $company): void
    {
        if ($company->users()->exists() || $company->appointments()->exists()) {
            throw EntityInUseException::company();
        }

        $company->delete();
    }
}
