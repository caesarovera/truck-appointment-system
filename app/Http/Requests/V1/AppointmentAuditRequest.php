<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AppointmentAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        // LAPIS KEDUA di atas `can:view,appointment` di route — dan lapis inilah
        // yang benar-benar bekerja di sini. Gate-officer (terminalnya cocok) dan
        // driver (appointment-nya sendiri) LOLOS AppointmentPolicy::view, tapi
        // matriks §1 tidak memberi mereka "Lihat audit log": trail memuat nama
        // orang yang mengubah, bukan cuma data appointment-nya.
        return (bool) $this->user()?->can('audit.read');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
