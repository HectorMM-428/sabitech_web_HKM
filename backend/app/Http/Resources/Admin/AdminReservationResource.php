<?php

namespace App\Http\Resources\Admin;

use App\Enums\NotificationStatus;
use App\Enums\ReservationStatus;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema AdminReservationRow del contrato (A23).
 *
 * @mixin Reservation
 */
class AdminReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $notification = $this->emailNotifications->firstWhere('type', EmailNotification::TYPE_RESERVATION_CONFIRMED);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'attendee' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'talk_id' => $this->talk_id,
            'talk_title' => $this->talk->title,
            'display_status' => $this->status === ReservationStatus::Confirmed && $this->attendance ? 'attended' : $this->status->value,
            'email_status' => ($notification?->status ?? NotificationStatus::Pending)->value,
            'confirmed_at' => ApiDate::format($this->confirmed_at),
            'cancelled_at' => ApiDate::format($this->cancelled_at),
        ];
    }
}
