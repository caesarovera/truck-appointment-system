<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\OpenSlotWindowData;
use App\Models\SlotWindow;
use Illuminate\Support\Collection;

interface SlotRepositoryInterface
{
    /** Buat slot window baru ber-status OPEN & booked_count 0 (dipakai OpenSlotWindowAction). */
    public function create(OpenSlotWindowData $data): SlotWindow;

    /** Tutup window: status → CLOSED (bukan delete). Appointment existing tetap valid. */
    public function markClosed(SlotWindow $window): void;

    /**
     * Ambil slot window dengan row lock (FOR UPDATE) — WAJIB dipanggil di dalam
     * DB::transaction. Inilah pertahanan utama anti over-booking saat dua
     * transporter berebut slot terakhir.
     */
    public function lockForUpdate(int $slotWindowId): ?SlotWindow;

    /** Naikkan booked_count satu langkah (dipakai di transaksi yang sama dengan booking). */
    public function incrementBooked(SlotWindow $window): void;

    /** Turunkan booked_count satu langkah (cancel / no-show — kembalikan kuota). */
    public function decrementBooked(SlotWindow $window): void;

    /**
     * Ketersediaan slot untuk satu gate + tanggal, di-cache anti-stampede
     * (Cache::flexible) karena endpoint ini di-poll banyak transporter.
     *
     * @return Collection<int, SlotWindow>
     */
    public function cachedAvailability(int $gateId, string $date): Collection;

    /** Invalidasi cache ketersediaan untuk satu gate + tanggal (dipanggil saat booking/cancel). */
    public function forgetAvailability(int $gateId, string $date): void;

    /**
     * Utilisasi satu gate + tanggal: tiap window membawa hitungan appointment per
     * status (completed/no_show/cancelled/active) via withCount (BUSINESS-FLOW §3.7).
     *
     * $companyId null = agregat lintas-company (planner/admin). Diisi = hitungan
     * disaring ke satu company (laporan transporter /me — angka company lain
     * tidak boleh bocor); capacity/booked_count tetap milik window (global).
     *
     * @return Collection<int, SlotWindow>
     */
    public function utilization(int $gateId, string $date, ?int $companyId = null): Collection;
}
