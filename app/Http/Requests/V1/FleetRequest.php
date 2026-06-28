<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class FleetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Armada sendiri → hanya transporter (punya fleet.manage).
        return (bool) $this->user()?->can('fleet.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
