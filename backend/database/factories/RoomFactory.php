<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Sala '.fake()->unique()->bothify('?##'),
            'location_details' => 'Planta '.fake()->numberBetween(0, 3),
        ];
    }
}
