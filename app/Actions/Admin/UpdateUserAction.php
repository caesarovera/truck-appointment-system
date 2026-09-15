<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Contracts\UserRepositoryInterface;
use App\DataTransferObjects\Admin\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class UpdateUserAction
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function execute(User $user, UserData $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $updated = $this->users->update($user, $data);

            // Password direset (mis. akun dicuri) → token lama tak boleh tetap hidup sampai kedaluwarsa.
            // Admin yang mengganti password sendiri tetap memakai token sesi ini.
            if ($data->changesPassword()) {
                $current = $actor->is($user) ? $actor->currentAccessToken() : null;
                $keepId = $current instanceof PersonalAccessToken ? (int) $current->getKey() : null;

                $this->users->revokeTokens($user, $keepId);
            }

            return $updated;
        });
    }
}
