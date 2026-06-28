<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\UpdateCompanyAction;
use App\Http\Requests\V1\Admin\UpsertCompanyRequest;
use App\Http\Resources\V1\CompanyResource;
use App\Models\TransportCompany;

final class UpdateCompanyController
{
    public function __invoke(UpsertCompanyRequest $request, TransportCompany $transportCompany, UpdateCompanyAction $action): CompanyResource
    {
        return CompanyResource::make($action->execute($transportCompany, $request->toData()));
    }
}
