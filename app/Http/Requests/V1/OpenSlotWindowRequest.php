<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\DataTransferObjects\OpenSlotWindowData;
use Illuminate\Foundation\Http\FormRequest;

final class OpenSlotWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya planner/admin (slot.manage) yang boleh kelola window (BUSINESS-FLOW §1).
        return (bool) $this->user()?->can('slot.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'gate' => ['required', 'integer', 'exists:gates,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after:start_time'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function toData(): OpenSlotWindowData
    {
        return new OpenSlotWindowData(
            gateId: $this->integer('gate'),
            date: $this->string('date')->toString(),
            startTime: $this->string('start_time')->toString(),
            endTime: $this->string('end_time')->toString(),
            capacity: $this->integer('capacity'),
        );
    }
}
