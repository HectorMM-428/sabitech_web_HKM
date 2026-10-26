<?php

namespace Tests\Unit;

use App\Support\ReservationReference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReservationReferenceTest extends TestCase
{
    #[Test]
    public function genera_referencias_con_el_formato_del_contrato(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $this->assertMatchesRegularExpression(ReservationReference::PATTERN, ReservationReference::generate());
        }
    }

    #[Test]
    public function no_usa_caracteres_ambiguos(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[01OIL]/', ReservationReference::ALPHABET);
    }

    #[Test]
    public function la_referencia_cabe_en_la_columna_char_10(): void
    {
        $this->assertSame(10, strlen(ReservationReference::generate()));
    }
}
