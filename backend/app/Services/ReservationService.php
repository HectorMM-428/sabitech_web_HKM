<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\TalkStatus;
use App\Events\ReservationConfirmed;
use App\Exceptions\BusinessRuleException;
use App\Models\Reservation;
use App\Models\Talk;
use App\Models\User;
use App\Support\ReservationReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio de las reservas (RN-01 a RN-05). Es el único sitio donde se
 * crean o cancelan reservas.
 *
 * Concurrencia (AD-04): toda la comprobación y la inserción ocurren dentro de una
 * transacción que bloquea la fila de la charla con SELECT … FOR UPDATE. Si dos
 * personas piden la última plaza a la vez, la segunda espera a que la primera
 * termine y entonces ve la plaza ocupada. Comprobar «hay sitio» fuera de ese
 * bloqueo no sería suficiente: ambas leerían «1 plaza libre» antes de insertar.
 */
class ReservationService
{
    /** Reintentos de la transacción si MySQL detecta un interbloqueo. */
    private const DEADLOCK_ATTEMPTS = 3;

    public function reserve(User $user, int $talkId): Reservation
    {
        try {
            $reservation = DB::transaction(function () use ($user, $talkId) {
                // Orden fijo de bloqueos (persona y después charla) para evitar interbloqueos.
                // Bloquear a la persona impide que dos peticiones suyas simultáneas se salten
                // la regla de duplicados o la de solapamiento.
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $talk = Talk::query()->whereKey($talkId)->lockForUpdate()->first();

                $this->assertReservable($talk);
                $this->assertNoActiveReservation($user, $talk);
                $this->assertNoOverlap($user, $talk);
                $this->assertHasSeats($talk);

                return Reservation::create([
                    'reference' => $this->uniqueReference(),
                    'user_id' => $user->id,
                    'talk_id' => $talk->id,
                    'status' => ReservationStatus::Confirmed,
                    'confirmed_at' => now(),
                    'cancelled_at' => null,
                ]);
            }, self::DEADLOCK_ATTEMPTS);
        } catch (QueryException $e) {
            // Segunda barrera: el índice único de la base de datos (reserva activa duplicada).
            if ($this->isDuplicateKey($e)) {
                throw new BusinessRuleException('DUPLICATE', 'Ya tienes una reserva activa para esta charla.');
            }
            throw $e;
        }

        // Fuera de la transacción: la reserva ya está guardada pase lo que pase después.
        ReservationConfirmed::dispatch($reservation);

        return $reservation;
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            /** @var Reservation $locked */
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $talk = Talk::query()->findOrFail($locked->talk_id);

            if ($locked->status === ReservationStatus::Cancelled) {
                throw new BusinessRuleException('ALREADY_CANCELLED', 'La reserva ya estaba cancelada.');
            }
            if ($locked->attendance()->exists()) {
                throw new BusinessRuleException('ATTENDED', 'Tu asistencia ya está registrada; la reserva no se puede cancelar.');
            }
            if ($talk->starts_at->lessThanOrEqualTo(now())) {
                throw new BusinessRuleException('TALK_STARTED', 'La charla ya ha comenzado; la reserva no se puede cancelar.');
            }

            // Al pasar a `cancelled`, la plaza vuelve a contar como libre (RN-02, RN-04).
            $locked->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $locked;
        });
    }

    /** RN-01: existe, está publicada y no ha empezado. Un borrador responde igual que si no existiera. */
    private function assertReservable(?Talk $talk): void
    {
        if (! $talk || $talk->status !== TalkStatus::Published) {
            throw (new ModelNotFoundException)->setModel(Talk::class);
        }

        if ($talk->starts_at->lessThanOrEqualTo(now())) {
            throw new BusinessRuleException('TALK_STARTED', 'La charla ya ha comenzado.');
        }
    }

    /** RN-03: una sola reserva activa por persona y charla. */
    private function assertNoActiveReservation(User $user, Talk $talk): void
    {
        $exists = Reservation::query()
            ->where('user_id', $user->id)
            ->where('talk_id', $talk->id)
            ->confirmed()
            ->exists();

        if ($exists) {
            throw new BusinessRuleException('DUPLICATE', 'Ya tienes una reserva activa para esta charla.');
        }
    }

    /** RN-05: no se puede estar en dos charlas a la vez. */
    private function assertNoOverlap(User $user, Talk $talk): void
    {
        $overlapping = Reservation::query()
            ->where('user_id', $user->id)
            ->confirmed()
            ->whereHas('talk', fn ($q) => $q
                ->where('id', '!=', $talk->id)
                ->where('starts_at', '<', $talk->ends_at)
                ->where('ends_at', '>', $talk->starts_at))
            ->exists();

        if ($overlapping) {
            throw new BusinessRuleException('OVERLAP', 'Tienes otra reserva en el mismo horario.');
        }
    }

    /** Aforo: se cuenta con la fila de la charla bloqueada, así el recuento no puede quedar desfasado. */
    private function assertHasSeats(Talk $talk): void
    {
        $confirmed = $talk->reservations()->confirmed()->count();

        if ($confirmed >= $talk->capacity) {
            throw new BusinessRuleException('TALK_FULL', 'La charla no tiene plazas disponibles.');
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = ReservationReference::generate();
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
