<?php

namespace App\Http\Resources\Admin;

use App\Models\Attendance;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Esquema Attendance del contrato (A24).
 *
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_reference' => $this->reservation->reference,
            'attendee_name' => $this->reservation->user->name,
            'talk_id' => $this->talk_id,
            'talk_title' => $this->talk->title,
            'method' => $this->method->value,
            'manual_reason' => $this->manual_reason,
            'validated_by' => $this->validator->name,
            'validated_at' => ApiDate::format($this->validated_at),
        ];
    }
}
