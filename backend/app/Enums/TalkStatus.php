<?php

namespace App\Enums;

/** Solo las charlas `published` aparecen en el programa y admiten reservas. */
enum TalkStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
}
