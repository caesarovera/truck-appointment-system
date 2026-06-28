<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\DataTransferObjects\Admin\UserData;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('user.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,planner,gate-officer,transporter,driver'],
            'terminal_id' => ['nullable', 'integer', 'exists:terminals,id'],
            'company_id' => ['nullable', 'integer', 'exists:transport_companies,id'],
        ];
    }

    public function toData(): UserData
    {
        return new UserData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            role: $this->string('role')->toString(),
            password: $this->string('password')->toString(),
            terminalId: $this->filled('terminal_id') ? $this->integer('terminal_id') : null,
            companyId: $this->filled('company_id') ? $this->integer('company_id') : null,
        );
    }
}
