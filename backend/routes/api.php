<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TalkController;
use Illuminate\Support\Facades\Route;

/*
 * API v1 (prefijo /api/v1, ver bootstrap/app.php). Cada ruta indica su operación del
 * contrato docs/api/openapi.yaml. Las rutas autenticadas se añaden en sus hitos.
 */

// A01 · Programa público
Route::get('talks', [TalkController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('talks.index');

// A02 · Detalle de una charla publicada
Route::get('talks/{talk}', [TalkController::class, 'show'])
    ->whereNumber('talk')
    ->middleware('throttle:60,1')
    ->name('talks.show');

// A29 · Estado del servicio
Route::get('health', HealthController::class)->name('health');
