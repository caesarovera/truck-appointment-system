<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\DataTransferObjects\Admin\GateData;
use App\Models\Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertGateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('gate.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $model = $this->route('gate');
        $gateId = $model instanceof Gate ? $model->id : null;

        return [
            'terminal_id' => ['required', 'integer', 'exists:terminals,id'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('gates', 'code')
                    ->where('terminal_id', $this->integer('terminal_id'))
                    ->ignore($gateId),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): GateData
    {
        return new GateData(
            terminalId: $this->integer('terminal_id'),
            code: $this->string('code')->toString(),
            name: $this->string('name')->toString(),
        );
    }
}
