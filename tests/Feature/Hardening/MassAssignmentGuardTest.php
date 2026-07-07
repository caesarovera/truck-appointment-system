<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\SlotWindow;
use Illuminate\Database\Eloquent\MassAssignmentException;

/*
 * Kontrak CLAUDE.md §JANGAN + ADR-0004: status appointment, version, dan kuota
 * slot TIDAK boleh berubah lewat mass-assignment — hanya lewat Action ber-lock.
 * preventSilentlyDiscardingAttributes aktif di test → pelanggaran meledak keras.
 * (Factory & seeder tetap bebas: factory unguarded, seeder pakai forceFill.)
 */

it('rejects mass-assigning appointment status, version, and company_id', function (string $attribute, mixed $value): void {
    expect(fn (): Appointment => new Appointment([$attribute => $value]))
        ->toThrow(MassAssignmentException::class);
})->with([
    'status' => ['status', 'COMPLETED'],
    'version' => ['version', 99],
    'company_id' => ['company_id', 1],
]);

it('rejects mass-assigning slot window booked_count and status', function (string $attribute, mixed $value): void {
    expect(fn (): SlotWindow => new SlotWindow([$attribute => $value]))
        ->toThrow(MassAssignmentException::class);
})->with([
    'booked_count' => ['booked_count', 0],
    'status' => ['status', 'CLOSED'],
]);
