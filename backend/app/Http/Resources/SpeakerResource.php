<?php

namespace App\Http\Resources;

use App\Models\Speaker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Speaker */
class SpeakerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'affiliation' => $this->affiliation,
            'bio' => $this->bio,
            'photo_url' => $this->photoUrl(),
            'photo_alt' => $this->photo_alt,
        ];
    }
}
