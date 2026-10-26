<?php

namespace App\Enums;

/** Perfiles de la aplicación (Fase 1, sección 6). El rol nunca llega desde el registro. */
enum UserRole: string
{
    case Attendee = 'attendee';
    case Staff = 'staff';
    case Admin = 'admin';
}
