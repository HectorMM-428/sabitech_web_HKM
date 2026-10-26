<?php

namespace App\Enums;

/** Resultado de cada lectura de QR registrada en scan_attempts (R30). */
enum ScanResult: string
{
    case Registered = 'registered';
    case Already = 'already';
    case Expired = 'expired';
    case Invalid = 'invalid';
    case WrongTalk = 'wrong_talk';
    case NotActive = 'not_active';
}
