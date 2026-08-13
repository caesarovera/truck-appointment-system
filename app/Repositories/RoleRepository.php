<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleRepository implements RoleRepositoryInterface
{
    private const GUARD = 'api';

    /** @return Collection<int, Role> */
    public function all(): Collection
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    /** @return list<string> */
    public function allPermissionNames(): array
    {
        $names = [];
        foreach (Permission::query()->where('guard_name', self::GUARD)->orderBy('name')->pluck('name') as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }

    public function find(string $name): Role
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->where('name', $name)
            ->firstOrFail();
    }

    /** @param list<string> $permissionNames */
    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        $role->syncPermissions($permissionNames);

        return $role->fresh('permissions') ?? $role;
    }
}
