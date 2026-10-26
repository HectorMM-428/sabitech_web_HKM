<?php

namespace Database\Factories;

use App\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Speaker>
 */
class SpeakerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'affiliation' => fake()->company(),
            'bio' => fake()->paragraph(),
            'photo_path' => null,
            'photo_alt' => null,
        ];
    }
}
