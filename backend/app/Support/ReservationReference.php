<?php

namespace App\Support;

/**
 * Referencias de reserva legibles: «SBT-» + 6 caracteres aleatorios.
 *
 * El alfabeto excluye los caracteres que se confunden al leerlos en voz alta o en
 * pantalla (0/O, 1/I/L), porque el personal de acceso puede buscar una reserva por
 * su referencia. 31^6 ≈ 887 millones de combinaciones; la columna es única y el
 * servicio de reservas reintenta si hubiera colisión.
 */
final class ReservationReference
{
    public const PREFIX = 'SBT-';

    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    public const PATTERN = '/^SBT-[A-HJKMNP-Z2-9]{6}$/';

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return self::PREFIX.$code;
    }
}
