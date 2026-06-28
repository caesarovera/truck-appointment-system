<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\DataTransferObjects\Admin\UserData;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

final class UserRepository implements UserRepositoryInterface
{
    /** @return Collection<int, User> */
    public function all(?string $role = null): Collection
    {
        $query = User::with(['roles', 'terminal', 'company'])->orderBy('name');

        if ($role !== null) {
            $query->role($role, 'api');
        }

        return $query->get();
    }

    public function find(int $id): User
    {
        return User::with(['roles', 'terminal', 'company'])->findOrFail($id);
    }

    public function create(UserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make((string) $data->password),
            'terminal_id' => $data->terminalId,
            'company_id' => $data->companyId,
        ]);

        $user->syncRoles([$data->role]);

        return $user->load(['roles', 'terminal', 'company']);
    }

    public function update(User $user, UserData $data): User
    {
        $fields = [
            'name' => $data->name,
            'email' => $data->email,
            'terminal_id' => $data->terminalId,
            'company_id' => $data->companyId,
        ];

        if ($data->password !== null && $data->password !== '') {
            $fields['password'] = Hash::make($data->password);
        }

        $user->update($fields);
        $user->syncRoles([$data->role]);

        return $user->fresh(['roles', 'terminal', 'company']) ?? $user;
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            abort(422, 'Tidak dapat menghapus akun sendiri.');
        }

        $user->tokens()->delete();
        $user->delete();
    }
}
