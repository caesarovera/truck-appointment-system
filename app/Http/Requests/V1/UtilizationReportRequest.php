<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UtilizationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Laporan utilisasi gate = agregat lintas-company → hanya planner/admin
        // (BUSINESS-FLOW §3.7). Laporan company-scoped transporter terpisah.
        return (bool) $this->user()?->hasAnyRole(['admin', 'planner']);
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
