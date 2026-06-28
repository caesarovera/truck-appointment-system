<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class TodayAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Scope driver: hanya appointment milik dirinya (BUSINESS-FLOW §1).
        return (bool) $this->user()?->can('appointment.read.self');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
