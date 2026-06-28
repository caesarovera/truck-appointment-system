<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ListAdminGatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('gate.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'terminal' => ['nullable', 'integer', 'exists:terminals,id'],
        ];
    }

    public function terminalId(): ?int
    {
        return $this->filled('terminal') ? $this->integer('terminal') : null;
    }
}
