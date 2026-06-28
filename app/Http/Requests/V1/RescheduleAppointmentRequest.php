<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\DataTransferObjects\RescheduleAppointmentData;
use Illuminate\Foundation\Http\FormRequest;

final class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi per-record ditegakkan middleware can:update,appointment di route.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'slot_window_id' => ['required', 'integer', 'exists:slot_windows,id'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toData(): RescheduleAppointmentData
    {
        return new RescheduleAppointmentData(
            slotWindowId: $this->integer('slot_window_id'),
            expectedVersion: $this->integer('version'),
        );
    }
}
