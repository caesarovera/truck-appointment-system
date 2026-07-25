<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Fleet;

use App\DataTransferObjects\Fleet\TruckData;
use App\Enums\TruckStatus;
use App\Models\Truck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpsertTruckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('fleet.manage') || $user->company_id === null) {
            return false;
        }

        // Update: truk terikat route wajib milik company user (cegah edit lintas company).
        $truck = $this->route('truck');
        if ($truck instanceof Truck) {
            return $truck->company_id === $user->company_id;
        }

        return true; // create
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $truck = $this->route('truck');
        $truckId = $truck instanceof Truck ? $truck->id : null;
        $companyId = $this->user()?->company_id;

        return [
            // Unik per company (bukan global): plat sama boleh ada di company lain.
            'plate_no' => [
                'required', 'string', 'max:20',
                Rule::unique('trucks', 'plate_no')->where('company_id', $companyId)->ignore($truckId),
            ],
            'status' => ['required', new Enum(TruckStatus::class)],
        ];
    }

    public function toData(): TruckData
    {
        return new TruckData(
            plateNo: $this->string('plate_no')->toString(),
            status: TruckStatus::from($this->string('status')->toString()),
        );
    }
}
