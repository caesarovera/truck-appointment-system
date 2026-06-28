<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ListTerminalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('terminal.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
