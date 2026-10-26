<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\TalkController;
use Illuminate\Support\Facades\Route;

/*
 * API v1 (prefijo /api/v1, ver bootstrap/app.php). Cada ruta indica su operación del
 * contrato docs/api/openapi.yaml.
 *
 * Capas de protección, de fuera adentro:
 *   1. throttle  · límite de peticiones (429)
 *   2. auth      · sesión válida (401)
 *   3. role      · perfil autorizado (403)
 *   4. policies  · el recurso es tuyo (404 si no, para no revelar que existe)
 *   5. servicio  · reglas de negocio (409)
 */

// --- Público ---------------------------------------------------------------

Route::middleware('throttle:60,1')->group(function () {
    Route::get('talks', [TalkController::class, 'index'])->name('talks.index');                          // A01
    Route::get('talks/{talk}', [TalkController::class, 'show'])->whereNumber('talk')->name('talks.show'); // A02
});

Route::get('health', HealthController::class)->name('health'); // A29

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');          // A04
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register'); // A05

// --- Autenticado -----------------------------------------------------------

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout'); // A06
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');              // A07

    // Reservas: solo asistentes. El resto de perfiles no reserva plazas.
    Route::middleware('role:attendee')->group(function () {
        Route::get('me/reservations', [ReservationController::class, 'index'])->name('reservations.mine');                                   // A08
        Route::get('me/reservations/{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('reservations.show'); // A09
        Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');                                  // A10
        Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->whereNumber('reservation')->name('reservations.cancel'); // A11
    });
});
