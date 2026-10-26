<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Las fechas se guardan en UTC y la API las devuelve en ISO 8601 con el desfase
 * de la zona de las jornadas, p. ej. 2027-02-24T10:00:00+01:00 (RNF-04).
 */
final class ApiDate
{
    public static function format(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::instance($date)
            ->setTimezone(config('app.display_timezone'))
            ->toIso8601String();
    }
}
