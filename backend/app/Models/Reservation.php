<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * `active_key` es una columna generada por MySQL: no se asigna nunca desde PHP.
 */
#[Fillable(['reference', 'user_id', 'talk_id', 'status', 'confirmed_at', 'cancelled_at'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Talk, $this> */
    public function talk(): BelongsTo
    {
        return $this->belongsTo(Talk::class);
    }

    /** @return HasOne<Attendance, $this> */
    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    /** @return HasMany<EmailNotification, $this> */
    public function emailNotifications(): HasMany
    {
        return $this->hasMany(EmailNotification::class);
    }

    /** @param Builder<Reservation> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', ReservationStatus::Confirmed->value);
    }
}
