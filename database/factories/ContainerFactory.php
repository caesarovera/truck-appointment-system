<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Container> */
class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'slot_window_id' => null,
            'container_no' => Str::upper(fake()->unique()->bothify('????#######')),
            'iso_type' => '22G1',
            'size' => 20,
        ];
    }
}
