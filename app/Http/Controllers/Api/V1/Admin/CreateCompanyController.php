<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CreateCompanyAction;
use App\Http\Requests\V1\Admin\UpsertCompanyRequest;
use App\Http\Resources\V1\CompanyResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateCompanyController
{
    public function __invoke(UpsertCompanyRequest $request, CreateCompanyAction $action): JsonResponse
    {
        $company = $action->execute($request->toData());

        return CompanyResource::make($company)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
