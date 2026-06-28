<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ListCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('company.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
