<?php

namespace App\Models;

use Database\Factories\SpeakerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'affiliation', 'bio', 'photo_path', 'photo_alt'])]
class Speaker extends Model
{
    /** @use HasFactory<SpeakerFactory> */
    use HasFactory;

    /** @return HasMany<Talk, $this> */
    public function talks(): HasMany
    {
        return $this->hasMany(Talk::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
