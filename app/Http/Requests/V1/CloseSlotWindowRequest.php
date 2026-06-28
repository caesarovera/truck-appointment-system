<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class CloseSlotWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('slot.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
