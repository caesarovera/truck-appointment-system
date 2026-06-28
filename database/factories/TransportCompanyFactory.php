<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TransportCompany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TransportCompany> */
class TransportCompanyFactory extends Factory
{
    protected $model = TransportCompany::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('????')),
            'name' => 'PT '.fake()->company(),
        ];
    }
}
