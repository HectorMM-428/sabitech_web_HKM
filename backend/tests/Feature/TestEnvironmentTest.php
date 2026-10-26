<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Salvaguarda: las pruebas vacían las tablas en cada ejecución (RefreshDatabase).
 * Si alguna vez apuntaran a la base de datos de la demo, la borrarían. Esta prueba
 * falla antes de que ocurra (incidencia detectada el 26 oct 2026, ver docs/pruebas).
 */
class TestEnvironmentTest extends TestCase
{
    #[Test]
    public function las_pruebas_usan_siempre_la_base_de_datos_de_pruebas(): void
    {
        $this->assertSame('sabitech_test', config('database.connections.mysql.database'));
        $this->assertSame('testing', app()->environment());
    }
}
