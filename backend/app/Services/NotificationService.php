<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendReservationConfirmation;
use App\Models\EmailNotification;
use Illuminate\Support\Facades\DB;

/**
 * Reintento controlado del correo (R15) y limpieza de envíos interrumpidos.
 * Reintentar nunca crea otra reserva ni otra notificación: reutiliza la misma fila.
 */
class NotificationService
{
    public function retry(EmailNotification $notification): EmailNotification
    {
        $locked = DB::transaction(function () use ($notification) {
            $locked = EmailNotification::query()->whereKey($notification->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === NotificationStatus::Sent) {
                throw new BusinessRuleException('ALREADY_SENT', 'El correo ya se envió correctamente.');
            }

            $inProgress = $locked->status === NotificationStatus::Sending
                && $locked->last_attempt_at?->greaterThan(now()->subMinutes(SendReservationConfirmation::SENDING_TIMEOUT_MINUTES));
            if ($inProgress) {
                throw new BusinessRuleException('ALREADY_SENT', 'El correo se está enviando en este momento.');
            }

            $locked->update(['status' => NotificationStatus::Pending]);

            return $locked;
        });

        SendReservationConfirmation::dispatch($locked->id);

        return $locked->fresh();
    }

    /** Envíos que quedaron en `sending` porque el proceso se cayó: pasan a `failed` para poder reintentarlos. */
    public function releaseStuck(): int
    {
        return EmailNotification::query()
            ->where('status', NotificationStatus::Sending->value)
            ->where('last_attempt_at', '<', now()->subMinutes(SendReservationConfirmation::SENDING_TIMEOUT_MINUTES))
            ->update([
                'status' => NotificationStatus::Failed->value,
                'last_error' => 'Envío interrumpido: el proceso terminó antes de confirmar el resultado.',
                'updated_at' => now(),
            ]);
    }
}
