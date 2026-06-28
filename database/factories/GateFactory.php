<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gate;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Gate> */
class GateFactory extends Factory
{
    protected $model = Gate::class;

    public function definition(): array
    {
        $code = 'GATE-'.Str::upper(fake()->unique()->bothify('?'));

        return [
            'terminal_id' => Terminal::factory(),
            'code' => $code,
            'name' => $code,
        ];
    }
}
