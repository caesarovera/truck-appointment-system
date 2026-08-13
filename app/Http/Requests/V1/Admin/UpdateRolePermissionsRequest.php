<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('role.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        /** @var list<string> $names */
        $names = $this->input('permissions', []);

        return $names;
    }
}
