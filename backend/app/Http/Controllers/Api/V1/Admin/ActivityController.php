<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AttendanceMethod;
use App\Enums\NotificationStatus;
use App\Enums\ReservationStatus;
use App\Enums\ScanResult;
use App\Enums\TalkStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminReservationResource;
use App\Http\Resources\Admin\AttendanceResource;
use App\Models\Attendance;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Models\ScanAttempt;
use App\Models\Talk;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A23, A24 y A28 · Consultas del panel: reservas, asistencias e indicadores. */
class ActivityController extends Controller
{
    /** A23 · Reservas con filtros. `attended` = confirmada con asistencia registrada. */
    public function reservations(Request $request): JsonResponse
    {
        $v = $request->validate([
            'talk_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['confirmed', 'cancelled', 'attended'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Reservation::query()
            ->with(['user', 'talk', 'attendance', 'emailNotifications'])
            ->when($v['talk_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('talk_id', $id));

        match ($v['status'] ?? null) {
            'cancelled' => $query->where('status', ReservationStatus::Cancelled->value),
            'confirmed' => $query->where('status', ReservationStatus::Confirmed->value)->doesntHave('attendance'),
            'attended' => $query->where('status', ReservationStatus::Confirmed->value)->has('attendance'),
            default => null,
        };

        if (filled($v['q'] ?? null)) {
            $term = '%'.addcslashes(trim($v['q']), '\\%_').'%';
            $query->where(fn (Builder $q) => $q
                ->where('reference', 'like', $term)
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
        }

        $page = $query->orderByDesc('confirmed_at')->orderByDesc('id')->paginate(config('sabitech.per_page'));

        return Paginated::response($page, AdminReservationResource::class, $request);
    }

    /** A24 · Asistencias registradas, con quién validó y el motivo si fue manual. */
    public function attendances(Request $request): JsonResponse
    {
        $v = $request->validate([
            'talk_id' => ['sometimes', 'integer', 'min:1'],
            'method' => ['sometimes', Rule::enum(AttendanceMethod::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $page = Attendance::query()
            ->with(['reservation.user', 'talk', 'validator'])
            ->when($v['talk_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('talk_id', $id))
            ->when($v['method'] ?? null, fn (Builder $q, string $m) => $q->where('method', $m))
            ->orderByDesc('validated_at')
            ->orderByDesc('id')
            ->paginate(config('sabitech.per_page'));

        return Paginated::response($page, AttendanceResource::class, $request);
    }

    /** A28 · Indicadores del panel, calculados en el momento desde la base de datos. */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'talks' => Talk::query()->where('status', TalkStatus::Published->value)->count(),
                'reservations_confirmed' => Reservation::query()->where('status', ReservationStatus::Confirmed->value)->count(),
                'attendances' => Attendance::query()->count(),
                'notifications_failed' => EmailNotification::query()->where('status', NotificationStatus::Failed->value)->count(),
                'scans_rejected' => ScanAttempt::query()
                    ->whereNotIn('result', [ScanResult::Registered->value, ScanResult::Already->value])
                    ->count(),
            ],
        ]);
    }
}
