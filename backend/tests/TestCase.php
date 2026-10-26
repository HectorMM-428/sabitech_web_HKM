<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las peticiones de las pruebas se presentan como si vinieran de la SPA
        // (dominio con estado de Sanctum): así usan sesión y cookies como el navegador.
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->withHeader('Referer', 'http://localhost:5173/');
    }
}
