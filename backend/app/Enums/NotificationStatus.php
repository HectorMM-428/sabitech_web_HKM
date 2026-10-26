<?php

namespace App\Enums;

/** Ciclo de vida del correo de confirmación (diseño, sección 4). */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
