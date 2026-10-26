<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Convenciones comunes de la API: errores uniformes y salud del servicio. */
class ApiConventionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function una_ruta_inexistente_usa_el_formato_de_error_unico(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertExactJson(['error' => ['code' => 'NOT_FOUND', 'message' => 'No se ha encontrado el recurso.', 'fields' => null]]);
    }

    #[Test]
    public function un_metodo_no_permitido_responde_405_con_el_formato_de_error(): void
    {
        $this->deleteJson('/api/v1/talks')
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    #[Test]
    public function un_identificador_no_numerico_no_llega_a_la_base_de_datos(): void
    {
        $this->getJson('/api/v1/talks/abc')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    #[Test]
    public function la_salud_informa_de_base_de_datos_y_cola(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('queue', 'ok');
    }

    #[Test]
    public function un_trabajo_de_cola_atascado_marca_el_servicio_como_degradado(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(10)->getTimestamp(),
            'created_at' => now()->subMinutes(10)->getTimestamp(),
        ]);

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('queue', 'error');
    }
}
