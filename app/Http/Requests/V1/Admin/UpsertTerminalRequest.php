<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\DataTransferObjects\Admin\TerminalData;
use App\Models\Terminal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('terminal.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $model = $this->route('terminal');
        $terminalId = $model instanceof Terminal ? $model->id : null;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('terminals', 'code')->ignore($terminalId)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): TerminalData
    {
        return new TerminalData(
            code: $this->string('code')->toString(),
            name: $this->string('name')->toString(),
        );
    }
}
