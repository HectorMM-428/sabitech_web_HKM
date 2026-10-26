<?php

namespace Tests\Feature\Database;

use App\Models\Reservation;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las garantías que el diseño confía a la base de datos, probadas directamente
 * contra MySQL: aunque el código tuviera un error, el motor las mantiene.
 */
class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function no_puede_haber_dos_reservas_confirmadas_de_la_misma_persona_y_charla(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->create();
        Reservation::factory()->for($user)->for($talk)->create();

        $this->expectException(QueryException::class);
        Reservation::factory()->for($user)->for($talk)->create();
    }

    #[Test]
    public function si_puede_haber_varias_canceladas_y_una_confirmada(): void
    {
        $user = User::factory()->create();
        $talk = Talk::factory()->create();
        Reservation::factory()->for($user)->for($talk)->cancelled()->create();
        Reservation::factory()->for($user)->for($talk)->cancelled()->create();
        Reservation::factory()->for($user)->for($talk)->create();

        $this->assertSame(3, Reservation::where('user_id', $user->id)->count());
        $this->assertSame(1, Reservation::confirmed()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function la_columna_active_key_la_calcula_mysql(): void
    {
        $confirmed = Reservation::factory()->create();
        $cancelled = Reservation::factory()->cancelled()->create();

        $this->assertSame(1, (int) DB::table('reservations')->where('id', $confirmed->id)->value('active_key'));
        $this->assertNull(DB::table('reservations')->where('id', $cancelled->id)->value('active_key'));
    }

    #[Test]
    public function una_reserva_cancelada_debe_tener_fecha_de_cancelacion(): void
    {
        $this->expectException(QueryException::class);
        Reservation::factory()->create(['status' => 'cancelled', 'cancelled_at' => null]);
    }

    #[Test]
    public function el_aforo_debe_ser_positivo(): void
    {
        $this->expectException(QueryException::class);
        Talk::factory()->create(['capacity' => 0]);
    }

    #[Test]
    public function una_charla_debe_terminar_despues_de_empezar(): void
    {
        $this->expectException(QueryException::class);
        $start = now()->addDay();
        Talk::factory()->create(['starts_at' => $start, 'ends_at' => $start]);
    }

    #[Test]
    public function no_puede_haber_dos_asistencias_de_la_misma_persona_a_la_misma_charla(): void
    {
        $staff = User::factory()->staff()->create();
        $first = Reservation::factory()->cancelled()->create();
        $second = Reservation::factory()->for($first->user)->for($first->talk)->create();

        $row = fn (Reservation $r) => [
            'reservation_id' => $r->id,
            'user_id' => $r->user_id,
            'talk_id' => $r->talk_id,
            'validated_by' => $staff->id,
            'method' => 'qr',
            'validated_at' => now(),
            'created_at' => now(),
        ];

        DB::table('attendances')->insert($row($first));

        $this->expectException(QueryException::class);
        DB::table('attendances')->insert($row($second));
    }

    #[Test]
    public function una_asistencia_manual_exige_motivo(): void
    {
        $staff = User::factory()->staff()->create();
        $reservation = Reservation::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('attendances')->insert([
            'reservation_id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'talk_id' => $reservation->talk_id,
            'validated_by' => $staff->id,
            'method' => 'manual',
            'manual_reason' => null,
            'validated_at' => now(),
        ]);
    }

    #[Test]
    public function no_se_puede_borrar_una_charla_con_reservas(): void
    {
        $reservation = Reservation::factory()->create();

        $this->expectException(QueryException::class);
        $reservation->talk->delete();
    }

    #[Test]
    public function el_rol_no_se_asigna_en_masa(): void
    {
        // En desarrollo y pruebas el modo estricto lo convierte en error visible;
        // en producción el atributo se descarta. En ambos casos nadie se asigna un rol.
        $this->expectException(MassAssignmentException::class);
        User::create(['name' => 'Intruso', 'email' => 'x@example.test', 'password' => 'secreto-largo', 'role' => 'admin']);
    }

    #[Test]
    public function un_usuario_nuevo_es_asistente_por_defecto(): void
    {
        $user = User::create(['name' => 'Nueva', 'email' => 'nueva@example.test', 'password' => 'secreto-largo']);

        $this->assertSame('attendee', $user->fresh()->role->value);
    }
}
