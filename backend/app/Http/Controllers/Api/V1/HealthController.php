<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A29 · Estado del servicio, para diagnóstico y para la demostración.
 *
 * - database: la consulta más simple posible contra MySQL.
 * - queue: no hay trabajos atascados (pendientes desde hace más de 5 minutos), que
 *   indicaría que el worker de correo está parado.
 * Nunca devuelve detalles internos; los errores van al log.
 */
class HealthController extends Controller
{
    private const STUCK_JOB_SECONDS = 300;

    public function __invoke(): JsonResponse
    {
        $database = $this->check(fn () => DB::select('SELECT 1'));
        $queue = $database === 'ok'
            ? $this->check(function () {
                $stuck = DB::table('jobs')
                    ->where('available_at', '<', now()->subSeconds(self::STUCK_JOB_SECONDS)->getTimestamp())
                    ->whereNull('reserved_at')
                    ->exists();
                if ($stuck) {
                    throw new \RuntimeException('Hay trabajos de cola sin procesar desde hace más de 5 minutos.');
                }
            })
            : 'error';

        $ok = $database === 'ok' && $queue === 'ok';

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'database' => $database,
            'queue' => $queue,
            'time' => ApiDate::format(now()),
        ], $ok ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $e) {
            Log::warning('Comprobación de salud fallida', ['error' => $e->getMessage()]);

            return 'error';
        }
    }
}
