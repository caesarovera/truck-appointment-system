<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class GateHistoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Riwayat gate-in/out = agregat lintas-company → hanya planner/admin,
        // sama seperti UtilizationReportRequest (BUSINESS-FLOW §3.7). Riwayat
        // company-scoped transporter cukup lewat GET /me/appointments (sudah
        // eager-load gateIn/gateOut, tak butuh endpoint terpisah).
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
