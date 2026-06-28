<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Base request shared by Show/Delete admin endpoints. */
class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('user.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
