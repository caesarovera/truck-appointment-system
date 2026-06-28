<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\MoveType;
use App\Models\Appointment;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $company = TransportCompany::factory();

        return [
            'company_id' => $company,
            'truck_id' => Truck::factory()->for($company, 'company'),
            'driver_id' => User::factory(),
            'slot_window_id' => SlotWindow::factory(),
            'move_type' => fake()->randomElement(MoveType::cases()),
            'status' => AppointmentStatus::BOOKED,
            'version' => 1,
            'booking_code' => 'TAS-'.Str::upper(Str::random(8)),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => AppointmentStatus::CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => AppointmentStatus::CANCELLED]);
    }

    public function noShow(): static
    {
        return $this->state(fn (): array => ['status' => AppointmentStatus::NO_SHOW]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => AppointmentStatus::COMPLETED]);
    }
}
