<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\TalkStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Talk;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Altas, cambios y estados de las charlas (A18–A20).
 *
 * El aforo se comprueba con la fila de la charla bloqueada (FOR UPDATE), igual
 * que al reservar: así una reserva y una reducción de aforo simultáneas no
 * pueden dejar más reservas confirmadas que plazas (RN-12).
 */
class TalkAdminService
{
    /** @param array{title: string, description: string, speaker_id: int, room_id: int, starts_at: string, ends_at: string, capacity: int} $data */
    public function create(array $data): Talk
    {
        $attributes = $this->normalize($data);

        if ($attributes['starts_at']->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['starts_at' => 'La charla debe empezar en el futuro.']);
        }

        return DB::transaction(function () use ($attributes) {
            $this->assertRoomIsFree($attributes);

            return Talk::create($attributes + ['status' => TalkStatus::Draft]);
        });
    }

    /** @param array{title: string, description: string, speaker_id: int, room_id: int, starts_at: string, ends_at: string, capacity: int} $data */
    public function update(Talk $talk, array $data): Talk
    {
        $attributes = $this->normalize($data);

        return DB::transaction(function () use ($talk, $attributes) {
            /** @var Talk $locked */
            $locked = Talk::query()->whereKey($talk->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TalkStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'Una charla cancelada no se puede editar.']);
            }
            if (! $locked->starts_at->equalTo($attributes['starts_at']) && $attributes['starts_at']->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['starts_at' => 'La nueva hora de inicio debe ser futura.']);
            }

            $confirmed = $locked->reservations()->where('status', ReservationStatus::Confirmed->value)->count();
            if ($attributes['capacity'] < $confirmed) {
                throw new BusinessRuleException(
                    'AFORO_BELOW_CONFIRMED',
                    "El aforo no puede ser menor que las {$confirmed} reservas confirmadas.",
                );
            }

            $this->assertRoomIsFree($attributes, $locked->id);
            $locked->update($attributes);

            return $locked;
        });
    }

    /**
     * draft → published · published → draft (sin reservas) · draft|published → cancelled.
     * Al cancelar se cancelan sus reservas confirmadas, para liberar a los asistentes
     * (la regla de solapes deja de afectarles).
     */
    public function changeStatus(Talk $talk, TalkStatus $to): Talk
    {
        return DB::transaction(function () use ($talk, $to) {
            /** @var Talk $locked */
            $locked = Talk::query()->whereKey($talk->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }
            if ($from === TalkStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'Una charla cancelada no se puede reactivar.']);
            }
            if ($locked->starts_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['status' => 'La charla ya ha comenzado: su estado no se puede cambiar.']);
            }

            $confirmed = $locked->reservations()->where('status', ReservationStatus::Confirmed->value);

            if ($to === TalkStatus::Draft && $confirmed->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Tiene reservas confirmadas: no se puede despublicar. Si no se va a celebrar, cancélala.',
                ]);
            }

            if ($to === TalkStatus::Cancelled) {
                $confirmed->update([
                    'status' => ReservationStatus::Cancelled->value,
                    'cancelled_at' => now(),
                ]);
            }

            $locked->update(['status' => $to]);

            return $locked;
        });
    }

    /**
     * @param  array{title: string, description: string, speaker_id: int, room_id: int, starts_at: string, ends_at: string, capacity: int}  $data
     * @return array{title: string, description: string, speaker_id: int, room_id: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, capacity: int}
     */
    private function normalize(array $data): array
    {
        return [
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'speaker_id' => (int) $data['speaker_id'],
            'room_id' => (int) $data['room_id'],
            'starts_at' => CarbonImmutable::parse($data['starts_at'])->utc(),
            'ends_at' => CarbonImmutable::parse($data['ends_at'])->utc(),
            'capacity' => (int) $data['capacity'],
        ];
    }

    /**
     * Dos charlas no canceladas no pueden coincidir en la misma sala.
     *
     * @param  array{room_id: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $attributes
     */
    private function assertRoomIsFree(array $attributes, ?int $ignoreId = null): void
    {
        $clash = Talk::query()
            ->where('room_id', $attributes['room_id'])
            ->where('status', '!=', TalkStatus::Cancelled->value)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $attributes['ends_at'])
            ->where('ends_at', '>', $attributes['starts_at'])
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'room_id' => "La sala ya está ocupada a esa hora por «{$clash->title}».",
            ]);
        }
    }
}
