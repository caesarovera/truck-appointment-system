<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('user.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', 'in:admin,planner,gate-officer,transporter,driver'],
        ];
    }

    public function role(): ?string
    {
        return $this->filled('role') ? $this->string('role')->toString() : null;
    }
}
