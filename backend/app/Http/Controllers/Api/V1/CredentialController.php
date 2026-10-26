<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\AttendanceService;
use App\Support\ApiDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A12 · Token QR vigente de una reserva propia. */
class CredentialController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function __invoke(Request $request, int $reservation): JsonResponse
    {
        // Solo reservas propias: una ajena responde 404, como si no existiera.
        $own = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($reservation);

        $credential = $this->attendance->credential($own);

        return response()
            ->json([
                'data' => [
                    'token' => $credential['token'],
                    'issued_at' => ApiDate::format($credential['issued_at']),
                    'expires_at' => ApiDate::format($credential['expires_at']),
                    // El cliente calcula la cuenta atrás con la hora del servidor, no con la suya.
                    'server_time' => ApiDate::format(now()),
                ],
            ])
            ->header('Cache-Control', 'no-store');
    }
}
