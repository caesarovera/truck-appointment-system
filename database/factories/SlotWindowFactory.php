<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SlotWindowStatus;
use App\Models\Gate;
use App\Models\SlotWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SlotWindow> */
class SlotWindowFactory extends Factory
{
    protected $model = SlotWindow::class;

    public function definition(): array
    {
        $hour = fake()->numberBetween(6, 17);

        return [
            'gate_id' => Gate::factory(),
            // Besok, bukan hari ini: default factory harus "valid untuk di-book".
            // Dengan date=hari-ini, window berjam-acak bisa sudah berakhir saat test
            // jalan sore/malam → guard hasEnded() bikin test flaky. Test yang butuh
            // hari-ini/masa-lalu set 'date' eksplisit.
            'date' => now()->addDay()->toDateString(),
            'start_time' => sprintf('%02d:00:00', $hour),
            'end_time' => sprintf('%02d:00:00', $hour + 1),
            'capacity' => 5,
            'booked_count' => 0,
            'status' => SlotWindowStatus::OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => SlotWindowStatus::CLOSED]);
    }

    /**
     * Window yang SEDANG berjalan (mulai 1 jam lalu, berakhir 1 jam lagi).
     *
     * Default factory sengaja "besok" supaya valid untuk di-book, tapi besok =
     * terlalu awal untuk gate-in (GateInAction menegakkan toleransi jam, lihat
     * BUSINESS-FLOW §3.5). Test gate-in memakai state ini sebagai "valid by default".
     * Jam dijepit di dalam hari yang sama karena start_time/end_time tak membawa
     * tanggal — tanpa jepitan itu test yang jalan dekat tengah malam jadi flaky.
     */
    public function ongoing(): static
    {
        return $this->state(function (): array {
            $start = now()->subHour();
            $end = now()->addHour();

            return [
                'date' => today()->toDateString(),
                'start_time' => $start->isSameDay(today()) ? $start->format('H:i:s') : '00:00:00',
                'end_time' => $end->isSameDay(today()) ? $end->format('H:i:s') : '23:59:59',
            ];
        });
    }

    /** Window with exactly one slot left — for race-condition tests. */
    public function nearlyFull(): static
    {
        return $this->state(fn (array $attrs): array => [
            'booked_count' => ($attrs['capacity'] ?? 5) - 1,
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attrs): array => [
            'booked_count' => $attrs['capacity'] ?? 5,
        ]);
    }
}
