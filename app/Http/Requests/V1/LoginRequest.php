<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\DataTransferObjects\Auth\LoginData;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email', 'ascii'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toData(): LoginData
    {
        return new LoginData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            deviceName: $this->filled('device_name') ? $this->string('device_name')->toString() : 'api',
        );
    }
}
