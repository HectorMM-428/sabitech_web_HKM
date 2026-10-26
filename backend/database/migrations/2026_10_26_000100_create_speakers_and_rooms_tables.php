<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ponentes y salas (diccionario de datos, sección 3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('affiliation', 160)->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            // Obligatorio si hay foto (accesibilidad); se valida en el formulario de administración.
            $table->string('photo_alt')->nullable();
            $table->datetimes();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('location_details')->nullable();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('speakers');
    }
};
