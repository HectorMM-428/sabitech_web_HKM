<?php

namespace Database\Seeders;

use App\Enums\NotificationStatus;
use App\Enums\TalkStatus;
use App\Enums\UserRole;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\Talk;
use App\Models\User;
use App\Support\ReservationReference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de laboratorio reproducibles (§4.13 del enunciado): charlas con plazas, una
 * charla completa, un borrador, usuarios de cada perfil y reservas en distintos estados.
 *
 * Todo es ficticio. Las fechas son relativas a hoy, para que la demo siempre tenga
 * charlas futuras: día 1 = dentro de 7 días, día 2 = dentro de 8 días (hora de Madrid).
 *
 *   php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make(config('sabitech.lab_password'));

        // --- Usuarios de laboratorio (uno por perfil + asistentes) ---
        $admin = $this->user('Ana Organizadora', 'admin@sabitech.test', UserRole::Admin, $password);
        $this->user('Sergio Acceso', 'staff@sabitech.test', UserRole::Staff, $password);
        $lucia = $this->user('Lucía Ferrer', 'lucia@sabitech.test', UserRole::Attendee, $password);
        $pablo = $this->user('Pablo Ortega', 'pablo@sabitech.test', UserRole::Attendee, $password);
        $irene = $this->user('Irene Navarro', 'irene@sabitech.test', UserRole::Attendee, $password);

        // --- Salas ---
        $magna = Room::create(['name' => 'Aula Magna', 'location_details' => 'Planta baja, edificio principal']);
        $lab = Room::create(['name' => 'Laboratorio 1', 'location_details' => 'Primera planta, pasillo izquierdo']);
        $b2 = Room::create(['name' => 'Sala B2', 'location_details' => 'Segunda planta']);

        // --- Ponentes (ficticios) ---
        $marta = Speaker::create([
            'name' => 'Marta Ibáñez',
            'affiliation' => 'Estudio Faro',
            'bio' => 'Diseñadora de producto especializada en accesibilidad. Audita interfaces públicas con lectores de pantalla y usuarios reales.',
        ]);
        $david = Speaker::create([
            'name' => 'David Requena',
            'affiliation' => 'Nube Levante',
            'bio' => 'Ingeniero de plataforma. Lleva aplicaciones PHP a contenedores y automatiza sus despliegues.',
        ]);
        $nuria = Speaker::create([
            'name' => 'Nuria Climent',
            'affiliation' => 'Laboratorio de Datos Turia',
            'bio' => 'Desarrolladora backend centrada en integridad de datos y sistemas de reservas.',
        ]);
        $omar = Speaker::create([
            'name' => 'Omar Benali',
            'affiliation' => 'Ciberseguridad Albufera',
            'bio' => 'Analista de seguridad web. Imparte talleres sobre autenticación y gestión de sesiones.',
        ]);
        $elena = Speaker::create([
            'name' => 'Elena Sanchis',
            'affiliation' => 'Colectivo Front Valencia',
            'bio' => 'Desarrolladora frontend. Trabaja con React y TypeScript en aplicaciones de gran tráfico.',
        ]);

        $day1 = CarbonImmutable::now('Europe/Madrid')->addDays(7)->format('Y-m-d');
        $day2 = CarbonImmutable::now('Europe/Madrid')->addDays(8)->format('Y-m-d');

        // --- Charlas ---
        $accesibilidad = $this->talk('Accesibilidad web más allá del contraste', 'Cómo probar una interfaz con teclado y lector de pantalla, qué revisar de WCAG 2.2 y qué errores aparecen una y otra vez en formularios reales.', $marta, $magna, "$day1 10:00", 45, 120);
        $contenedores = $this->talk('Del portátil a producción con Docker Compose', 'Montamos una aplicación PHP con tres contenedores, variables de entorno y HTTPS, y vemos qué cambia entre el entorno de desarrollo y el de demostración.', $david, $lab, "$day1 11:00", 60, 30);
        $concurrencia = $this->talk('La última plaza: concurrencia en sistemas de reservas', 'Qué pasa cuando dos personas reservan la última plaza a la vez, por qué comprobar antes de insertar no basta y cómo lo resuelven las transacciones y los bloqueos de fila.', $nuria, $b2, "$day1 12:30", 45, 2);
        $sesiones = $this->talk('Sesiones, cookies y CSRF sin miedo', 'Autenticación de una SPA con cookies HttpOnly, protección CSRF y límites de intentos, explicado con ejemplos que se pueden reproducir.', $omar, $magna, "$day2 10:00", 45, 80);
        $react = $this->talk('React y TypeScript: contratos que no se rompen', 'Generar los tipos del frontend a partir de la especificación OpenAPI para que una pantalla nunca muestre un estado que el servidor no ha guardado.', $elena, $lab, "$day2 11:30", 45, 25);
        $this->talk('Taller de QR temporales (borrador)', 'Charla en preparación: no debe aparecer en el programa público.', $nuria, $b2, "$day2 13:00", 60, 20, TalkStatus::Draft);

        // --- Reservas en distintos estados ---
        // La charla de concurrencia queda COMPLETA (aforo 2) para probar el rechazo por aforo.
        $this->reservation($lucia, $concurrencia);
        $this->reservation($pablo, $concurrencia);
        // Reservas con plaza.
        $this->reservation($lucia, $accesibilidad);
        $this->reservation($irene, $contenedores);
        // Una reserva cancelada (historial) y una nueva reserva de la misma persona.
        $this->reservation($pablo, $sesiones, cancelled: true);
        $this->reservation($pablo, $react);

        $this->command?->info("Datos de laboratorio creados. Administrador: {$admin->email}");
    }

    private function user(string $name, string $email, UserRole $role, string $passwordHash): User
    {
        $user = new User(['name' => $name, 'email' => $email]);
        $user->password = $passwordHash;
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function talk(
        string $title,
        string $description,
        Speaker $speaker,
        Room $room,
        string $madridStart,
        int $minutes,
        int $capacity,
        TalkStatus $status = TalkStatus::Published,
    ): Talk {
        $start = CarbonImmutable::parse($madridStart, 'Europe/Madrid')->utc();

        return Talk::create([
            'title' => $title,
            'description' => $description,
            'speaker_id' => $speaker->id,
            'room_id' => $room->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes($minutes),
            'capacity' => $capacity,
            'status' => $status,
        ]);
    }

    private function reservation(User $user, Talk $talk, bool $cancelled = false): Reservation
    {
        $reservation = Reservation::create([
            'reference' => ReservationReference::generate(),
            'user_id' => $user->id,
            'talk_id' => $talk->id,
            'status' => $cancelled ? 'cancelled' : 'confirmed',
            'confirmed_at' => now()->subDays(2),
            'cancelled_at' => $cancelled ? now()->subDay() : null,
        ]);

        // Historial ficticio: se marca como ya enviado para que la demo no envíe correos a cuentas de laboratorio.
        EmailNotification::create([
            'reservation_id' => $reservation->id,
            'type' => EmailNotification::TYPE_RESERVATION_CONFIRMED,
            'recipient' => $user->email,
            'status' => NotificationStatus::Sent,
            'attempts' => 1,
            'last_attempt_at' => now()->subDays(2),
            'sent_at' => now()->subDays(2),
        ]);

        return $reservation;
    }
}
