<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Reservation;
use App\Models\ScanAttempt;
use App\Models\Talk;
use App\Models\User;
use App\Services\QrTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** QR temporal y asistencia (A12–A16). Pruebas T14 a T19 del plan. */
class QrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $lucia;

    private User $staff;

    private Talk $talk;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        // Reloj fijo: la charla empieza dentro de 30 minutos, así la acreditación está abierta.
        $this->travelTo(CarbonImmutable::parse('2027-02-24 09:30:00', 'UTC'));

        $this->lucia = User::factory()->create(['name' => 'Lucía Ferrer']);
        $this->staff = User::factory()->staff()->create();
        $this->talk = Talk::factory()->create([
            'starts_at' => CarbonImmutable::parse('2027-02-24 10:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-02-24 10:45:00', 'UTC'),
        ]);
        $this->reservation = Reservation::factory()->for($this->lucia)->for($this->talk)->create();
    }

    private function credential(): string
    {
        return $this->actingAs($this->lucia)
            ->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertOk()
            ->json('data.token');
    }

    private function scan(string $token, ?int $talkId = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->postJson('/api/v1/checkins', [
            'token' => $token,
            'talk_id' => $talkId ?? $this->talk->id,
        ]);
    }

    #[Test]
    public function la_credencial_usa_la_hora_del_servidor_y_caduca_a_los_30_segundos(): void
    {
        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.issued_at', '2027-02-24T10:30:00+01:00')
            ->assertJsonPath('data.expires_at', '2027-02-24T10:30:30+01:00')
            ->assertJsonPath('data.server_time', '2027-02-24T10:30:00+01:00');
    }

    #[Test]
    public function t14_un_qr_vigente_registra_la_asistencia(): void
    {
        $token = $this->credential();
        $this->travel(20)->seconds();

        $this->scan($token)
            ->assertCreated()
            ->assertJsonPath('data.result', 'REGISTERED')
            ->assertJsonPath('data.attendee_name', 'Lucía Ferrer')
            ->assertJsonPath('data.method', 'qr');

        $attendance = Attendance::sole();
        $this->assertSame($this->staff->id, $attendance->validated_by);
        $this->assertSame($this->reservation->id, $attendance->reservation_id);
        $this->actingAs($this->lucia)->getJson("/api/v1/me/reservations/{$this->reservation->id}")
            ->assertJsonPath('data.display_status', 'attended');
    }

    #[Test]
    public function t15_el_limite_exacto_acepta_el_segundo_35_y_rechaza_el_36(): void
    {
        $token = $this->credential();

        $this->travel(35)->seconds();
        $this->scan($token)->assertCreated();

        Attendance::query()->delete();
        $this->travel(1)->seconds(); // segundo 36
        $this->scan($token)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'EXPIRED');
        $this->assertSame(0, Attendance::count());
    }

    #[Test]
    public function t16_un_qr_manipulado_o_inventado_se_rechaza(): void
    {
        $token = $this->credential();
        [$payload, $signature] = explode('.', $token);

        // Cambiar la reserva dentro del payload sin poder volver a firmar.
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $data['rid'] = 999;
        $forged = rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=').'.'.$signature;

        foreach ([$forged, $payload.'.'.strrev($signature), 'no-es-un-token', $payload, '', str_repeat('A', 600)] as $bad) {
            $response = $this->scan($bad);
            $this->assertContains($response->status(), [422], "Token aceptado: {$bad}");
            if ($bad !== '' && strlen($bad) <= 512) {
                $response->assertJsonPath('error.code', 'INVALID');
            }
        }
        $this->assertSame(0, Attendance::count());
    }

    #[Test]
    public function t17_un_qr_de_una_reserva_cancelada_o_inexistente_se_rechaza(): void
    {
        $token = $this->credential();
        $this->reservation->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->scan($token)->assertUnprocessable()->assertJsonPath('error.code', 'NOT_ACTIVE');

        $ghost = new Reservation;
        $ghost->id = 999999;
        $ghost->talk_id = $this->talk->id;
        $ghostToken = app(QrTokenService::class)->issue($ghost)['token'];
        $this->scan($ghostToken)->assertUnprocessable()->assertJsonPath('error.code', 'NOT_ACTIVE');

        $this->assertSame(0, Attendance::count());
    }

    #[Test]
    public function t18_un_escaneo_repetido_no_duplica_la_asistencia(): void
    {
        $token = $this->credential();
        $first = $this->scan($token)->assertCreated()->json('data.validated_at');

        $this->travel(5)->seconds();
        $this->scan($token)
            ->assertOk()
            ->assertJsonPath('data.result', 'ALREADY_REGISTERED')
            ->assertJsonPath('data.validated_at', $first);

        $this->assertSame(1, Attendance::count());
    }

    #[Test]
    public function t19_sin_rol_de_acceso_no_se_puede_validar(): void
    {
        $token = $this->credential();

        $this->scan($token, as: $this->lucia)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/checkins', ['token' => $token, 'talk_id' => $this->talk->id])->assertUnauthorized();
        $this->assertSame(0, Attendance::count());

        // El administrador sí puede.
        $this->scan($token, as: User::factory()->admin()->create())->assertCreated();
    }

    #[Test]
    public function un_qr_de_otra_charla_se_rechaza(): void
    {
        $token = $this->credential();
        $other = Talk::factory()->create();

        $this->scan($token, $other->id)->assertUnprocessable()->assertJsonPath('error.code', 'WRONG_TALK');
    }

    #[Test]
    public function un_token_con_fecha_de_emision_futura_se_rechaza(): void
    {
        $this->travel(10)->minutes();
        $future = $this->credential();
        $this->travel(-10)->minutes();

        $this->scan($future)->assertUnprocessable()->assertJsonPath('error.code', 'INVALID');
    }

    #[Test]
    public function cada_lectura_queda_registrada_sin_guardar_el_token(): void
    {
        $token = $this->credential();
        $this->scan($token)->assertCreated();
        $this->scan($token)->assertOk();
        $this->scan('basura')->assertUnprocessable();

        $this->assertSame(['registered', 'already', 'invalid'], ScanAttempt::orderBy('id')->pluck('result')->map->value->all());
        $this->assertDatabaseMissing('scan_attempts', ['result' => $token]);
    }

    #[Test]
    public function la_credencial_solo_se_abre_en_su_ventana_y_para_reservas_propias_y_activas(): void
    {
        // 61 minutos antes: aún cerrada.
        $this->travelTo(CarbonImmutable::parse('2027-02-24 08:59:00', 'UTC'));
        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertConflict()->assertJsonPath('error.code', 'OUTSIDE_WINDOW');

        // Al terminar la charla: cerrada.
        $this->travelTo(CarbonImmutable::parse('2027-02-24 10:45:00', 'UTC'));
        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertConflict()->assertJsonPath('error.code', 'OUTSIDE_WINDOW');

        $this->travelTo(CarbonImmutable::parse('2027-02-24 09:30:00', 'UTC'));
        $other = Reservation::factory()->for($this->talk)->create();
        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$other->id}/credential")->assertNotFound();

        $this->reservation->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertConflict()->assertJsonPath('error.code', 'NOT_ACTIVE');
    }

    #[Test]
    public function con_la_asistencia_registrada_ya_no_se_emite_credencial(): void
    {
        $this->scan($this->credential())->assertCreated();

        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertConflict()->assertJsonPath('error.code', 'ATTENDED');
    }

    #[Test]
    public function la_credencial_tiene_un_limite_de_10_peticiones_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->credential();
        }
        $this->actingAs($this->lucia)->getJson("/api/v1/reservations/{$this->reservation->id}/credential")
            ->assertStatus(429)->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    #[Test]
    public function registro_manual_busca_por_nombre_o_referencia_y_exige_motivo(): void
    {
        $this->actingAs($this->staff)->getJson("/api/v1/staff/talks/{$this->talk->id}/reservations?q=luc")
            ->assertOk()
            ->assertJsonPath('data.0.reservation_id', $this->reservation->id)
            ->assertJsonPath('data.0.attended', false);
        $this->actingAs($this->staff)->getJson("/api/v1/staff/talks/{$this->talk->id}/reservations?q=".$this->reservation->reference)
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->staff)->postJson('/api/v1/checkins/manual', ['reservation_id' => $this->reservation->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.reason.0', 'Indica el motivo del registro manual.');

        $this->actingAs($this->staff)->postJson('/api/v1/checkins/manual', [
            'reservation_id' => $this->reservation->id,
            'reason' => 'Móvil sin batería; identidad comprobada con DNI',
        ])->assertCreated()->assertJsonPath('data.method', 'manual');

        $this->assertSame('Móvil sin batería; identidad comprobada con DNI', Attendance::sole()->manual_reason);

        // Repetir el registro manual no duplica.
        $this->actingAs($this->staff)->postJson('/api/v1/checkins/manual', ['reservation_id' => $this->reservation->id, 'reason' => 'Otra vez'])
            ->assertOk()->assertJsonPath('data.result', 'ALREADY_REGISTERED');
        $this->assertSame(1, Attendance::count());
    }

    #[Test]
    public function el_personal_ve_las_charlas_del_dia_con_reservas_y_asistencias(): void
    {
        $this->scan($this->credential())->assertCreated();
        Reservation::factory()->for($this->talk)->create();

        $this->actingAs($this->staff)->getJson('/api/v1/staff/talks?date=2027-02-24')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->talk->id)
            ->assertJsonPath('data.0.reservations_confirmed', 2)
            ->assertJsonPath('data.0.attendances', 1);

        $this->actingAs($this->lucia)->getJson('/api/v1/staff/talks')->assertForbidden();
    }
}
