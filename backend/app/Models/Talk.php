<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\TalkStatus;
use Database\Factories\TalkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $confirmed_reservations_count Disponible tras withAvailableSeats().
 */
#[Fillable(['title', 'description', 'speaker_id', 'room_id', 'starts_at', 'ends_at', 'capacity', 'status'])]
class Talk extends Model
{
    /** @use HasFactory<TalkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'status' => TalkStatus::class,
        ];
    }

    /** @return BelongsTo<Speaker, $this> */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @param Builder<Talk> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', TalkStatus::Published->value);
    }

    /**
     * Cuenta las reservas confirmadas en la misma consulta (sin N+1).
     * Las plazas libres salen siempre de la base de datos, nunca de un contador editable (RN-02).
     *
     * @param  Builder<Talk>  $query
     */
    public function scopeWithAvailableSeats(Builder $query): void
    {
        $query->withCount([
            'reservations as confirmed_reservations_count' => fn (Builder $q) => $q->where('status', ReservationStatus::Confirmed->value),
        ]);
    }

    public function availableSeats(): int
    {
        // Si la consulta ya trajo el recuento (withAvailableSeats), se usa; si no, se calcula.
        $confirmed = array_key_exists('confirmed_reservations_count', $this->attributes)
            ? (int) $this->attributes['confirmed_reservations_count']
            : $this->reservations()->where('status', ReservationStatus::Confirmed->value)->count();

        return max(0, $this->capacity - $confirmed);
    }
}
