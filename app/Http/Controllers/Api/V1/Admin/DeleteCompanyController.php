<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\DeleteCompanyAction;
use App\Http\Requests\V1\Admin\AdminRequest;
use App\Models\TransportCompany;
use Illuminate\Http\Response;

final class DeleteCompanyController
{
    public function __invoke(AdminRequest $request, TransportCompany $transportCompany, DeleteCompanyAction $action): Response
    {
        $action->execute($transportCompany);

        return response()->noContent();
    }
}
