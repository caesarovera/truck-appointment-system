<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class MyUtilizationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Laporan utilisasi milik sendiri → cukup permission report.read
        // (transporter punya). Scope per-company ditegakkan di controller
        // (butuh company_id) — pola yang sama dengan /me/appointments.
        return (bool) $this->user()?->can('report.read');
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
