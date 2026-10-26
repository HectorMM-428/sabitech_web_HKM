<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Reservation;
use App\Models\Talk;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\ReservationReference;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Un proceso de sabitech:concurrency-demo: espera a la marca de salida, intenta
 * reservar una vez e imprime OK o el código de error. No se usa fuera de la demo.
 */
class ConcurrencyWorker extends Command
{
    protected $signature = 'sabitech:concurrency-worker {user} {talk} {startAt} {--without-lock}';

    protected $description = 'Proceso interno de sabitech:concurrency-demo';

    protected $hidden = true;

    public function handle(ReservationService $reservations): int
    {
        // Ningún correo sale de una prueba de carga, aunque la configuración esté cacheada.
        config(['queue.default' => 'sync', 'mail.default' => 'array']);

        $user = User::query()->findOrFail((int) $this->argument('user'));
        $talkId = (int) $this->argument('talk');

        $startAt = (float) $this->argument('startAt');
        while (microtime(true) < $startAt) {
            usleep(200);
        }

        try {
            if ($this->option('without-lock')) {
                $this->naiveReserve($user, Talk::query()->findOrFail($talkId));
            } else {
                $reservations->reserve($user, $talkId);
            }
            $this->line('OK');
        } catch (BusinessRuleException $e) {
            $this->line($e->errorCode);
        } catch (QueryException $e) {
            // En la versión ingenua, el índice único sigue frenando al mismo usuario.
            $this->line($e->errorInfo[1] === 1062 ? 'DUPLICATE (índice único)' : 'DB_ERROR');
        }

        return self::SUCCESS;
    }

    /**
     * Lo que NO hay que hacer: contar y luego insertar sin bloquear la charla.
     * Entre la lectura y la escritura otro proceso puede llevarse la misma plaza.
     * La pausa solo agranda la ventana para que el fallo se vea siempre.
     */
    private function naiveReserve(User $user, Talk $talk): void
    {
        $confirmed = Reservation::query()->where('talk_id', $talk->id)->confirmed()->count();
        usleep(100_000);

        if ($confirmed >= $talk->capacity) {
            throw new BusinessRuleException('TALK_FULL', 'No quedan plazas.');
        }

        Reservation::create([
            'reference' => ReservationReference::generate(),
            'user_id' => $user->id,
            'talk_id' => $talk->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
