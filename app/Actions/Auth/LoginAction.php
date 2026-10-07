<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Contracts\UserRepositoryInterface;
use App\DataTransferObjects\Auth\IssuedToken;
use App\DataTransferObjects\Auth\LoginData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function execute(LoginData $data): IssuedToken
    {
        $user = $this->users->findByEmail($data->email);

        // Email tak terdaftar tetap membayar satu Hash::check — kalau dilewati, responsnya ±200×
        // lebih cepat dan email terdaftar bisa ditebak lewat waktu (throttle hanya memperlambat).
        $hash = $user === null ? $this->dummyHash() : $user->password;

        if (! Hash::check($data->password, $hash) || $user === null) {
            // Pesan generik (jangan bocorkan email mana yang terdaftar).
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Token abilities = permission milik role user (scope per role, BUSINESS-FLOW §1).
        $abilities = $user->getAllPermissions()->pluck('name')->all();

        return new IssuedToken(
            user: $user,
            plainTextToken: $user->createToken($data->deviceName, $abilities)->plainTextToken,
        );
    }

    /**
     * Hash tiruan ber-cost SAMA dengan hash user asli. Dibuat sekali lalu di-cache: membuatnya per
     * request menggandakan waktu jalur "tak terdaftar" sehingga justru jadi lebih LAMBAT (oracle
     * terbalik). Kunci memuat konfigurasi hashing → ganti rounds/driver otomatis membuat ulang.
     */
    private function dummyHash(): string
    {
        $key = 'auth:dummy-hash:'.md5((string) json_encode(config('hashing')));

        return (string) Cache::rememberForever($key, fn (): string => Hash::make(Str::random(32)));
    }
}
