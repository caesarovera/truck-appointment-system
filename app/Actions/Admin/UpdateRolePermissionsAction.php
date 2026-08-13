<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\RoleRepositoryInterface;
use App\Exceptions\ImmutableRoleException;
use Spatie\Permission\Models\Role;

final class UpdateRolePermissionsAction
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    /** @param list<string> $permissionNames */
    public function execute(Role $role, array $permissionNames): Role
    {
        if ($role->name === 'admin') {
            throw ImmutableRoleException::admin();
        }

        return $this->roles->syncPermissions($role, $permissionNames);
    }
}
