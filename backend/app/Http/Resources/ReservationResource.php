<?php

namespace App\Http\Resources;

use App\Enums\NotificationStatus;
use App\Enums\ReservationStatus;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema Reservation del contrato.
 *
 * - `status`: lo que está guardado (confirmed | cancelled).
 * - `display_status`: lo que se muestra; añade `attended` si existe la asistencia.
 *   Así nunca se confunde «tener plaza» con «haber asistido» (§4.4 del enunciado).
 * - `email_status`: estado real del correo de confirmación (R14).
 *
 * Requiere cargadas las relaciones: talk (con speaker, room y plazas), attendance y emailNotifications.
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $attendance = $this->attendance;
        $notification = $this->emailNotifications
            ->firstWhere('type', EmailNotification::TYPE_RESERVATION_CONFIRMED);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'display_status' => $this->status === ReservationStatus::Confirmed && $attendance ? 'attended' : $this->status->value,
            'confirmed_at' => ApiDate::format($this->confirmed_at),
            'cancelled_at' => ApiDate::format($this->cancelled_at),
            'attended_at' => ApiDate::format($attendance?->validated_at),
            'email_status' => ($notification?->status ?? NotificationStatus::Pending)->value,
            'talk' => new TalkResource($this->talk),
        ];
    }
}
