<?php

namespace App\Listeners;

use App\Events\ReservationConfirmed;
use App\Jobs\SendReservationConfirmation;
use App\Models\EmailNotification;

/** Al confirmarse una reserva, pone en cola el envío de su correo. */
class QueueReservationConfirmation
{
    public function handle(ReservationConfirmed $event): void
    {
        $notification = EmailNotification::query()
            ->where('reservation_id', $event->reservation->id)
            ->where('type', EmailNotification::TYPE_RESERVATION_CONFIRMED)
            ->first();

        if ($notification) {
            SendReservationConfirmation::dispatch($notification->id);
        }
    }
}
