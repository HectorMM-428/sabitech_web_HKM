<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Mail\ReservationConfirmedMail;
use App\Models\EmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía el correo de confirmación de una reserva y registra su estado real (R14).
 *
 * Idempotente: si la notificación ya está `sent`, no hace nada. Así un reintento o
 * un trabajo duplicado en la cola no envía el correo dos veces ni toca la reserva.
 */
class SendReservationConfirmation implements ShouldQueue
{
    use Queueable;

    /** Intentos automáticos; después solo se reintenta a mano desde el panel (A26). */
    public const MAX_AUTOMATIC_ATTEMPTS = 5;

    /** Espera antes de cada reintento automático (segundos): 1, 5, 15 y 15 minutos. */
    public const BACKOFF = [60, 300, 900, 900];

    /** Si un envío lleva más de esto en `sending`, se considera interrumpido. */
    public const SENDING_TIMEOUT_MINUTES = 10;

    public function __construct(public readonly int $notificationId) {}

    public function handle(): void
    {
        $notification = $this->claim();
        if (! $notification) {
            return;
        }

        try {
            Mail::to($notification->recipient)->send(new ReservationConfirmedMail($notification->reservation));
        } catch (Throwable $e) {
            $this->markFailed($notification, $e);

            return;
        }

        $notification->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Reserva la notificación para este proceso con la fila bloqueada: dos trabajos
     * a la vez no pueden enviar el mismo correo.
     */
    private function claim(): ?EmailNotification
    {
        return DB::transaction(function () {
            $notification = EmailNotification::query()
                ->whereKey($this->notificationId)
                ->lockForUpdate()
                ->first();

            if (! $notification || $notification->status === NotificationStatus::Sent) {
                return null;
            }

            $sendingRecently = $notification->status === NotificationStatus::Sending
                && $notification->last_attempt_at?->greaterThan(now()->subMinutes(self::SENDING_TIMEOUT_MINUTES));
            if ($sendingRecently) {
                return null;
            }

            $notification->update([
                'status' => NotificationStatus::Sending,
                'attempts' => $notification->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            return $notification->load(['reservation.user', 'reservation.talk.speaker', 'reservation.talk.room']);
        });
    }

    private function markFailed(EmailNotification $notification, Throwable $e): void
    {
        $notification->update([
            'status' => NotificationStatus::Failed,
            'last_error' => self::sanitize($e),
        ]);

        // Al log va la causa, nunca credenciales ni la configuración del servidor de correo.
        Log::warning('No se pudo enviar el correo de confirmación', [
            'notification_id' => $notification->id,
            'reservation_id' => $notification->reservation_id,
            'attempt' => $notification->attempts,
            'error' => self::sanitize($e),
        ]);

        if ($notification->attempts < self::MAX_AUTOMATIC_ATTEMPTS) {
            $delay = self::BACKOFF[min($notification->attempts - 1, count(self::BACKOFF) - 1)];
            self::dispatch($notification->id)->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Mensaje de error apto para guardarse y mostrarse en el panel: sin usuario ni
     * contraseña de URLs, sin cadenas que parezcan claves y con longitud limitada.
     */
    public static function sanitize(Throwable $e): string
    {
        $message = class_basename($e).': '.$e->getMessage();
        $message = preg_replace('#//[^/\s:@]+:[^@\s]+@#', '//***:***@', $message) ?? $message;
        $message = preg_replace('/(password|passwd|pwd|secret|token|key)\s*[=:]\s*\S+/i', '$1=***', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}
