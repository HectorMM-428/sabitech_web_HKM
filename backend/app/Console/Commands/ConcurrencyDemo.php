<?php

namespace App\Console\Commands;

use App\Enums\TalkStatus;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Prueba de concurrencia real (H9, T08): lanza N procesos PHP independientes que
 * intentan reservar a la vez, cada uno con su propia conexión a MySQL, y comprueba
 * en la base de datos que no hay sobreventa.
 *
 *   php artisan sabitech:concurrency-demo --workers=30 --seats=1
 *   php artisan sabitech:concurrency-demo --workers=30 --seats=1 --without-lock   (demostración del fallo)
 *   php artisan sabitech:concurrency-demo --workers=10 --same-user                (doble clic)
 *
 * Crea una charla y usuarios temporales (correo @example.invalid) y los borra al acabar.
 * Código de salida 0 solo si las reservas confirmadas no superan el aforo.
 */
class ConcurrencyDemo extends Command
{
    protected $signature = 'sabitech:concurrency-demo
        {--workers=30 : Procesos que reservan a la vez}
        {--seats=1 : Aforo de la charla de prueba}
        {--same-user : Todos los procesos con el mismo usuario (doble clic)}
        {--without-lock : Usa una reserva ingenua sin bloqueo, para ver la sobreventa}
        {--keep : No borrar los datos de prueba al terminar}
        {--force : Permitir ejecutarlo con APP_ENV=production}';

    protected $description = 'Reservas simultáneas desde varios procesos para comprobar que no hay sobreventa';

    public function handle(): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('Entorno de producción: añade --force si de verdad quieres ejecutarlo aquí.');

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $seats = max(1, (int) $this->option('seats'));
        $sameUser = (bool) $this->option('same-user');
        $withoutLock = (bool) $this->option('without-lock');
        $marker = Str::lower(Str::random(8));

        [$talk, $users] = $this->createFixtures($marker, $workers, $seats, $sameUser);

        // Todos esperan a la misma marca de tiempo para chocar de verdad.
        $startAt = microtime(true) + 2.0 + $workers * 0.05;
        $processes = [];
        foreach ($users as $i => $user) {
            $command = [PHP_BINARY, base_path('artisan'), 'sabitech:concurrency-worker', (string) $user->id, (string) $talk->id, sprintf('%.6F', $startAt)];
            if ($withoutLock) {
                $command[] = '--without-lock';
            }
            $process = new Process($command, base_path(), $this->childEnv(), null, 60);
            $process->start();
            $processes[$i] = $process;
        }

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $line = trim(collect(explode("\n", trim($process->getOutput())))->last() ?? '');
            $results[] = $process->isSuccessful() && $line !== '' ? $line : 'ERROR: '.trim($process->getErrorOutput() ?: $line);
        }

        $confirmed = Reservation::query()->where('talk_id', $talk->id)->confirmed()->count();
        $expected = $sameUser ? 1 : min($seats, $workers);
        $ok = collect($results)->filter(fn ($r) => $r === 'OK')->count();

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>%d procesos</> · aforo %d · %s · %s',
            $workers,
            $seats,
            $sameUser ? 'mismo usuario' : 'usuarios distintos',
            $withoutLock ? '<fg=yellow>SIN bloqueo (ingenuo)</>' : 'con bloqueo FOR UPDATE',
        ));
        $this->table(['Resultado', 'Procesos'], collect($results)->countBy()->sortKeys()->map(fn ($n, $r) => [$r, $n])->values()->all());
        $this->line("Reservas confirmadas en la base de datos: <options=bold>{$confirmed}</> (aforo {$seats}, esperado {$expected})");

        $integrity = $confirmed <= $seats && $confirmed === $ok && ($sameUser ? $confirmed === 1 : $confirmed === $expected);

        if (! $this->option('keep')) {
            $this->cleanup($talk, $users);
        }

        if ($integrity) {
            $this->info('Sin sobreventa: cada plaza se ha asignado una sola vez.');

            return self::SUCCESS;
        }

        $this->error($confirmed > $seats
            ? "✕ SOBREVENTA: {$confirmed} reservas para {$seats} plazas."
            : 'Resultado inesperado: revisa la tabla.');

        return self::FAILURE;
    }

    /** @return array{Talk, list<User>} */
    private function createFixtures(string $marker, int $workers, int $seats, bool $sameUser): array
    {
        return DB::transaction(function () use ($marker, $workers, $seats, $sameUser) {
            $speaker = Speaker::create(['name' => "Prueba de concurrencia {$marker}"]);
            $room = Room::create(['name' => "Sala de prueba {$marker}"]);
            $start = now()->addDays(30)->startOfHour();
            $talk = Talk::create([
                'title' => "Prueba de concurrencia {$marker}",
                'description' => 'Charla temporal creada por sabitech:concurrency-demo.',
                'speaker_id' => $speaker->id,
                'room_id' => $room->id,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(45),
                'capacity' => $seats,
                'status' => TalkStatus::Published,
            ]);

            $count = $sameUser ? 1 : $workers;
            $created = [];
            for ($i = 1; $i <= $count; $i++) {
                $created[] = User::create([
                    'name' => "Concurrencia {$marker} {$i}",
                    'email' => "concurrency-{$marker}-{$i}@example.invalid",
                    'password' => Str::random(32),
                ]);
            }

            $users = $sameUser ? array_fill(0, $workers, $created[0]) : $created;

            return [$talk, $users];
        });
    }

    /** @param list<User> $users */
    private function cleanup(Talk $talk, array $users): void
    {
        DB::transaction(function () use ($talk, $users) {
            $reservationIds = Reservation::query()->where('talk_id', $talk->id)->pluck('id');
            EmailNotification::query()->whereIn('reservation_id', $reservationIds)->delete();
            Reservation::query()->whereIn('id', $reservationIds)->delete();
            User::query()->whereIn('id', collect($users)->pluck('id')->unique())->delete();
            $talk->delete();
            Speaker::query()->whereKey($talk->speaker_id)->delete();
            Room::query()->whereKey($talk->room_id)->delete();
        });
    }

    /**
     * Los procesos hijos usan la misma base de datos que este proceso (también en las
     * pruebas, donde la configuración viene de phpunit.xml y no de .env).
     *
     * @return array<string, string>
     */
    private function childEnv(): array
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        return array_filter([
            'APP_ENV' => (string) $this->laravel->environment(),
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => (string) $connection,
            'DB_HOST' => (string) ($db['host'] ?? ''),
            'DB_PORT' => (string) ($db['port'] ?? ''),
            'DB_DATABASE' => (string) ($db['database'] ?? ''),
            'DB_USERNAME' => (string) ($db['username'] ?? ''),
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
        ], fn ($v) => $v !== '');
    }
}
