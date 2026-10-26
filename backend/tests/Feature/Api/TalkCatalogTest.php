<?php

namespace Tests\Feature\Api;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\Talk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Programa público: A01 y A02. Pruebas T01 y T02 del plan.
 */
class TalkCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function t01_devuelve_el_detalle_de_una_charla_publicada(): void
    {
        $speaker = Speaker::factory()->create(['name' => 'Marta Ibáñez']);
        $room = Room::factory()->create(['name' => 'Aula Magna']);
        $talk = Talk::factory()
            ->for($speaker)->for($room)
            ->at('2027-02-24 10:00', 45)
            ->create(['title' => 'Accesibilidad web', 'capacity' => 40]);

        $this->getJson("/api/v1/talks/{$talk->id}")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $talk->id,
                    'title' => 'Accesibilidad web',
                    'description' => $talk->description,
                    'speaker' => [
                        'id' => $speaker->id,
                        'name' => 'Marta Ibáñez',
                        'affiliation' => $speaker->affiliation,
                        'bio' => $speaker->bio,
                        'photo_url' => null,
                        'photo_alt' => null,
                    ],
                    'room' => ['id' => $room->id, 'name' => 'Aula Magna', 'location_details' => $room->location_details],
                    // Guardada en UTC (09:00), devuelta con el desfase de Madrid.
                    'starts_at' => '2027-02-24T10:00:00+01:00',
                    'ends_at' => '2027-02-24T10:45:00+01:00',
                    'capacity' => 40,
                    'available_seats' => 40,
                    'status' => 'published',
                ],
            ]);

        $this->assertDatabaseHas('talks', ['id' => $talk->id, 'starts_at' => '2027-02-24 09:00:00']);
    }

    #[Test]
    public function t02_una_charla_inexistente_responde_404_con_el_formato_de_error(): void
    {
        $this->getJson('/api/v1/talks/999999')
            ->assertNotFound()
            ->assertExactJson([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'No se ha encontrado el recurso.', 'fields' => null],
            ]);
    }

    #[Test]
    public function un_borrador_o_una_charla_cancelada_no_se_muestran(): void
    {
        $draft = Talk::factory()->draft()->create();
        $cancelled = Talk::factory()->cancelled()->create();
        Talk::factory()->create(['title' => 'Publicada']);

        $this->getJson("/api/v1/talks/{$draft->id}")->assertNotFound();
        $this->getJson("/api/v1/talks/{$cancelled->id}")->assertNotFound();

        $this->getJson('/api/v1/talks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Publicada');
    }

    #[Test]
    public function las_plazas_disponibles_solo_descuentan_reservas_confirmadas(): void
    {
        $talk = Talk::factory()->create(['capacity' => 10]);
        Reservation::factory()->count(3)->for($talk)->create();
        Reservation::factory()->count(2)->for($talk)->cancelled()->create();

        $this->getJson("/api/v1/talks/{$talk->id}")->assertJsonPath('data.available_seats', 7);
        $this->getJson('/api/v1/talks')->assertJsonPath('data.0.available_seats', 7);
    }

    #[Test]
    public function una_charla_llena_muestra_cero_plazas(): void
    {
        $talk = Talk::factory()->create(['capacity' => 2]);
        Reservation::factory()->count(2)->for($talk)->create();

        $this->getJson("/api/v1/talks/{$talk->id}")->assertJsonPath('data.available_seats', 0);
    }

    #[Test]
    public function el_programa_se_ordena_por_hora_y_se_pagina(): void
    {
        Talk::factory()->at('2027-02-24 12:00')->create(['title' => 'Tercera']);
        Talk::factory()->at('2027-02-24 10:00')->create(['title' => 'Primera']);
        Talk::factory()->at('2027-02-24 11:00')->create(['title' => 'Segunda']);

        $this->getJson('/api/v1/talks')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Primera')
            ->assertJsonPath('data.2.title', 'Tercera')
            ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 20, 'total' => 3, 'last_page' => 1])
            // La descripción solo va en el detalle.
            ->assertJsonMissingPath('data.0.description');

        Talk::factory()->count(20)->create();
        $this->getJson('/api/v1/talks?page=2')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function el_filtro_por_dia_usa_la_zona_de_madrid(): void
    {
        // 00:30 del 25 en Madrid = 23:30 del 24 en UTC: pertenece al día 25.
        Talk::factory()->at('2027-02-25 00:30')->create(['title' => 'Madrugada del 25']);
        Talk::factory()->at('2027-02-24 23:00')->create(['title' => 'Noche del 24']);

        $this->getJson('/api/v1/talks?date=2027-02-25')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Madrugada del 25');
    }

    #[Test]
    public function la_busqueda_encuentra_por_titulo_o_ponente_y_escapa_comodines(): void
    {
        $speaker = Speaker::factory()->create(['name' => 'Nuria Climent']);
        Talk::factory()->create(['title' => 'Concurrencia en reservas', 'description' => 'Bloqueos de fila.']);
        Talk::factory()->for($speaker)->create(['title' => 'Integridad de datos', 'description' => 'Índices únicos.']);

        $this->getJson('/api/v1/talks?q=concurrencia')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/talks?q=Climent')->assertJsonPath('data.0.title', 'Integridad de datos');
        // «%» no actúa como comodín: no devuelve todo el programa.
        $this->getJson('/api/v1/talks?q=%25')->assertJsonCount(0, 'data');
    }

    #[Test]
    public function un_filtro_mal_formado_responde_422_con_los_campos(): void
    {
        $this->getJson('/api/v1/talks?date=24-02-2027')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.fields.date.0', 'La fecha debe tener el formato AAAA-MM-DD.');
    }

    #[Test]
    public function el_programa_vacio_devuelve_una_lista_vacia(): void
    {
        $this->getJson('/api/v1/talks')
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1]]);
    }
}
