<?php

namespace Database\Factories;

use App\Enums\TalkStatus;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\Talk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Talk>
 */
class TalkFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->addDays(fake()->numberBetween(2, 20))->setTime(9, 0);

        return [
            'title' => rtrim(fake()->sentence(5), '.'),
            'description' => fake()->paragraphs(2, true),
            'speaker_id' => Speaker::factory(),
            'room_id' => Room::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(45),
            'capacity' => 40,
            'status' => TalkStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => TalkStatus::Draft]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => TalkStatus::Cancelled]);
    }

    /** Charla en una hora concreta de Madrid, como la introduciría la organización. */
    public function at(string $madridDateTime, int $minutes = 45): static
    {
        $start = CarbonImmutable::parse($madridDateTime, 'Europe/Madrid')->utc();

        return $this->state(fn () => ['starts_at' => $start, 'ends_at' => $start->addMinutes($minutes)]);
    }
}
