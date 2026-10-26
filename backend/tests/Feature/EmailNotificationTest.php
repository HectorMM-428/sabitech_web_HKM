<?php

namespace Tests\Feature;

use App\Jobs\SendReservationConfirmation;
use App\Mail\ReservationConfirmedMail;
use App\Models\EmailNotification;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** Correo de confirmación: pruebas T12 (envío) y T13 (fallo y reintento controlado). */
class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $lucia;

    private Talk $talk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lucia = User::factory()->create(['name' => 'Lucía Ferrer', 'email' => 'lucia@sabitech.test']);
        $this->talk = Talk::factory()
            ->for(Speaker::factory()->create(['name' => 'Nuria Climent']))
            ->for(Room::factory()->create(['name' => 'Sala B2', 'location_details' => 'Segunda planta']))
            ->at('2027-02-24 12:30', 45)
            ->create(['title' => 'La última plaza']);
    }

    private function reserve(): int
    {
        return $this->actingAs($this->lucia)
            ->postJson('/api/v1/reservations', ['talk_id' => $this->talk->id])
            ->assertCreated()
            ->json('data.id');
    }

    #[Test]
    public function la_reserva_y_su_notificacion_pendiente_se_crean_juntas_y_el_envio_va_a_la_cola(): void
    {
        Queue::fake();

        $id = $this->reserve();

        $notification = EmailNotification::sole();
        $this->assertSame($id, $notification->reservation_id);
        $this->assertSame('pending', $notification->status->value);
        $this->assertSame('lucia@sabitech.test', $notification->recipient);
        Queue::assertPushed(SendReservationConfirmation::class, fn ($job) => $job->notificationId === $notification->id);
    }

    #[Test]
    public function t12_el_correo_se_envia_con_los_datos_de_la_reserva_y_queda_marcado_como_enviado(): void
    {
        Mail::fake();

        $id = $this->reserve(); // la cola es síncrona en las pruebas: el trabajo se ejecuta aquí
        $reservation = Reservation::findOrFail($id);

        Mail::assertSent(ReservationConfirmedMail::class, function (ReservationConfirmedMail $mail) use ($reservation) {
            $html = $mail->render();

            return $mail->hasTo('lucia@sabitech.test')
                && $mail->hasSubject('Reserva confirmada: La última plaza')
                && str_contains($html, 'La última plaza')
                && str_contains($html, 'miércoles, 24 de febrero de 2027')
                && str_contains($html, '12:30–13:15')
                && str_contains($html, 'Sala B2')
                && str_contains($html, 'Segunda planta')
                && str_contains($html, $reservation->reference)
                && str_contains($html, 'no registra tu asistencia');
        });

        $notification = EmailNotification::sole();
        $this->assertSame('sent', $notification->status->value);
        $this->assertSame(1, $notification->attempts);
        $this->assertNotNull($notification->sent_at);

        $this->actingAs($this->lucia)->getJson("/api/v1/me/reservations/{$id}")->assertJsonPath('data.email_status', 'sent');
    }

    #[Test]
    public function t13_si_el_correo_falla_la_reserva_se_conserva_y_el_error_se_guarda_sin_credenciales(): void
    {
        Queue::fake();
        $id = $this->reserve();
        $notification = EmailNotification::sole();

        Mail::shouldReceive('to->send')->once()->andThrow(
            new RuntimeException('Connection could not be established with host smtp://user:supersecreta@smtp.ejemplo:587 password=supersecreta')
        );
        (new SendReservationConfirmation($notification->id))->handle();

        $notification->refresh();
        $this->assertSame('failed', $notification->status->value);
        $this->assertSame(1, $notification->attempts);
        $this->assertStringNotContainsString('supersecreta', $notification->last_error);
        $this->assertStringContainsString('Connection could not be established', $notification->last_error);

        // La reserva sigue confirmada y visible para la persona, con el estado real del correo.
        $this->assertSame('confirmed', Reservation::findOrFail($id)->status->value);
        $this->actingAs($this->lucia)->getJson("/api/v1/me/reservations/{$id}")
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.email_status', 'failed');

        // Se programa un reintento automático con espera.
        Queue::assertPushed(SendReservationConfirmation::class, fn ($job) => $job->delay !== null);
    }

    #[Test]
    public function t13_el_reintento_manual_envia_el_correo_sin_crear_otra_reserva_ni_otra_notificacion(): void
    {
        Queue::fake();
        $this->reserve();
        $notification = EmailNotification::sole();
        $notification->update(['status' => 'failed', 'attempts' => 5, 'last_error' => 'Fallo anterior']);
        $admin = User::factory()->admin()->create();

        Mail::fake();
        $this->actingAs($admin)->postJson("/api/v1/admin/notifications/{$notification->id}/retry")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        // Ejecutamos el trabajo que el reintento ha puesto en la cola.
        (new SendReservationConfirmation($notification->id))->handle();
        Mail::assertSentCount(1);
        $this->assertSame('sent', $notification->fresh()->status->value);

        // Un segundo reintento de un correo ya enviado se rechaza y no envía nada.
        $this->actingAs($admin)->postJson("/api/v1/admin/notifications/{$notification->id}/retry")
            ->assertConflict()
            ->assertJsonPath('error.code', 'ALREADY_SENT');
        Mail::assertSentCount(1);

        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, EmailNotification::count());
    }

    #[Test]
    public function un_trabajo_repetido_no_envia_el_correo_dos_veces(): void
    {
        Queue::fake();
        $this->reserve();
        $notification = EmailNotification::sole();
        Mail::fake();

        (new SendReservationConfirmation($notification->id))->handle();
        (new SendReservationConfirmation($notification->id))->handle();

        Mail::assertSentCount(1);
        $this->assertSame(1, $notification->fresh()->attempts);
    }

    #[Test]
    public function tras_cinco_intentos_fallidos_no_hay_mas_reintentos_automaticos(): void
    {
        Queue::fake();
        $this->reserve();
        $notification = EmailNotification::sole();
        $notification->update(['status' => 'failed', 'attempts' => 4]);
        Queue::fake(); // vaciar lo encolado al reservar

        Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException('SMTP caído'));
        (new SendReservationConfirmation($notification->id))->handle();

        $this->assertSame(5, $notification->fresh()->attempts);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function un_envio_interrumpido_pasa_a_fallido_para_poder_reintentarlo(): void
    {
        Queue::fake();
        $this->reserve();
        $notification = EmailNotification::sole();
        $notification->update(['status' => 'sending', 'last_attempt_at' => now()->subMinutes(15)]);

        $this->artisan('sabitech:release-stuck-notifications')->assertSuccessful();

        $this->assertSame('failed', $notification->fresh()->status->value);
    }

    #[Test]
    public function solo_la_organizacion_ve_y_reintenta_los_correos(): void
    {
        Queue::fake();
        $this->reserve();
        $notification = EmailNotification::sole();

        $this->actingAs($this->lucia)->getJson('/api/v1/admin/notifications')->assertForbidden();
        $this->actingAs($this->lucia)->postJson("/api/v1/admin/notifications/{$notification->id}/retry")->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->getJson('/api/v1/admin/notifications')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/notifications?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.recipient', 'lucia@sabitech.test');
    }
}
