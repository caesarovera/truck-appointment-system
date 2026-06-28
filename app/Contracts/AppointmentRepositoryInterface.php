<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\BookAppointmentData;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface
{
    /**
     * Buat appointment baru ber-status CONFIRMED beserta container-nya.
     * Dipanggil di dalam transaksi booking (lihat BookAppointmentAction).
     */
    public function createConfirmed(BookAppointmentData $data, int $companyId, string $bookingCode): Appointment;

    /**
     * Tandai CANCELLED & lepas container dari window (slot_window_id → NULL)
     * supaya kontainer bisa dibooking lagi. Dipakai di transaksi cancel.
     */
    public function markCancelled(Appointment $appointment): void;

    /**
     * Pindahkan appointment ke window lain: set slot_window_id, naikkan version
     * (optimistic lock), dan pindahkan container ke window baru. Dipakai di
     * transaksi reschedule.
     */
    public function moveToWindow(Appointment $appointment, int $toWindowId): void;

    /**
     * Catat gate-in: buat gate_transactions (type=IN) & dorong status
     * CONFIRMED → ARRIVED → IN_PROGRESS (MVP, BUSINESS-FLOW §3.5). Dipakai di
     * transaksi gate-in ber-lock.
     */
    public function recordGateIn(Appointment $appointment, int $processedBy): void;

    /**
     * Catat gate-out: buat gate_transactions (type=OUT) & status
     * IN_PROGRESS → COMPLETED. Dipakai di transaksi gate-out ber-lock.
     */
    public function recordGateOut(Appointment $appointment, int $processedBy): void;

    /**
     * Tandai NO_SHOW & lepas container dari window (slot_window_id → NULL).
     * Dipakai di transaksi NoShowSweepJob (kuota dikembalikan terpisah).
     */
    public function markNoShow(Appointment $appointment): void;

    /**
     * Appointment yang sudah melewati window.end + grace tapi masih menahan kuota
     * (BOOKED/CONFIRMED) — kandidat no-show. slotWindow ikut di-eager-load.
     *
     * @return Collection<int, Appointment>
     */
    public function dueForNoShow(CarbonInterface $now, int $graceMinutes): Collection;

    /**
     * Appointment milik 1 sopir yang window-nya jatuh pada $date (jadwal hari-H
     * driver, BUSINESS-FLOW §3.4). Relasi tampilan di-eager-load (anti N+1).
     *
     * @return Collection<int, Appointment>
     */
    public function todayForDriver(int $driverId, string $date): Collection;

    /**
     * Semua appointment milik 1 company (daftar "Booking Saya" transporter),
     * opsional disaring status. Relasi tampilan di-eager-load (anti N+1).
     *
     * @return Collection<int, Appointment>
     */
    public function forCompany(int $companyId, ?string $status): Collection;
}
