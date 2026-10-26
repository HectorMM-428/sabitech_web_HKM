<?php

namespace App\Enums;

/**
 * Estado guardado de una reserva. «Asistida» no es un estado: se calcula a partir
 * de que exista una asistencia, para no confundir tener plaza con haber acudido.
 */
enum ReservationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
