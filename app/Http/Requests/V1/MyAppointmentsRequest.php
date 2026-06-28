<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MyAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Daftar booking sendiri → butuh appointment.read (transporter punya).
        // Scope per-company ditegakkan di controller (butuh company_id).
        return (bool) $this->user()?->can('appointment.read');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ];
    }

    public function statusFilter(): ?string
    {
        return $this->filled('status') ? $this->string('status')->toString() : null;
    }
}
