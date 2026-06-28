<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GateTransactionType;
use App\Models\Appointment;
use App\Models\GateTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GateTransaction> */
class GateTransactionFactory extends Factory
{
    protected $model = GateTransaction::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'type' => GateTransactionType::IN,
            'processed_by' => User::factory(),
            'processed_at' => now(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn (): array => ['type' => GateTransactionType::OUT]);
    }
}
