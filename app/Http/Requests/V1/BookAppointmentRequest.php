<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\DataTransferObjects\BookAppointmentData;
use App\Enums\MoveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya transporter (punya appointment.write). Isolasi company ditegakkan
        // lewat rule exists ber-scope di bawah + cek ulang di Action.
        return (bool) $this->user()?->can('appointment.write');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'slot_window_id' => ['required', 'integer', 'exists:slot_windows,id'],
            'truck_id' => ['required', 'integer', Rule::exists('trucks', 'id')->where('company_id', $companyId)],
            'driver_id' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'move_type' => ['required', Rule::enum(MoveType::class)],
            'container_no' => ['required', 'string', 'max:20'],
            'iso_type' => ['nullable', 'string', 'max:10'],
            'size' => ['nullable', 'integer', Rule::in([20, 40])],
        ];
    }

    public function toData(): BookAppointmentData
    {
        return new BookAppointmentData(
            slotWindowId: $this->integer('slot_window_id'),
            truckId: $this->integer('truck_id'),
            driverId: $this->integer('driver_id'),
            moveType: MoveType::from($this->string('move_type')->toString()),
            containerNo: $this->string('container_no')->toString(),
            isoType: $this->filled('iso_type') ? $this->string('iso_type')->toString() : null,
            size: $this->filled('size') ? $this->integer('size') : null,
        );
    }
}
