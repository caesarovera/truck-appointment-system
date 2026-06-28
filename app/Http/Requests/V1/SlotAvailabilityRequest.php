<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SlotAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('slot.read');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'gate' => ['required', 'integer', 'exists:gates,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function gateId(): int
    {
        return $this->integer('gate');
    }

    public function requestedDate(): string
    {
        return $this->filled('date')
            ? $this->string('date')->toString()
            : now()->toDateString();
    }
}
