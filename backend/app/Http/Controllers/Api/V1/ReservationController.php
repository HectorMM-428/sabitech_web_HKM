<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reservas del asistente autenticado (A08–A11).
 *
 * Todas las consultas parten de `$request->user()->reservations()`: una reserva ajena
 * simplemente «no existe» para ti (404), sin revelar que existe (RT-03).
 */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    /** A08 · GET /api/v1/me/reservations */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['confirmed', 'cancelled'])],
        ]);

        $items = $this->ownReservations($request->user())
            ->when($validated['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => ReservationResource::collection($items)->resolve($request)]);
    }

    /** A09 · GET /api/v1/me/reservations/{id} */
    public function show(Request $request, int $reservation): JsonResponse
    {
        $model = $this->ownReservations($request->user())->findOrFail($reservation);

        return response()->json(['data' => (new ReservationResource($model))->resolve($request)]);
    }

    /** A10 · POST /api/v1/reservations */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $created = $this->reservations->reserve($request->user(), (int) $request->validated('talk_id'));
        $model = $this->ownReservations($request->user())->findOrFail($created->id);

        return response()->json(['data' => (new ReservationResource($model))->resolve($request)], 201);
    }

    /** A11 · POST /api/v1/reservations/{id}/cancel */
    public function cancel(Request $request, int $reservation): JsonResponse
    {
        $own = $this->ownReservations($request->user())->findOrFail($reservation);
        $this->reservations->cancel($own);

        $fresh = $this->ownReservations($request->user())->findOrFail($own->id);

        return response()->json(['data' => (new ReservationResource($fresh))->resolve($request)]);
    }

    /** @return Builder<Reservation> */
    private function ownReservations(User $user): Builder
    {
        return Reservation::query()
            ->where('user_id', $user->id)
            ->with([
                'talk' => fn ($q) => $q->withAvailableSeats()->with(['speaker', 'room']),
                'attendance',
                'emailNotifications',
            ]);
    }
}
