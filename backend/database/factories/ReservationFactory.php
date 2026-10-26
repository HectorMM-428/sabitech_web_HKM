<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Talk;
use App\Models\User;
use App\Support\ReservationReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => ReservationReference::generate(),
            'user_id' => User::factory(),
            'talk_id' => Talk::factory(),
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'cancelled_at' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
