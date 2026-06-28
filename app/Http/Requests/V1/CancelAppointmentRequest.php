<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi per-record ditegakkan middleware can:cancel,appointment di route.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        // `version` opsional: bila dikirim → optimistic lock ditegakkan (anti edit
        // konkuren transporter vs planner); bila tidak → cancel tetap jalan.
        return [
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function expectedVersion(): ?int
    {
        return $this->filled('version') ? $this->integer('version') : null;
    }
}
