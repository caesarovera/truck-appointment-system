<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\RoleRepositoryInterface;
use App\Http\Requests\V1\Admin\ListRolesRequest;
use App\Http\Resources\V1\RoleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListRolesController
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    public function __invoke(ListRolesRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: role.manage (FormRequest::authorize).
        return RoleResource::collection($this->roles->all())->additional([
            'meta' => ['all_permissions' => $this->roles->allPermissionNames()],
        ]);
    }
}
