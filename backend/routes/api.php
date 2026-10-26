<?php

use App\Http\Controllers\Api\V1\AccessController;
use App\Http\Controllers\Api\V1\Admin\ActivityController;
use App\Http\Controllers\Api\V1\Admin\CatalogDataController;
use App\Http\Controllers\Api\V1\Admin\NotificationController;
use App\Http\Controllers\Api\V1\Admin\TalkController as AdminTalkController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CredentialController;
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

        Route::get('reservations/{reservation}/credential', CredentialController::class)                                                     // A12
            ->whereNumber('reservation')->middleware('throttle:credential')->withoutMiddleware('throttle:api')->name('reservations.credential');
    });

    // Control de acceso: personal de acceso y administración.
    Route::middleware('role:staff,admin')->group(function () {
        Route::get('staff/talks', [AccessController::class, 'talks'])->name('access.talks');                                                   // A13
        Route::post('checkins', [AccessController::class, 'checkIn'])->name('access.checkin');                                                 // A14
        Route::get('staff/talks/{talk}/reservations', [AccessController::class, 'search'])->whereNumber('talk')->name('access.search');        // A15
        Route::post('checkins/manual', [AccessController::class, 'manual'])->name('access.manual');                                           // A16
    });

    // Panel de organización: solo administradores.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('talks', [AdminTalkController::class, 'index'])->name('talks.index');                                         // A17
        Route::post('talks', [AdminTalkController::class, 'store'])->name('talks.store');                                        // A18
        Route::get('talks/{talk}', [AdminTalkController::class, 'show'])->whereNumber('talk')->name('talks.show');               // A17
        Route::put('talks/{talk}', [AdminTalkController::class, 'update'])->whereNumber('talk')->name('talks.update');           // A19
        Route::patch('talks/{talk}/status', [AdminTalkController::class, 'status'])->whereNumber('talk')->name('talks.status');  // A20

        Route::get('speakers', [CatalogDataController::class, 'speakers'])->name('speakers.index');                                        // A21
        Route::post('speakers', [CatalogDataController::class, 'storeSpeaker'])->name('speakers.store');
        Route::put('speakers/{speaker}', [CatalogDataController::class, 'updateSpeaker'])->whereNumber('speaker')->name('speakers.update');
        Route::delete('speakers/{speaker}', [CatalogDataController::class, 'destroySpeaker'])->whereNumber('speaker')->name('speakers.destroy');

        Route::get('rooms', [CatalogDataController::class, 'rooms'])->name('rooms.index');                                                 // A22
        Route::post('rooms', [CatalogDataController::class, 'storeRoom'])->name('rooms.store');
        Route::put('rooms/{room}', [CatalogDataController::class, 'updateRoom'])->whereNumber('room')->name('rooms.update');
        Route::delete('rooms/{room}', [CatalogDataController::class, 'destroyRoom'])->whereNumber('room')->name('rooms.destroy');

        Route::get('reservations', [ActivityController::class, 'reservations'])->name('reservations.index'); // A23
        Route::get('attendances', [ActivityController::class, 'attendances'])->name('attendances.index');    // A24
        Route::get('stats', [ActivityController::class, 'stats'])->name('stats');                            // A28

        Route::get('users', [UserController::class, 'index'])->name('users.index');                                         // A30
        Route::patch('users/{user}/role', [UserController::class, 'role'])->whereNumber('user')->name('users.role');         // A27

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');                                              // A25
        Route::post('notifications/{notification}/retry', [NotificationController::class, 'retry'])->whereNumber('notification')->name('notifications.retry'); // A26
    });
});
