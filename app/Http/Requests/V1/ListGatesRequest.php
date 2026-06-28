<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ListGatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gate = referensi untuk lihat/booking slot → butuh slot.read
        // (planner/gate-officer/transporter; driver tidak).
        return (bool) $this->user()?->can('slot.read');
    }

    /** @return array<string, array<int, mixed>> */
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
