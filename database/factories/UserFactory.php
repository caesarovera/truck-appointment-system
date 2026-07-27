<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Sopir sungguhan: user ber-role `driver` (guard `api`).
     *
     * Dipakai di mana pun test butuh `driver_id` yang sah — BookAppointmentAction
     * menolak user yang cuma sekantor tapi bukan sopir. `findOrCreate` supaya state
     * ini jalan baik di test yang menjalankan RolePermissionSeeder maupun yang tidak.
     */
    public function driver(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(Role::findOrCreate('driver', 'api'));
        });
    }
}
