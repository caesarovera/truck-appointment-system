<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\UpdateRolePermissionsAction;
use App\Contracts\RoleRepositoryInterface;
use App\Http\Requests\V1\Admin\UpdateRolePermissionsRequest;
use App\Http\Resources\V1\RoleResource;

final class UpdateRolePermissionsController
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    // $role diambil string (bukan route-model-binding) — Role bukan Eloquent
    // model biasa (Spatie), dan proyek ini sudah pernah kejebak jebakan PHPStan
    // di route-binding lain (lihat CODE-WALKTHROUGH §V) — resolve manual lebih aman.
    public function __invoke(UpdateRolePermissionsRequest $request, string $role, UpdateRolePermissionsAction $action): RoleResource
    {
        $model = $this->roles->find($role);

        return RoleResource::make($action->execute($model, $request->permissionNames()));
    }
}
