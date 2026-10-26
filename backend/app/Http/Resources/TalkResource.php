<?php

namespace App\Http\Resources;

use App\Models\Talk;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma de una charla en la API (esquemas TalkSummary y TalkDetail del contrato).
 *
 * @mixin Talk
 */
class TalkResource extends JsonResource
{
    private bool $withDescription = false;

    public function detailed(): static
    {
        $this->withDescription = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'speaker' => new SpeakerResource($this->whenLoaded('speaker')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'starts_at' => ApiDate::format($this->starts_at),
            'ends_at' => ApiDate::format($this->ends_at),
            'capacity' => $this->capacity,
            'available_seats' => $this->availableSeats(),
            'status' => $this->status->value,
        ];

        if ($this->withDescription) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
