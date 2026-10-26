<?php

namespace Tests\Feature\Api;

use App\Events\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Reservas A08–A11. Pruebas T05, T06, T07, T09, T10 y T11 del plan. */
class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $lucia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lucia = User::factory()->create(['email' => 'lucia@sabitech.test', 'password' => 'Clave-segura-2026']);
    }

    private function reserve(Talk $talk, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->lucia)->postJson('/api/v1/reservations', ['talk_id' => $talk->id]);
    }

    #[Test]
    public function t05_reserva_correcta_guarda_la_reserva_y_descuenta_una_plaza(): void
    {
        Event::fake([ReservationConfirmed::class]);
        $talk = Talk::factory()->create(['capacity' => 10]);

        $response = $this->reserve($talk)
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.display_status', 'confirmed')
            ->assertJsonPath('data.talk.id', $talk->id)
            ->assertJsonPath('data.talk.available_seats', 9)
            ->assertJsonPath('data.email_status', 'pending');

        $this->assertMatchesRegularExpression('/^SBT-[A-HJKMNP-Z2-9]{6}$/', $response->json('data.reference'));
        $this->assertDatabaseHas('reservations', ['user_id' => $this->lucia->id, 'talk_id' => $talk->id, 'status' => 'confirmed']);
        $this->getJson("/api/v1/talks/{$talk->id}")->assertJsonPath('data.available_seats', 9);
        Event::assertDispatched(ReservationConfirmed::class, 1);
    }

    #[Test]
    public function t06_una_reserva_duplicada_se_rechaza_sin_crear_otra_fila(): void
    {
        $talk = Talk::factory()->create();
        $this->reserve($talk)->assertCreated();

        $this->reserve($talk)
            ->assertConflict()
            ->assertExactJson(['error' => ['code' => 'DUPLICATE', 'message' => 'Ya tienes una reserva activa para esta charla.', 'fields' => null]]);

        $this->assertSame(1, Reservation::where('user_id', $this->lucia->id)->count());
    }

    #[Test]
    public function t07_con_el_aforo_completo_se_rechaza_la_reserva(): void
    {
        $talk = Talk::factory()->create(['capacity' => 2]);
        Reservation::factory()->count(2)->for($talk)->create();

        $this->reserve($talk)->assertConflict()->assertJsonPath('error.code', 'TALK_FULL');
        $this->assertSame(2, $talk->reservations()->count());
    }

    #[Test]
    public function una_plaza_liberada_por_cancelacion_se_puede_volver_a_reservar(): void
    {
        $talk = Talk::factory()->create(['capacity' => 1]);
        Reservation::factory()->for($talk)->cancelled()->create();

        $this->reserve($talk)->assertCreated();
    }

    #[Test]
    public function no_se_puede_reservar_una_charla_inexistente_borrador_o_cancelada(): void
    {
        $this->actingAs($this->lucia)->postJson('/api/v1/reservations', ['talk_id' => 999999])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->reserve(Talk::factory()->draft()->create())->assertNotFound();
        $this->reserve(Talk::factory()->cancelled()->create())->assertNotFound();
        $this->assertSame(0, Reservation::count());
    }

    #[Test]
    public function no_se_puede_reservar_una_charla_que_ya_ha_empezado(): void
    {
        $start = now()->subMinutes(5);
        $talk = Talk::factory()->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);

        $this->reserve($talk)->assertConflict()->assertJsonPath('error.code', 'TALK_STARTED');
    }

    #[Test]
    public function no_se_pueden_reservar_dos_charlas_que_se_solapan(): void
    {
        $first = Talk::factory()->at('2027-02-24 10:00', 60)->create();
        $overlapping = Talk::factory()->at('2027-02-24 10:30', 60)->create();
        $justAfter = Talk::factory()->at('2027-02-24 11:00', 45)->create();

        $this->reserve($first)->assertCreated();
        $this->reserve($overlapping)->assertConflict()->assertJsonPath('error.code', 'OVERLAP');
        // Terminar a las 11:00 y empezar a las 11:00 no es solaparse.
        $this->reserve($justAfter)->assertCreated();
    }

    #[Test]
    public function la_entrada_se_valida_en_el_servidor(): void
    {
        $this->actingAs($this->lucia)->postJson('/api/v1/reservations', ['talk_id' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.talk_id.0', 'La charla indicada no es válida.');
    }

    #[Test]
    public function sin_sesion_o_sin_rol_de_asistente_no_se_reserva(): void
    {
        $talk = Talk::factory()->create();

        $this->postJson('/api/v1/reservations', ['talk_id' => $talk->id])->assertUnauthorized();
        $this->reserve($talk, User::factory()->staff()->create())->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->reserve($talk, User::factory()->admin()->create())->assertForbidden();
        $this->assertSame(0, Reservation::count());
    }

    #[Test]
    public function mis_reservas_solo_muestra_las_propias_con_su_estado(): void
    {
        $talk = Talk::factory()->create();
        $mine = Reservation::factory()->for($this->lucia)->for($talk)->create();
        Reservation::factory()->for($this->lucia)->cancelled()->create();
        Reservation::factory()->count(3)->create(); // de otras personas

        $this->actingAs($this->lucia)->getJson('/api/v1/me/reservations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->lucia)->getJson('/api/v1/me/reservations?status=confirmed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.talk.title', $talk->title);
    }

    #[Test]
    public function la_reserva_aparece_como_asistida_cuando_hay_asistencia(): void
    {
        $reservation = Reservation::factory()->for($this->lucia)->create();
        DB::table('attendances')->insert([
            'reservation_id' => $reservation->id,
            'user_id' => $this->lucia->id,
            'talk_id' => $reservation->talk_id,
            'validated_by' => User::factory()->staff()->create()->id,
            'method' => 'qr',
            'validated_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAs($this->lucia)->getJson("/api/v1/me/reservations/{$reservation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.display_status', 'attended');
    }

    #[Test]
    public function t11_la_reserva_persiste_tras_cerrar_sesion_y_volver_a_entrar(): void
    {
        $talk = Talk::factory()->create();
        $this->postJson('/api/v1/auth/login', ['email' => 'lucia@sabitech.test', 'password' => 'Clave-segura-2026'])->assertOk();
        $reference = $this->postJson('/api/v1/reservations', ['talk_id' => $talk->id])->assertCreated()->json('data.reference');

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me/reservations')->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', ['email' => 'lucia@sabitech.test', 'password' => 'Clave-segura-2026'])->assertOk();
        $this->getJson('/api/v1/me/reservations')->assertOk()->assertJsonPath('data.0.reference', $reference);
    }

    #[Test]
    public function t09_cancelar_la_reserva_propia_libera_la_plaza(): void
    {
        $talk = Talk::factory()->create(['capacity' => 1]);
        $reservation = Reservation::factory()->for($this->lucia)->for($talk)->create();
        $this->getJson("/api/v1/talks/{$talk->id}")->assertJsonPath('data.available_seats', 0);

        $this->actingAs($this->lucia)->postJson("/api/v1/reservations/{$reservation->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.talk.available_seats', 1);

        $this->assertNotNull($reservation->fresh()->cancelled_at);
        $this->getJson("/api/v1/talks/{$talk->id}")->assertJsonPath('data.available_seats', 1);
    }

    #[Test]
    public function t10_no_se_puede_ver_ni_cancelar_una_reserva_ajena(): void
    {
        $other = Reservation::factory()->create();

        $this->actingAs($this->lucia)->getJson("/api/v1/me/reservations/{$other->id}")->assertNotFound();
        $this->actingAs($this->lucia)->postJson("/api/v1/reservations/{$other->id}/cancel")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame('confirmed', $other->fresh()->status->value);
    }

    #[Test]
    public function no_se_cancela_dos_veces_ni_despues_de_empezar_ni_con_asistencia(): void
    {
        $cancelled = Reservation::factory()->for($this->lucia)->cancelled()->create();
        $this->actingAs($this->lucia)->postJson("/api/v1/reservations/{$cancelled->id}/cancel")
            ->assertConflict()->assertJsonPath('error.code', 'ALREADY_CANCELLED');

        $start = now()->subMinutes(10);
        $started = Talk::factory()->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
        $ongoing = Reservation::factory()->for($this->lucia)->for($started)->create();
        $this->actingAs($this->lucia)->postJson("/api/v1/reservations/{$ongoing->id}/cancel")
            ->assertConflict()->assertJsonPath('error.code', 'TALK_STARTED');

        $attended = Reservation::factory()->for($this->lucia)->create();
        DB::table('attendances')->insert([
            'reservation_id' => $attended->id,
            'user_id' => $this->lucia->id,
            'talk_id' => $attended->talk_id,
            'validated_by' => User::factory()->staff()->create()->id,
            'method' => 'manual',
            'manual_reason' => 'Prueba',
            'validated_at' => now(),
        ]);
        $this->actingAs($this->lucia)->postJson("/api/v1/reservations/{$attended->id}/cancel")
            ->assertConflict()->assertJsonPath('error.code', 'ATTENDED');
    }

    #[Test]
    public function tras_cancelar_se_puede_volver_a_reservar_con_otra_referencia(): void
    {
        $talk = Talk::factory()->create();
        $first = $this->reserve($talk)->assertCreated()->json('data');
        $this->actingAs($this->lucia)->postJson("/api/v1/reservations/{$first['id']}/cancel")->assertOk();

        $second = $this->reserve($talk)->assertCreated()->json('data');

        $this->assertNotSame($first['reference'], $second['reference']);
        $this->assertSame(2, Reservation::where('user_id', $this->lucia->id)->where('talk_id', $talk->id)->count());
    }
}
