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
            'date' => now()->toDateString(),
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
