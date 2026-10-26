<?php

namespace Tests\Feature;

use App\Enums\AttendanceMethod;
use App\Enums\ReservationStatus;
use App\Enums\ScanResult;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\ScanAttempt;
use App\Models\Speaker;
use App\Models\Talk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Panel de organización (A17–A28 y A30). Pruebas T20 y T21 del plan. */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Speaker $speaker;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-02-01 12:00:00', 'UTC'));
        $this->admin = User::factory()->admin()->create(['name' => 'Ana Organizadora']);
        $this->speaker = Speaker::factory()->create(['name' => 'Marta Ibáñez']);
        $this->room = Room::factory()->create(['name' => 'Aula Magna']);
    }

    /** @return array<string, mixed> */
    private function talkInput(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Accesibilidad web más allá del contraste',
            'description' => 'Patrones de foco, lectores de pantalla y formularios accesibles.',
            'speaker_id' => $this->speaker->id,
            'room_id' => $this->room->id,
            'starts_at' => '2027-02-24T10:00:00+01:00',
            'ends_at' => '2027-02-24T10:45:00+01:00',
            'capacity' => 40,
        ];
    }

    // --- Permisos ---------------------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function adminRoutes(): array
    {
        return [
            'A17' => ['GET', '/api/v1/admin/talks'],
            'A18' => ['POST', '/api/v1/admin/talks'],
            'A21' => ['GET', '/api/v1/admin/speakers'],
            'A22' => ['DELETE', '/api/v1/admin/rooms/1'],
            'A23' => ['GET', '/api/v1/admin/reservations'],
            'A24' => ['GET', '/api/v1/admin/attendances'],
            'A27' => ['PATCH', '/api/v1/admin/users/1/role'],
            'A28' => ['GET', '/api/v1/admin/stats'],
            'A30' => ['GET', '/api/v1/admin/users'],
        ];
    }

    #[Test]
    #[DataProvider('adminRoutes')]
    public function t20_solo_el_admin_entra_en_el_panel(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->actingAs(User::factory()->staff()->create())->json($method, $uri)
            ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs(User::factory()->create())->json($method, $uri)
            ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // --- Charlas ------------------------------------------------------------------

    #[Test]
    public function crear_una_charla_la_deja_en_borrador_y_no_sale_en_el_programa(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/talks', $this->talkInput())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.starts_at', '2027-02-24T10:00:00+01:00')
            ->assertJsonPath('data.available_seats', 40)
            ->assertJsonPath('data.reservations_confirmed', 0)
            ->json('data.id');

        $this->assertDatabaseHas('talks', ['id' => $id, 'starts_at' => '2027-02-24 09:00:00']);
        $this->getJson("/api/v1/talks/{$id}")->assertNotFound();
        $this->actingAs($this->admin)->getJson("/api/v1/admin/talks/{$id}")
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.speaker.name', 'Marta Ibáñez');
    }

    #[Test]
    public function la_validacion_de_la_charla_explica_cada_campo(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/talks', $this->talkInput([
            'title' => 'AB',
            'ends_at' => '2027-02-24T09:00:00+01:00',
            'capacity' => 0,
            'speaker_id' => 999,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.fields.title.0', 'El título debe tener al menos 3 caracteres.')
            ->assertJsonPath('error.fields.ends_at.0', 'La hora de fin debe ser posterior a la de inicio.')
            ->assertJsonPath('error.fields.capacity.0', 'El aforo debe ser al menos 1.')
            ->assertJsonPath('error.fields.speaker_id.0', 'El ponente no existe.');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/talks', $this->talkInput([
            'starts_at' => '2027-01-20T10:00:00+01:00',
            'ends_at' => '2027-01-20T10:45:00+01:00',
        ]))->assertUnprocessable()->assertJsonPath('error.fields.starts_at.0', 'La charla debe empezar en el futuro.');
    }

    #[Test]
    public function dos_charlas_no_pueden_coincidir_en_la_misma_sala(): void
    {
        Talk::factory()->for($this->room)->at('2027-02-24 10:30')->create(['title' => 'Charla previa']);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/talks', $this->talkInput())
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.room_id.0', 'La sala ya está ocupada a esa hora por «Charla previa».');

        // Una charla cancelada no ocupa la sala.
        Talk::query()->update(['status' => 'cancelled']);
        $this->actingAs($this->admin)->postJson('/api/v1/admin/talks', $this->talkInput())->assertCreated();
    }

    #[Test]
    public function t21_el_aforo_no_puede_bajar_de_las_reservas_confirmadas(): void
    {
        $talk = Talk::factory()->for($this->speaker)->for($this->room)->at('2027-02-24 10:00')->create(['capacity' => 40]);
        Reservation::factory()->count(3)->for($talk)->create();
        Reservation::factory()->cancelled()->for($talk)->create();

        $this->actingAs($this->admin)->putJson("/api/v1/admin/talks/{$talk->id}", $this->talkInput(['capacity' => 2]))
            ->assertConflict()
            ->assertJsonPath('error.code', 'AFORO_BELOW_CONFIRMED')
            ->assertJsonPath('error.message', 'El aforo no puede ser menor que las 3 reservas confirmadas.');
        $this->assertSame(40, $talk->fresh()->capacity);

        // Igual al número de confirmadas sí se permite (las canceladas no cuentan).
        $this->actingAs($this->admin)->putJson("/api/v1/admin/talks/{$talk->id}", $this->talkInput(['capacity' => 3]))
            ->assertOk()
            ->assertJsonPath('data.capacity', 3)
            ->assertJsonPath('data.available_seats', 0)
            ->assertJsonPath('data.reservations_confirmed', 3);
    }

    #[Test]
    public function editar_una_charla_no_choca_consigo_misma_en_la_sala(): void
    {
        $talk = Talk::factory()->for($this->speaker)->for($this->room)->at('2027-02-24 10:00')->create();

        $this->actingAs($this->admin)->putJson("/api/v1/admin/talks/{$talk->id}", $this->talkInput(['title' => 'Título nuevo']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Título nuevo');
    }

    #[Test]
    public function publicar_hace_visible_la_charla_en_el_programa(): void
    {
        $talk = Talk::factory()->draft()->at('2027-02-24 10:00')->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/talks/{$talk->id}/status", ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');

        $this->getJson("/api/v1/talks/{$talk->id}")->assertOk();
    }

    #[Test]
    public function no_se_despublica_una_charla_con_reservas(): void
    {
        $talk = Talk::factory()->at('2027-02-24 10:00')->create();
        Reservation::factory()->for($talk)->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/talks/{$talk->id}/status", ['status' => 'draft'])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.status.0', 'Tiene reservas confirmadas: no se puede despublicar. Si no se va a celebrar, cancélala.');
    }

    #[Test]
    public function cancelar_una_charla_cancela_sus_reservas_y_no_tiene_vuelta_atras(): void
    {
        $talk = Talk::factory()->at('2027-02-24 10:00')->create();
        $active = Reservation::factory()->for($talk)->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/talks/{$talk->id}/status", ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.reservations_confirmed', 0);

        $this->assertSame(ReservationStatus::Cancelled, $active->fresh()->status);
        $this->assertNotNull($active->fresh()->cancelled_at);
        $this->getJson("/api/v1/talks/{$talk->id}")->assertNotFound();

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/talks/{$talk->id}/status", ['status' => 'published'])
            ->assertUnprocessable()->assertJsonPath('error.fields.status.0', 'Una charla cancelada no se puede reactivar.');
        $this->actingAs($this->admin)->putJson("/api/v1/admin/talks/{$talk->id}", $this->talkInput())
            ->assertUnprocessable()->assertJsonPath('error.fields.status.0', 'Una charla cancelada no se puede editar.');
    }

    #[Test]
    public function una_charla_empezada_no_cambia_de_estado(): void
    {
        $talk = Talk::factory()->at('2027-02-01 12:30')->create(); // 11:30 UTC: ya empezó.

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/talks/{$talk->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()->assertJsonPath('error.fields.status.0', 'La charla ya ha comenzado: su estado no se puede cambiar.');
    }

    #[Test]
    public function el_listado_incluye_borradores_y_canceladas_con_sus_contadores(): void
    {
        $published = Talk::factory()->at('2027-02-24 10:00')->create();
        Talk::factory()->draft()->at('2027-02-24 12:00')->create();
        Talk::factory()->cancelled()->at('2027-02-24 13:00')->create();
        $reservation = Reservation::factory()->for($published)->create();
        Attendance::create([
            'reservation_id' => $reservation->id, 'user_id' => $reservation->user_id, 'talk_id' => $published->id,
            'validated_by' => $this->admin->id, 'method' => AttendanceMethod::Qr, 'validated_at' => now(),
        ]);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/talks')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.reservations_confirmed', 1)
            ->assertJsonPath('data.0.attendances', 1)
            ->assertJsonPath('data.0.description', $published->description);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/talks?status=draft')->assertJsonPath('meta.total', 1);
    }

    // --- Ponentes y salas ----------------------------------------------------------

    #[Test]
    public function ponentes_alta_edicion_y_borrado_protegido(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/speakers', ['name' => '  Raúl Ortega ', 'affiliation' => ''])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Raúl Ortega')
            ->assertJsonPath('data.affiliation', null)
            ->json('data.id');

        $this->actingAs($this->admin)->putJson("/api/v1/admin/speakers/{$id}", ['name' => 'Raúl Ortega Gil', 'affiliation' => 'XTART'])
            ->assertOk()->assertJsonPath('data.affiliation', 'XTART');

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/speakers/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('speakers', ['id' => $id]);

        Talk::factory()->for($this->speaker)->create();
        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/speakers/{$this->speaker->id}")
            ->assertConflict()->assertJsonPath('error.code', 'HAS_TALKS');
        $this->assertDatabaseHas('speakers', ['id' => $this->speaker->id]);
    }

    #[Test]
    public function salas_con_nombre_unico_y_borrado_protegido(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/rooms', ['name' => 'Aula Magna'])
            ->assertUnprocessable()->assertJsonPath('error.fields.name.0', 'Ya existe una sala con ese nombre.');

        // Guardar la misma sala con su propio nombre no es un duplicado.
        $this->actingAs($this->admin)->putJson("/api/v1/admin/rooms/{$this->room->id}", ['name' => 'Aula Magna', 'location_details' => 'Planta baja'])
            ->assertOk()->assertJsonPath('data.location_details', 'Planta baja');

        Talk::factory()->for($this->room)->create();
        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/rooms/{$this->room->id}")
            ->assertConflict()->assertJsonPath('error.code', 'HAS_TALKS');

        $this->actingAs($this->admin)->getJson('/api/v1/admin/rooms')->assertOk()->assertJsonCount(1, 'data');
    }

    // --- Reservas, asistencias e indicadores -------------------------------------

    #[Test]
    public function las_reservas_se_filtran_por_estado_charla_y_texto(): void
    {
        $talk = Talk::factory()->create(['title' => 'Seguridad en APIs']);
        $other = Talk::factory()->create();
        $lucia = User::factory()->create(['name' => 'Lucía Ferrer', 'email' => 'lucia@example.test']);
        $attended = Reservation::factory()->for($talk)->for($lucia)->create();
        Attendance::create([
            'reservation_id' => $attended->id, 'user_id' => $lucia->id, 'talk_id' => $talk->id,
            'validated_by' => $this->admin->id, 'method' => AttendanceMethod::Qr, 'validated_at' => now(),
        ]);
        Reservation::factory()->for($talk)->create();
        Reservation::factory()->cancelled()->for($talk)->create();
        Reservation::factory()->for($other)->create();

        $this->actingAs($this->admin)->getJson("/api/v1/admin/reservations?talk_id={$talk->id}")->assertJsonPath('meta.total', 3);
        $this->actingAs($this->admin)->getJson('/api/v1/admin/reservations?status=confirmed')->assertJsonPath('meta.total', 2);
        $this->actingAs($this->admin)->getJson('/api/v1/admin/reservations?status=cancelled')->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/reservations?status=attended')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference', $attended->reference)
            ->assertJsonPath('data.0.display_status', 'attended')
            ->assertJsonPath('data.0.attendee.email', 'lucia@example.test')
            ->assertJsonPath('data.0.talk_title', 'Seguridad en APIs');

        $this->actingAs($this->admin)->getJson('/api/v1/admin/reservations?q=lucia@')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->admin)->getJson('/api/v1/admin/reservations?q='.$attended->reference)->assertJsonPath('meta.total', 1);
        // Los comodines de LIKE se tratan como texto.
        $this->actingAs($this->admin)->getJson('/api/v1/admin/reservations?q=%25')->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function las_asistencias_muestran_quien_valido_y_el_motivo_manual(): void
    {
        $talk = Talk::factory()->create();
        $staff = User::factory()->staff()->create(['name' => 'Sergio Acceso']);
        foreach ([AttendanceMethod::Qr, AttendanceMethod::Manual] as $method) {
            $r = Reservation::factory()->for($talk)->create();
            Attendance::create([
                'reservation_id' => $r->id, 'user_id' => $r->user_id, 'talk_id' => $talk->id, 'validated_by' => $staff->id,
                'method' => $method, 'manual_reason' => $method === AttendanceMethod::Manual ? 'Móvil sin batería' : null,
                'validated_at' => now(),
            ]);
        }

        $this->actingAs($this->admin)->getJson('/api/v1/admin/attendances?method=manual')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.method', 'manual')
            ->assertJsonPath('data.0.manual_reason', 'Móvil sin batería')
            ->assertJsonPath('data.0.validated_by', 'Sergio Acceso');
    }

    #[Test]
    public function los_indicadores_salen_de_la_base_de_datos(): void
    {
        $talk = Talk::factory()->create();
        Talk::factory()->draft()->create();
        $r = Reservation::factory()->for($talk)->create();
        Reservation::factory()->cancelled()->for($talk)->create();
        Attendance::create([
            'reservation_id' => $r->id, 'user_id' => $r->user_id, 'talk_id' => $talk->id,
            'validated_by' => $this->admin->id, 'method' => AttendanceMethod::Qr, 'validated_at' => now(),
        ]);
        EmailNotification::create([
            'reservation_id' => $r->id, 'type' => EmailNotification::TYPE_RESERVATION_CONFIRMED,
            'recipient' => 'x@example.test', 'status' => 'failed', 'attempts' => 5,
        ]);
        foreach ([ScanResult::Registered, ScanResult::Already, ScanResult::Expired, ScanResult::Invalid] as $result) {
            ScanAttempt::create(['staff_id' => $this->admin->id, 'talk_id' => $talk->id, 'result' => $result]);
        }

        $this->actingAs($this->admin)->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertExactJson(['data' => [
                'talks' => 1,
                'reservations_confirmed' => 1,
                'attendances' => 1,
                'notifications_failed' => 1,
                'scans_rejected' => 2,
            ]]);
    }

    // --- Usuarios y roles ------------------------------------------------------------

    #[Test]
    public function el_admin_busca_usuarios_y_cambia_su_rol(): void
    {
        $lucia = User::factory()->create(['name' => 'Lucía Ferrer']);
        User::factory()->create(['name' => 'Pablo Ruiz']);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/users?q=luc')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.role', 'attendee');

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/users/{$lucia->id}/role", ['role' => 'staff'])
            ->assertOk()->assertJsonPath('data.role', 'staff');
        $this->assertSame(UserRole::Staff, $lucia->fresh()->role);

        // El cambio se aplica en la siguiente petición: ya no puede reservar.
        $this->actingAs($lucia->fresh())->getJson('/api/v1/me/reservations')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/users?role=staff')->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function nadie_cambia_su_propio_rol(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/v1/admin/users/{$this->admin->id}/role", ['role' => 'attendee'])
            ->assertUnprocessable()->assertJsonPath('error.fields.role.0', 'No puedes cambiar tu propio rol.');

        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);

        $this->actingAs($this->admin)->patchJson('/api/v1/admin/users/999/role', ['role' => 'staff'])->assertNotFound();
        $this->actingAs($this->admin)->patchJson("/api/v1/admin/users/{$this->admin->id}/role", ['role' => 'root'])
            ->assertUnprocessable()->assertJsonPath('error.fields.role.0', 'El rol no es válido.');
    }
}
