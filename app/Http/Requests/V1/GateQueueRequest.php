<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class GateQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Antrian gate → hanya petugas gate (punya gate.process).
        // Scope per-terminal ditegakkan di controller (butuh terminal_id).
        return (bool) $this->user()?->can('gate.process');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function requestedDate(): string
    {
        return $this->filled('date')
            ? $this->string('date')->toString()
            : now()->toDateString();
    }
}
