<?php

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Enums\ReservationStatus;
use App\Enums\ScanResult;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Reservation;
use App\Models\ScanAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Acreditación y registro de asistencia (R16–R20, RN-08 a RN-11).
 *
 * La asistencia es única por persona y charla: lo garantiza el índice único de
 * `attendances`. Un segundo escaneo devuelve la asistencia existente sin crear otra.
 */
class AttendanceService
{
    /** La acreditación se abre 60 minutos antes de la charla (K-06). */
    public const WINDOW_OPENS_MINUTES_BEFORE = 60;

    public function __construct(private readonly QrTokenService $tokens) {}

    /**
     * A12 · Token vigente para la reserva propia.
     *
     * @return array{token: string, issued_at: mixed, expires_at: mixed}
     */
    public function credential(Reservation $reservation): array
    {
        $reservation->loadMissing(['talk', 'attendance']);

        if ($reservation->status !== ReservationStatus::Confirmed) {
            throw new BusinessRuleException('NOT_ACTIVE', 'La reserva no está confirmada.');
        }
        if ($reservation->attendance) {
            throw new BusinessRuleException('ATTENDED', 'Tu asistencia ya está registrada.');
        }

        $opensAt = $reservation->talk->starts_at->subMinutes(self::WINDOW_OPENS_MINUTES_BEFORE);
        if (now()->lessThan($opensAt) || now()->greaterThanOrEqualTo($reservation->talk->ends_at)) {
            throw new BusinessRuleException(
                'OUTSIDE_WINDOW',
                'La acreditación está disponible desde 60 minutos antes del inicio de la charla hasta su final.',
            );
        }

        return $this->tokens->issue($reservation);
    }

    /**
     * A14 · Validación del QR por el personal de acceso, en el orden del diseño (sección 5).
     *
     * @return array{created: bool, attendance: Attendance}
     */
    public function checkInWithToken(User $staff, string $token, int $talkId): array
    {
        $reservationId = null;

        try {
            $claims = $this->tokens->verify($token);               // pasos 2–4: firma, emisión, caducidad
            $reservationId = $claims['rid'];

            if ($claims['tid'] !== $talkId) {                      // paso 5: la charla del escáner
                throw new BusinessRuleException('WRONG_TALK', 'El código corresponde a otra charla.', 422);
            }

            $result = $this->register($staff, $claims['rid'], $talkId, AttendanceMethod::Qr, null);   // pasos 6–7
        } catch (BusinessRuleException $e) {
            $this->log($staff, $talkId, $reservationId, $e->errorCode);
            throw $e;
        }

        $this->log($staff, $talkId, $reservationId, $result['created'] ? 'REGISTERED' : 'ALREADY');

        return $result;
    }

    /**
     * A16 · Registro manual cuando la persona no puede mostrar el QR (R20).
     *
     * @return array{created: bool, attendance: Attendance}
     */
    public function checkInManually(User $staff, Reservation $reservation, string $reason): array
    {
        return $this->register($staff, $reservation->id, $reservation->talk_id, AttendanceMethod::Manual, $reason);
    }

    /**
     * @return array{created: bool, attendance: Attendance}
     */
    private function register(User $staff, int $reservationId, int $talkId, AttendanceMethod $method, ?string $reason): array
    {
        try {
            return DB::transaction(function () use ($staff, $reservationId, $talkId, $method, $reason) {
                $reservation = Reservation::query()->whereKey($reservationId)->lockForUpdate()->first();

                if (! $reservation || $reservation->talk_id !== $talkId || $reservation->status !== ReservationStatus::Confirmed) {
                    throw new BusinessRuleException('NOT_ACTIVE', 'La reserva no está confirmada.', 422);
                }

                $existing = Attendance::query()
                    ->where('user_id', $reservation->user_id)
                    ->where('talk_id', $reservation->talk_id)
                    ->first();
                if ($existing) {
                    return ['created' => false, 'attendance' => $existing];
                }

                $attendance = Attendance::create([
                    'reservation_id' => $reservation->id,
                    'user_id' => $reservation->user_id,
                    'talk_id' => $reservation->talk_id,
                    'validated_by' => $staff->id,
                    'method' => $method,
                    'manual_reason' => $reason,
                    'validated_at' => now(),
                ]);

                return ['created' => true, 'attendance' => $attendance];
            });
        } catch (QueryException $e) {
            // Dos escaneos simultáneos del mismo código: el índice único deja pasar solo uno.
            if (($e->errorInfo[1] ?? null) === 1062) {
                $existing = Attendance::query()->where('reservation_id', $reservationId)->firstOrFail();

                return ['created' => false, 'attendance' => $existing];
            }
            throw $e;
        }
    }

    private function log(User $staff, int $talkId, ?int $reservationId, string $code): void
    {
        $result = match ($code) {
            'REGISTERED' => ScanResult::Registered,
            'ALREADY' => ScanResult::Already,
            'EXPIRED' => ScanResult::Expired,
            'WRONG_TALK' => ScanResult::WrongTalk,
            'NOT_ACTIVE' => ScanResult::NotActive,
            default => ScanResult::Invalid,
        };

        ScanAttempt::create([
            'staff_id' => $staff->id,
            'talk_id' => $talkId,
            'reservation_id' => $reservationId !== null && Reservation::query()->whereKey($reservationId)->exists() ? $reservationId : null,
            'result' => $result,
        ]);
    }
}
