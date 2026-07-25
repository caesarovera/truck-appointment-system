<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Fleet;

use App\Models\Truck;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteTruckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $truck = $this->route('truck');

        return $user !== null
            && $user->can('fleet.manage')
            && $user->company_id !== null
            && $truck instanceof Truck
            && $truck->company_id === $user->company_id;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
