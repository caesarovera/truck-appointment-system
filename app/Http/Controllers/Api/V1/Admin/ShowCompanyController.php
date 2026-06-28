<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\CompanyRepositoryInterface;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Http\Resources\V1\CompanyResource;
use App\Models\TransportCompany;

final class ShowCompanyController
{
    public function __construct(private readonly CompanyRepositoryInterface $companies) {}

    public function __invoke(AdminRequest $request, TransportCompany $transportCompany): CompanyResource
    {
        return CompanyResource::make($this->companies->find($transportCompany->id));
    }
}
