<?php

namespace App\Http\Resources;

use App\Models\EmailNotification;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema Notification del contrato (panel de organización).
 *
 * @mixin EmailNotification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_reference' => $this->reservation->reference,
            'recipient' => $this->recipient,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'last_attempt_at' => ApiDate::format($this->last_attempt_at),
            'sent_at' => ApiDate::format($this->sent_at),
        ];
    }
}
