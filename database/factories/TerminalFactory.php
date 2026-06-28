<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Terminal> */
class TerminalFactory extends Factory
{
    protected $model = Terminal::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('???')),
            'name' => fake()->company().' Terminal',
        ];
    }
}
