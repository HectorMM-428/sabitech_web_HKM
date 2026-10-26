<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Talk;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T08 · Concurrencia real: varios procesos PHP, cada uno con su conexión a MySQL,
 * reservan a la vez (sabitech:concurrency-demo). Sin RefreshDatabase a propósito:
 * los otros procesos no verían datos de una transacción sin confirmar. El comando
 * crea y borra sus propios datos.
 */
#[Group('concurrency')]
class ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    #[Test]
    public function t08_la_ultima_plaza_se_asigna_una_sola_vez(): void
    {
        $this->artisan('sabitech:concurrency-demo', ['--workers' => 12, '--seats' => 1])
            ->expectsOutputToContain('Reservas confirmadas en la base de datos: 1 (aforo 1, esperado 1)')
            ->assertSuccessful();

        $this->assertNoLeftovers();
    }

    #[Test]
    public function con_varias_plazas_se_llenan_exactamente(): void
    {
        $this->artisan('sabitech:concurrency-demo', ['--workers' => 12, '--seats' => 4])
            ->expectsOutputToContain('Reservas confirmadas en la base de datos: 4 (aforo 4, esperado 4)')
            ->assertSuccessful();
    }

    #[Test]
    public function el_doble_clic_del_mismo_usuario_crea_una_sola_reserva(): void
    {
        $this->artisan('sabitech:concurrency-demo', ['--workers' => 8, '--same-user' => true])
            ->expectsOutputToContain('Reservas confirmadas en la base de datos: 1 (aforo 1, esperado 1)')
            ->assertSuccessful();
    }

    #[Test]
    public function sin_bloqueo_hay_sobreventa_y_el_comando_lo_detecta(): void
    {
        // Demostración de por qué hace falta FOR UPDATE: la versión ingenua vende de más.
        $this->artisan('sabitech:concurrency-demo', ['--workers' => 8, '--seats' => 1, '--without-lock' => true])
            ->expectsOutputToContain('SOBREVENTA')
            ->assertFailed();

        $this->assertNoLeftovers();
    }

    private function assertNoLeftovers(): void
    {
        $this->assertSame(0, User::query()->where('email', 'like', '%@example.invalid')->count());
        $this->assertSame(0, Talk::query()->where('title', 'like', 'Prueba de concurrencia%')->count());
        $this->assertSame(0, Reservation::query()->whereDoesntHave('talk')->count());
    }
}
