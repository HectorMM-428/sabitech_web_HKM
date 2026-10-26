<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Attendance;
use App\Models\Reservation;
use App\Models\Talk;
use App\Services\AttendanceService;
use App\Support\ApiDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personal de acceso (A13–A16). Solo valida asistencia: no tiene permisos de
 * administración (§6 del prompt). El admin también puede usar estas rutas.
 */
class AccessController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /** A13 · Charlas del día con sus contadores. */
    public function talks(Request $request): JsonResponse
    {
        $validated = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $zone = config('app.display_timezone');
        $day = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $zone)->startOfDay()
            : CarbonImmutable::now($zone)->startOfDay();

        $talks = Talk::query()
            ->published()
            ->where('starts_at', '>=', $day->utc())
            ->where('starts_at', '<', $day->addDay()->utc())
            ->with('room')
            ->withCount([
                'reservations as reservations_confirmed' => fn (Builder $q) => $q->where('status', ReservationStatus::Confirmed->value),
            ])
            ->orderBy('starts_at')
            ->get();

        $attendances = Attendance::query()
            ->whereIn('talk_id', $talks->pluck('id'))
            ->selectRaw('talk_id, COUNT(*) AS total')
            ->groupBy('talk_id')
            ->pluck('total', 'talk_id');

        return response()->json([
            'data' => $talks->map(fn (Talk $talk) => [
                'id' => $talk->id,
                'title' => $talk->title,
                'room' => (new RoomResource($talk->room))->resolve($request),
                'starts_at' => ApiDate::format($talk->starts_at),
                'ends_at' => ApiDate::format($talk->ends_at),
                'reservations_confirmed' => (int) $talk->getAttribute('reservations_confirmed'),
                'attendances' => (int) ($attendances[$talk->id] ?? 0),
            ])->all(),
        ]);
    }

    /** A14 · Validar un QR y registrar la asistencia. 201 si se registra; 200 si ya lo estaba. */
    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'talk_id' => ['required', 'integer', 'exists:talks,id'],
        ]);

        $result = $this->attendance->checkInWithToken($request->user(), $validated['token'], (int) $validated['talk_id']);

        return $this->checkInResponse($result);
    }

    /** A15 · Buscar reservas confirmadas de una charla por nombre o referencia. */
    public function search(Request $request, int $talk): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']], [
            'q.min' => 'Escribe al menos 2 caracteres.',
        ]);
        $talkModel = Talk::query()->findOrFail($talk);
        $term = '%'.addcslashes(trim($validated['q']), '\\%_').'%';

        $rows = Reservation::query()
            ->where('talk_id', $talkModel->id)
            ->confirmed()
            ->where(fn (Builder $q) => $q
                ->where('reference', 'like', $term)
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term)))
            ->with(['user', 'attendance'])
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Reservation $r) => [
                'reservation_id' => $r->id,
                'reference' => $r->reference,
                'attendee_name' => $r->user->name,
                'attended' => $r->attendance !== null,
            ])->all(),
        ]);
    }

    /** A16 · Registro manual con motivo obligatorio. */
    public function manual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Indica el motivo del registro manual.',
            'reason.min' => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $reservation = Reservation::query()->findOrFail($validated['reservation_id']);
        $result = $this->attendance->checkInManually($request->user(), $reservation, trim($validated['reason']));

        return $this->checkInResponse($result);
    }

    /** @param array{created: bool, attendance: Attendance} $result */
    private function checkInResponse(array $result): JsonResponse
    {
        $attendance = $result['attendance']->load(['reservation.user', 'reservation.talk']);

        return response()->json([
            'data' => [
                'result' => $result['created'] ? 'REGISTERED' : 'ALREADY_REGISTERED',
                'attendee_name' => $attendance->reservation->user->name,
                'reservation_reference' => $attendance->reservation->reference,
                'talk_title' => $attendance->reservation->talk->title,
                'validated_at' => ApiDate::format($attendance->validated_at),
                'method' => $attendance->method->value,
            ],
        ], $result['created'] ? 201 : 200);
    }
}
