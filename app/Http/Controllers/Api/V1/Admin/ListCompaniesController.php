<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\CompanyRepositoryInterface;
use App\Http\Requests\V1\Admin\ListCompaniesRequest;
use App\Http\Resources\V1\CompanyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCompaniesController
{
    public function __construct(private readonly CompanyRepositoryInterface $companies) {}

    public function __invoke(ListCompaniesRequest $request): AnonymousResourceCollection
    {
        return CompanyResource::collection($this->companies->all());
    }
}
