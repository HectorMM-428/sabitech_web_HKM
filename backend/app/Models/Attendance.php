<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Asistencia registrada. Solo tiene created_at: una asistencia no se edita. */
#[Fillable(['reservation_id', 'user_id', 'talk_id', 'validated_by', 'method', 'manual_reason', 'validated_at'])]
class Attendance extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'method' => AttendanceMethod::class,
            'validated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
