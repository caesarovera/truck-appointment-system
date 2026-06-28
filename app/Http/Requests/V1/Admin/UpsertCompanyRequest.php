<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\DataTransferObjects\Admin\CompanyData;
use App\Models\TransportCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('company.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $model = $this->route('transportCompany');
        $companyId = $model instanceof TransportCompany ? $model->id : null;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('transport_companies', 'code')->ignore($companyId)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): CompanyData
    {
        return new CompanyData(
            code: $this->string('code')->toString(),
            name: $this->string('name')->toString(),
        );
    }
}
