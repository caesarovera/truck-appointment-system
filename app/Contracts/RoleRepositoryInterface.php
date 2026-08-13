<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * Semua role beserta permission-nya (Role & Izin, admin). Urut nama.
     *
     * @return Collection<int, Role>
     */
    public function all(): Collection;

    /** @return list<string> Semua nama permission yang ada — universe checkbox di FE. */
    public function allPermissionNames(): array;

    public function find(string $name): Role;

    /**
     * Ganti seluruh permission role dengan $permissionNames (replace, bukan tambah).
     *
     * @param  list<string>  $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): Role;
}
