<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TruckStatus;
use App\Models\TransportCompany;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Truck> */
class TruckFactory extends Factory
{
    protected $model = Truck::class;

    public function definition(): array
    {
        return [
            'company_id' => TransportCompany::factory(),
            'plate_no' => 'B '.fake()->unique()->numberBetween(1000, 9999).' '.fake()->bothify('??'),
            'status' => TruckStatus::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => TruckStatus::INACTIVE]);
    }
}
