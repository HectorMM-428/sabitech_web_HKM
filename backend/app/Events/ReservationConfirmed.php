<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una reserva acaba de confirmarse y ya está guardada. Quien escuche este evento
 * (el correo de confirmación) trabaja aparte: si falla, la reserva no se pierde.
 */
class ReservationConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
